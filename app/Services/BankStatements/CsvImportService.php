<?php

declare(strict_types=1);

namespace App\Services\BankStatements;

use App\Contracts\Services\TextAnalysisContract;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CsvImportService
{
    public const MAX_INPUT_BYTES = 20 * 1024 * 1024;

    public const MAX_RECORD_BYTES = 1024 * 1024;

    public const MAX_RECORDS = 100000;

    public function __construct(
        protected TextAnalysisContract $ai
    ) {}

    /**
     * Import a CSV bank statement file and create statement + transactions.
     */
    public function import(UploadedFile $file, int $userId, int $fileId): BankStatement
    {
        $csvContent = file_get_contents($file->getRealPath(), false, null, 0, self::MAX_INPUT_BYTES + 1);

        if ($csvContent === false || trim($csvContent) === '') {
            throw new Exception('CSV file is empty or unreadable.');
        }

        return $this->importFromContent($csvContent, $userId, $fileId);
    }

    /**
     * Import from raw CSV content string (for jobs that already have the content).
     */
    public function importFromContent(string $csvContent, int $userId, int $fileId, string $generation = 'initial'): BankStatement
    {
        $existing = BankStatement::where('user_id', $userId)->where('file_id', $fileId)->where('import_generation', $generation)->first();
        if ($existing) {
            return $existing;
        }
        if (trim($csvContent) === '') {
            throw new Exception('CSV content is empty.');
        }

        $this->assertInputSize($csvContent);
        $csvContent = $this->ensureUtf8($csvContent);
        $this->assertInputSize($csvContent);

        $delimiter = $this->detectDelimiter($csvContent);

        $headers = $this->parseHeaders($csvContent, $delimiter);
        $sampleRows = $this->sampleRows($csvContent, 5, $delimiter);

        if (empty($headers) || empty($sampleRows)) {
            throw new Exception('CSV file contains no data rows.');
        }

        File::where('user_id', $userId)->findOrFail($fileId);
        $mapping = File::find($fileId)?->meta['csv_mapping'] ?? $this->mapColumns($headers, $sampleRows);
        $this->validateMapping($headers, $sampleRows, $mapping);

        Log::info('[CsvImportService] Column mapping resolved', [
            'file_id' => $fileId,
            'mapping' => $mapping,
            'delimiter' => $delimiter,
            'provider' => $this->ai->getProviderName(),
        ]);

        $allRows = $this->parseAllRows($csvContent, $delimiter);

        return (new BankStatement)->getConnection()->transaction(function () use ($allRows, $mapping, $userId, $fileId, $generation, $csvContent): BankStatement {
            $statement = $this->createStatementFromCsv($allRows, $mapping, $userId, $fileId, $generation);
            $this->enrichFromFooter($csvContent, $statement);

            return $statement;
        });
    }

    /**
     * Detect the CSV delimiter by inspecting the header row.
     */
    public function detectDelimiter(string $csvContent): string
    {
        $lines = preg_split('/\R/', $csvContent, 2);
        $headerLine = $lines[0] ?? '';

        $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($delimiters) as $d) {
            $inQuote = false;
            for ($i = 0; $i < strlen($headerLine); $i++) {
                if ($headerLine[$i] === '"') {
                    $inQuote = ! $inQuote;
                } elseif (! $inQuote && $headerLine[$i] === $d) {
                    $delimiters[$d]++;
                }
            }
        }

        arsort($delimiters);
        $best = array_key_first($delimiters);

        return $delimiters[$best] > 0 ? $best : ',';
    }

    /**
     * Convert content to UTF-8 if it's in another encoding.
     */
    protected function ensureUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $encoding = mb_detect_encoding($content, ['Windows-1252', 'ISO-8859-1', 'ISO-8859-15'], true);

            if ($encoding) {
                Log::info('[CsvImportService] Converting encoding', ['from' => $encoding, 'to' => 'UTF-8']);
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            } else {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
            }
        }

        return mb_scrub($content, 'UTF-8');
    }

    /**
     * Parse header row from CSV content.
     *
     * @return list<string>
     */
    public function parseHeaders(string $csvContent, string $delimiter = ','): array
    {
        foreach ($this->readRecords($csvContent, $delimiter) as $record) {
            return $record;
        }

        return [];
    }

    /**
     * Get sample data rows from CSV for column mapping.
     *
     * @return list<list<string>>
     */
    public function sampleRows(string $csvContent, int $count = 5, string $delimiter = ','): array
    {
        $rows = [];
        foreach ($this->readRecords($csvContent, $delimiter) as $index => $record) {
            if ($index === 0) {
                continue;
            }
            if (count($rows) >= $count) {
                break;
            }
            $rows[] = $record;
        }

        return $rows;
    }

    /**
     * Use AI to auto-detect column mapping from headers and sample data.
     * Falls back to structural data-pattern analysis if AI is unavailable.
     *
     * @return array<string, int|null> Maps standard field names to column indices
     */
    public function mapColumns(array $headers, array $sampleRows): array
    {
        $headersStr = implode(', ', array_map(
            fn ($h, $i) => "Column {$i}: \"{$h}\"",
            $headers,
            array_keys($headers)
        ));

        $sampleStr = '';
        foreach (array_slice($sampleRows, 0, 3) as $i => $row) {
            $sampleStr .= "Row {$i}: ".implode(' | ', $row)."\n";
        }

        $prompt = <<<PROMPT
        You are analyzing a bank statement CSV file to identify column types.
        The headers and data may be in ANY language (Norwegian, English, German, etc.).

        Headers: {$headersStr}

        Sample data rows:
        {$sampleStr}

        Map each column to the correct standard field. Return a JSON object where each key is a field name
        and the value is the 0-based column index (integer). Set to null if the field is not present.

        Standard fields:
        - transaction_date: Date the transaction occurred (date-formatted values like "2025-12-29" or "29.12.2025")
        - posting_date: Date the transaction was posted/booked (if a SECOND date column exists)
        - description: Main text describing the transaction (merchant name, payment narrative)
        - amount: A SINGLE column with signed monetary amounts (negative=debit, positive=credit). Only if NOT split.
        - debit: Column for outgoing/withdrawal amounts ONLY. Use when amounts are split into two separate columns.
        - credit: Column for incoming/deposit amounts ONLY. Use when amounts are split into two separate columns.
        - balance: Running account balance AFTER each transaction (a per-row balance). null if no such column exists.
        - reference: Transaction reference or archive number
        - counterparty: Name of the other party (recipient or sender)
        - type: Transaction type/category label (e.g., "Purchase", "Transfer", "Fee")
        - currency: Column with currency codes (e.g., "NOK", "USD", "EUR")

        CRITICAL — read these rules carefully:
        1. Account numbers (e.g., "9230 34 28978", "8101 50 88257") are NOT amounts or balances. They identify bank accounts. Do NOT map them to amount, debit, credit, or balance.
        2. Reference numbers, archive numbers, and ID columns are NOT amounts. They are identifiers.
        3. Only map "balance" if there is a column showing the running account balance per transaction row. Most bank CSVs do NOT have this — set balance to null.
        4. Monetary amounts are typically small-to-medium numbers with decimals (e.g., -378.00, -12.44, 181183). Columns with 10+ digit values are likely account numbers, NOT amounts.
        5. If amounts are in ONE column (signed): map "amount", set "debit" and "credit" to null.
        6. If amounts are SPLIT into two columns (money in / money out): map "credit" and "debit", set "amount" to null.
        7. Sender/account holder columns are NOT counterparty. Counterparty is the OTHER party (recipient name).
        8. Return ONLY a valid JSON object, no other text.
        PROMPT;

        try {
            $result = $this->ai->analyze($prompt);

            if (is_array($result) && $this->isValidMapping($result)) {
                $mapping = $this->normalizeMapping($result);
                $this->validateMapping($headers, $sampleRows, $mapping);

                return $mapping;
            }

            Log::warning('[CsvImportService] AI returned invalid mapping, falling back to structural analysis');
        } catch (Exception $e) {
            Log::warning('[CsvImportService] AI mapping failed, falling back to structural analysis', [
                'error' => $e->getMessage(),
            ]);
        }

        $mapping = $this->structuralFallbackMapping($headers, $sampleRows);
        $this->validateMapping($headers, $sampleRows, $mapping);

        return $mapping;
    }

    /**
     * Create a BankStatement and its transactions from parsed CSV rows.
     */
    public function createStatementFromCsv(array $rows, array $mapping, int $userId, int $fileId, string $generation = 'initial'): BankStatement
    {
        return (new BankStatement)->getConnection()->transaction(function () use ($rows, $mapping, $userId, $fileId, $generation): BankStatement {
            File::where('user_id', $userId)->lockForUpdate()->findOrFail($fileId);
            $existing = BankStatement::where('user_id', $userId)->where('file_id', $fileId)->where('import_generation', $generation)->first();
            if ($existing) {
                return $existing;
            }
            if ($generation === 'initial') {
                $legacy = BankStatement::where('user_id', $userId)->where('file_id', $fileId)->whereNull('import_generation')->first();
                if ($legacy) {
                    $legacy->update(['import_generation' => $generation]);

                    return $legacy;
                }
            }

            return $this->persistStatement($rows, $mapping, $userId, $fileId, $generation);
        });
    }

    protected function persistStatement(array $rows, array $mapping, int $userId, int $fileId, string $generation): BankStatement
    {
        $this->validateMapping(array_fill(0, count($rows[0] ?? []), ''), $rows, $mapping);
        $transactions = [];
        $totalCredits = 0;
        $totalDebits = 0;
        $firstDate = null;
        $lastDate = null;

        foreach ($rows as $row) {
            $txData = $this->mapRowToTransaction($row, $mapping);
            if ($txData === null) {
                continue;
            }

            $transactions[] = $txData;

            $amount = (float) ($txData['amount'] ?? 0);
            if ($amount > 0) {
                $totalCredits += $amount;
            } else {
                $totalDebits += abs($amount);
            }

            if ($txData['transaction_date']) {
                if ($firstDate === null || $txData['transaction_date'] < $firstDate) {
                    $firstDate = $txData['transaction_date'];
                }
                if ($lastDate === null || $txData['transaction_date'] > $lastDate) {
                    $lastDate = $txData['transaction_date'];
                }
            }
        }

        if (empty($transactions)) {
            throw new Exception('No valid transactions found in CSV.');
        }

        $currency = $this->detectCurrency($transactions, $userId);

        if ($transactions[0]['transaction_date'] > end($transactions)['transaction_date']) {
            $transactions = array_reverse($transactions);
        }
        usort($transactions, fn (array $a, array $b): int => $a['transaction_date'] <=> $b['transaction_date']);

        $firstBalance = $transactions[0]['balance_after'] ?? null;
        $firstAmount = (float) ($transactions[0]['amount'] ?? 0);
        $openingBalance = $firstBalance !== null ? round($firstBalance - $firstAmount, 2) : null;
        $closingBalance = end($transactions)['balance_after'] ?? null;

        $statement = BankStatement::create([
            'file_id' => $fileId,
            'import_generation' => $generation,
            'user_id' => $userId,
            'bank_name' => null,
            'statement_date' => $lastDate,
            'statement_period_start' => $firstDate,
            'statement_period_end' => $lastDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'currency' => $currency,
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'transaction_count' => count($transactions),
        ]);

        foreach ($transactions as $txData) {
            $txCurrency = $txData['currency'] ?? $currency;
            unset($txData['currency']);

            BankTransaction::create(array_merge($txData, [
                'bank_statement_id' => $statement->id,
                'user_id' => $userId,
                'currency' => $txCurrency,
            ]));
        }

        ExtractableEntity::create([
            'file_id' => $fileId,
            'user_id' => $userId,
            'entity_type' => 'bank_statement',
            'entity_id' => $statement->id,
            'is_primary' => true,
            'extraction_provider' => 'csv_import',
            'extraction_model' => null,
            'confidence_score' => 1.0,
            'extracted_at' => now(),
        ]);

        Log::info('[CsvImportService] Statement created from CSV', [
            'statement_id' => $statement->id,
            'transaction_count' => count($transactions),
            'period' => "{$firstDate} to {$lastDate}",
        ]);

        return $statement;
    }

    /**
     * Extract summary data from CSV footer lines via AI and enrich the statement.
     */
    protected function enrichFromFooter(string $csvContent, BankStatement $statement): void
    {
        $lines = preg_split('/\R/', trim($csvContent));

        $footerLines = array_slice($lines, max(1, count($lines) - 10));
        $footerText = implode("\n", $footerLines);

        if (trim($footerText) === '') {
            return;
        }

        $prompt = <<<PROMPT
        Analyze these lines from the end of a bank statement CSV file.
        Extract any summary/footer information. The text may be in ANY language.

        Lines:
        {$footerText}

        Return a JSON object with these fields (set to null if not found):
        - opening_balance: The opening/starting balance as a plain number (e.g., "30 868,86 NOK" → 30868.86)
        - closing_balance: The closing/ending balance as a plain number
        - account_number: The bank account number as a string
        - bank_name: The bank name as a string

        IMPORTANT:
        - Only extract values from summary/total/balance lines, NOT from individual transaction rows.
        - Convert all amounts to plain decimal numbers (no thousands separators, use dot for decimal).
        - Return ONLY valid JSON, no other text.
        PROMPT;

        try {
            $result = $this->ai->analyze($prompt);

            $updates = [];

            if (isset($result['opening_balance']) && $result['opening_balance'] !== null) {
                $updates['opening_balance'] = (float) $result['opening_balance'];
            }
            if (isset($result['closing_balance']) && $result['closing_balance'] !== null) {
                $updates['closing_balance'] = (float) $result['closing_balance'];
            }
            if (! empty($result['account_number'])) {
                $updates['account_number'] = (string) $result['account_number'];
            }
            if (! empty($result['bank_name'])) {
                $updates['bank_name'] = (string) $result['bank_name'];
            }

            if (! empty($updates)) {
                $statement->update($updates);

                Log::info('[CsvImportService] Statement enriched from footer data', [
                    'statement_id' => $statement->id,
                    'updates' => array_keys($updates),
                ]);
            }
        } catch (QueryException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::info('[CsvImportService] No footer data extracted', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Parse all data rows from CSV content.
     *
     * @return list<list<string>>
     */
    protected function parseAllRows(string $csvContent, string $delimiter = ','): array
    {
        $records = iterator_to_array($this->readRecords($csvContent, $delimiter), false);

        return array_slice($records, 1);
    }

    protected function assertInputSize(string $content): void
    {
        if (strlen($content) > self::MAX_INPUT_BYTES) {
            throw new Exception('CSV exceeds the 20 MB input limit.');
        }
    }

    /** @return \Generator<int, list<string>> */
    protected function readRecords(string $content, string $delimiter): \Generator
    {
        $this->assertInputSize($content);
        if (strlen($delimiter) !== 1) {
            throw new Exception('CSV delimiter must be one character.');
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new Exception('Cannot open CSV reader.');
        }
        try {
            fwrite($stream, $this->ensureUtf8($content));
            rewind($stream);
            $count = 0;
            while (! feof($stream)) {
                $start = ftell($stream);
                $record = fgetcsv($stream, 0, $delimiter, '"', '');
                if (ftell($stream) - $start > self::MAX_RECORD_BYTES) {
                    throw new Exception('CSV record exceeds the 1 MB limit.');
                }
                if ($record === false || $record === [null]) {
                    continue;
                }
                if (++$count > self::MAX_RECORDS) {
                    throw new Exception('CSV exceeds the record limit.');
                }
                yield array_map(static fn (?string $value): string => trim($value ?? ''), $record);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Map a single CSV row to transaction data using the column mapping.
     *
     * @return array<string, mixed>|null
     */
    protected function mapRowToTransaction(array $row, array $mapping): ?array
    {
        $get = fn (string $field) => isset($mapping[$field]) && $mapping[$field] !== null && isset($row[$mapping[$field]])
            ? trim($row[$mapping[$field]])
            : null;

        $description = $get('description');
        if ($description === null || $description === '') {
            return null;
        }

        // Require at least one valid date to filter out footer/summary rows
        $txDate = $this->parseDate($get('transaction_date'), $mapping['date_format'] ?? null);
        $postDate = $this->parseDate($get('posting_date'), $mapping['date_format'] ?? null);
        if ($txDate === null && $postDate === null) {
            return null;
        }

        $amount = null;
        if ($get('amount') !== null && $get('amount') !== '') {
            $amount = $this->parseAmount($get('amount'));
        } elseif ($get('debit') !== null || $get('credit') !== null) {
            $debit = $this->parseAmount($get('debit') ?? '0');
            $credit = $this->parseAmount($get('credit') ?? '0');
            $amount = ($credit ?? 0) - abs($debit ?? 0);
        }

        if ($amount === null) {
            return null;
        }

        $transactionType = $amount >= 0 ? 'credit' : 'debit';

        $balance = $get('balance') !== null ? $this->parseAmount($get('balance')) : null;
        $currency = $get('currency');

        return [
            'transaction_date' => $txDate ?? $postDate,
            'posting_date' => $postDate,
            'description' => $description,
            'reference' => $get('reference'),
            'transaction_type' => $transactionType,
            'amount' => round($amount, 2),
            'balance_after' => $balance !== null ? round($balance, 2) : null,
            'counterparty_name' => $get('counterparty'),
            'currency' => $currency,
        ];
    }

    /**
     * Parse a numeric amount string, handling various formats.
     */
    protected function parseAmount(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $cleaned = preg_replace('/\s|\x{00A0}/u', '', trim($value));
        $cleaned = preg_replace('/^[A-Z]{3}|[A-Z]{3}$/i', '', $cleaned);
        if (! preg_match('/^[+-]?\d[\d.,]*$/', $cleaned)) {
            return null;
        }

        // European format (1.234,56) vs US format (1,234.56)
        if (preg_match('/\d+\.\d{3},\d{2}$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/\d+,\d{2}$/', $cleaned) && ! str_contains($cleaned, '.')) {
            $cleaned = str_replace(',', '.', $cleaned);
        } else {
            $cleaned = str_replace(',', '', $cleaned);
        }

        if (! is_numeric($cleaned)) {
            return null;
        }
        $result = (float) $cleaned;

        return is_finite($result) ? $result : null;
    }

    /**
     * Parse a date string in various formats.
     */
    protected function parseDate(?string $value, ?string $dateFormat = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $formats = [
            'Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'd-m-Y',
            'Y/m/d', 'd M Y', 'M d, Y', 'Y-m-d\TH:i:s',
        ];

        if ($dateFormat !== null) {
            $formats = [$dateFormat];
        }
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));
                if ($date && ! Carbon::getLastErrors() && $date->format($format) === trim($value) && $date->year > 1990 && $date->year < 2100) {
                    return $date->format('Y-m-d');
                }
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }

    /**
     * Detect currency from transaction data or fall back to user preference.
     */
    protected function detectCurrency(array $transactions, int $userId): string
    {
        $currencies = array_filter(array_column($transactions, 'currency'));

        if (! empty($currencies)) {
            $counts = array_count_values($currencies);
            arsort($counts);

            return array_key_first($counts);
        }

        $user = User::find($userId);

        return $user?->preference('currency') ?? config('paperpulse.defaults.currency', 'USD');
    }

    /**
     * Validate that a column mapping result has the expected structure.
     */
    protected function isValidMapping(array $mapping): bool
    {
        if (! array_key_exists('transaction_date', $mapping) || ! array_key_exists('description', $mapping)) {
            return false;
        }

        $hasAmount = array_key_exists('amount', $mapping) && $mapping['amount'] !== null;
        $hasDebitCredit = (array_key_exists('debit', $mapping) && $mapping['debit'] !== null)
            || (array_key_exists('credit', $mapping) && $mapping['credit'] !== null);

        return $hasAmount || $hasDebitCredit;
    }

    /**
     * Reject ambiguous mappings before any financial records are created.
     *
     * @param  array<string, mixed>  $mapping
     */
    public function validateMapping(array $headers, array $rows, array $mapping): void
    {
        $fail = static function (string $message): never {
            throw ValidationException::withMessages(['mapping' => $message.' Correct the CSV column mapping and retry.']);
        };
        if (! $this->isValidMapping($mapping) || $mapping['transaction_date'] === null || $mapping['description'] === null) {
            $fail('Select a date, description and signed amount or debit/credit columns.');
        }
        $indices = [];
        foreach ($mapping as $field => $index) {
            if ($field === 'date_format' || $index === null) {
                continue;
            }
            if (! is_int($index) || $index < 0 || $index >= count($headers)) {
                $fail("The {$field} column is outside the CSV column range.");
            }
            if (in_array($index, $indices, true)) {
                $fail('A column cannot represent multiple fields.');
            }
            $indices[] = $index;
        }
        if (isset($mapping['amount']) && (isset($mapping['debit']) || isset($mapping['credit']))) {
            $fail('Use either one signed amount column or separate debit/credit columns.');
        }
        foreach ($rows as $offset => $row) {
            $dateValue = $row[$mapping['transaction_date']] ?? '';
            $date = $this->parseDate($dateValue, $mapping['date_format'] ?? null);
            if ($date === null) {
                if (preg_match('/\d{1,4}[-.\/]\d{1,2}[-.\/]\d{1,4}/', $dateValue)) {
                    $fail('Row '.($offset + 2).' contains an invalid date.');
                }

                continue;
            }
            if (! isset($mapping['date_format']) && preg_match('/^(\d{1,2})\/(\d{1,2})\/\d{4}$/', $dateValue, $parts)
                && (int) $parts[1] <= 12 && (int) $parts[2] <= 12 && $parts[1] !== $parts[2]) {
                $fail('Choose day/month/year or month/day/year for ambiguous dates.');
            }
            if (($row[$mapping['description']] ?? '') === '') {
                continue;
            }
            if (isset($mapping['amount'])) {
                if ($this->parseAmount($row[$mapping['amount']] ?? '') === null) {
                    $fail('Row '.($offset + 2).' contains an invalid amount.');
                }
            } else {
                $debitValue = isset($mapping['debit']) ? ($row[$mapping['debit']] ?? '') : '';
                $creditValue = isset($mapping['credit']) ? ($row[$mapping['credit']] ?? '') : '';
                $debit = $debitValue === '' ? 0 : $this->parseAmount($debitValue);
                $credit = $creditValue === '' ? 0 : $this->parseAmount($creditValue);
                if ($debit === null || $credit === null || $credit < 0 || ($debit !== 0 && $credit !== 0) || ($debitValue === '' && $creditValue === '')) {
                    $fail('Row '.($offset + 2).' must contain one debit magnitude (signed or unsigned) or one positive credit.');
                }
            }
            if (isset($mapping['balance']) && ($row[$mapping['balance']] ?? '') !== '' && $this->parseAmount($row[$mapping['balance']]) === null) {
                $fail('Row '.($offset + 2).' contains an invalid running balance.');
            }
        }
    }

    /**
     * Normalize AI mapping response — ensure all standard fields exist and values are int|null.
     *
     * @return array<string, int|null>
     */
    protected function normalizeMapping(array $mapping): array
    {
        $standardFields = [
            'transaction_date', 'posting_date', 'description', 'amount',
            'debit', 'credit', 'balance', 'reference', 'counterparty', 'type',
            'currency',
        ];

        $normalized = [];
        foreach ($standardFields as $field) {
            $value = $mapping[$field] ?? null;
            $normalized[$field] = is_int($value) ? $value : null;
        }

        return $normalized;
    }

    /**
     * Last-resort structural fallback: analyze data patterns (dates, numbers)
     * to guess column roles. No language-specific logic — purely structural.
     *
     * @return array<string, int|null>
     */
    protected function structuralFallbackMapping(array $headers, array $sampleRows): array
    {
        $mapping = [
            'transaction_date' => null,
            'posting_date' => null,
            'description' => null,
            'amount' => null,
            'debit' => null,
            'credit' => null,
            'balance' => null,
            'reference' => null,
            'counterparty' => null,
            'type' => null,
            'currency' => null,
        ];

        if (empty($sampleRows)) {
            return $mapping;
        }

        $columnCount = count($headers);
        $dateColumns = [];
        $numericColumns = [];
        $textColumns = [];

        for ($col = 0; $col < $columnCount; $col++) {
            $dateScore = 0;
            $numericScore = 0;
            $textScore = 0;
            $hasNegative = false;
            $hasEmpty = false;
            $rowCount = 0;
            $parsedValues = [];
            $maxDigits = 0;

            foreach ($sampleRows as $row) {
                $value = trim($row[$col] ?? '');
                if ($value === '') {
                    $hasEmpty = true;

                    continue;
                }
                $rowCount++;

                if ($this->parseDate($value) !== null) {
                    $dateScore++;
                }

                $parsed = $this->parseAmount($value);
                if ($parsed !== null) {
                    $numericScore++;
                    $parsedValues[] = $parsed;
                    if ($parsed < 0) {
                        $hasNegative = true;
                    }
                    $digitCount = strlen(preg_replace('/[^\d]/', '', explode('.', (string) abs($parsed))[0]));
                    $maxDigits = max($maxDigits, $digitCount);
                }

                if (preg_match('/[a-zA-Z\p{L}]/u', $value)) {
                    $textScore++;
                }
            }

            if ($rowCount === 0) {
                continue;
            }

            $dateRatio = $dateScore / $rowCount;
            $numericRatio = $numericScore / $rowCount;
            $textRatio = $textScore / $rowCount;

            if ($dateRatio >= 0.5) {
                $dateColumns[] = ['index' => $col, 'score' => $dateRatio];
            } elseif ($numericRatio >= 0.5 && $textRatio < 0.3) {
                // Filter out account/reference numbers:
                // - Constant value across all rows → likely account number
                // - Values with 10+ digits → likely account/reference number
                $isConstant = count(array_unique($parsedValues)) <= 1 && count($parsedValues) > 1;
                $isLargeNumber = $maxDigits >= 10;

                if ($isConstant || $isLargeNumber) {
                    Log::debug('[CsvImportService] Structural fallback: skipped likely identifier column', [
                        'column' => $col,
                        'header' => $headers[$col] ?? '',
                        'reason' => $isConstant ? 'constant_value' : 'large_number',
                        'max_digits' => $maxDigits,
                    ]);

                    continue;
                }

                $numericColumns[] = [
                    'index' => $col,
                    'score' => $numericRatio,
                    'has_negative' => $hasNegative,
                    'has_empty' => $hasEmpty,
                ];
            } elseif ($textRatio >= 0.5) {
                $textColumns[] = ['index' => $col, 'score' => $textRatio, 'avg_length' => $this->avgLength($sampleRows, $col)];
            }
        }

        usort($dateColumns, fn ($a, $b) => $a['index'] <=> $b['index']);
        if (! empty($dateColumns)) {
            $mapping['transaction_date'] = $dateColumns[0]['index'];
            if (count($dateColumns) > 1) {
                $mapping['posting_date'] = $dateColumns[1]['index'];
            }
        }

        // Description typically follows date columns in bank CSVs; fall back to longest text column
        $lastDateIndex = ! empty($dateColumns) ? end($dateColumns)['index'] : -1;
        usort($textColumns, fn ($a, $b) => $a['index'] <=> $b['index']);
        $descriptionCol = null;
        foreach ($textColumns as $tc) {
            if ($tc['index'] > $lastDateIndex) {
                $descriptionCol = $tc['index'];
                break;
            }
        }
        if ($descriptionCol === null && ! empty($textColumns)) {
            usort($textColumns, fn ($a, $b) => $b['avg_length'] <=> $a['avg_length']);
            $descriptionCol = $textColumns[0]['index'];
        }
        $mapping['description'] = $descriptionCol;

        $roles = [
            'transaction_date' => ['date', 'dato', 'utført dato'],
            'description' => ['description', 'desc', 'forklaring', 'beskrivelse'],
            'amount' => ['amount', 'beløp', 'sum'],
            'balance' => ['balance', 'saldo'],
            'debit' => ['debit', 'withdrawal', 'money out', 'beløp ut'],
            'credit' => ['credit', 'deposit', 'money in', 'beløp inn'],
        ];
        foreach ($headers as $index => $header) {
            foreach ($roles as $role => $names) {
                if (in_array(mb_strtolower(trim($header)), $names, true)) {
                    $mapping[$role] = $index;
                }
            }
        }
        if ($mapping['amount'] === null && $mapping['debit'] === null && $mapping['credit'] === null) {
            if (count($numericColumns) === 1) {
                $mapping['amount'] = $numericColumns[0]['index'];
            } else {
                $withNegative = array_values(array_filter($numericColumns, fn (array $column): bool => $column['has_negative']));
                if (count($withNegative) === 1 && ! $withNegative[0]['has_empty']) {
                    $mapping['amount'] = $withNegative[0]['index'];
                } else {
                    throw ValidationException::withMessages(['mapping' => 'Amount columns are ambiguous. Correct the CSV column mapping and retry.']);
                }
            }
        }

        Log::info('[CsvImportService] Structural fallback mapping applied', [
            'mapping' => $mapping,
            'date_columns' => count($dateColumns),
            'numeric_columns' => count($numericColumns),
            'text_columns' => count($textColumns),
        ]);

        return $mapping;
    }

    /**
     * Calculate average non-empty string length for a column across sample rows.
     */
    private function avgLength(array $rows, int $col): float
    {
        $lengths = [];
        foreach ($rows as $row) {
            $value = trim($row[$col] ?? '');
            if ($value !== '') {
                $lengths[] = mb_strlen($value);
            }
        }

        return empty($lengths) ? 0.0 : array_sum($lengths) / count($lengths);
    }
}
