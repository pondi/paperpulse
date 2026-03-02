<?php

use App\Contracts\Services\TextAnalysisContract;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\User;
use App\Services\BankStatements\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buildCsv(array $headers, array $rows, string $delimiter = ','): string
{
    $lines = [implode($delimiter, $headers)];
    foreach ($rows as $row) {
        $lines[] = implode($delimiter, $row);
    }

    return implode("\n", $lines);
}

function createCsvImportService(?TextAnalysisContract $ai = null): CsvImportService
{
    if ($ai === null) {
        $ai = Mockery::mock(TextAnalysisContract::class);
        $ai->shouldReceive('analyze')->andThrow(new Exception('AI unavailable'));
        $ai->shouldReceive('getProviderName')->andReturn('mock');
    }

    return new CsvImportService($ai);
}

// ==========================================
// Header Parsing
// ==========================================

it('parses CSV headers correctly', function () {
    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [['2026-01-15', 'Store purchase', '-250.00', '5000.00']]
    );

    $service = createCsvImportService();
    $headers = $service->parseHeaders($csv);

    expect($headers)->toBe(['Date', 'Description', 'Amount', 'Balance']);
});

it('returns empty headers for empty CSV', function () {
    $service = createCsvImportService();
    $headers = $service->parseHeaders('');

    expect($headers)->toBe([]);
});

// ==========================================
// Sample Rows
// ==========================================

it('extracts sample rows from CSV', function () {
    $csv = buildCsv(
        ['Date', 'Desc', 'Amount'],
        [
            ['2026-01-01', 'Grocery store', '-100.00'],
            ['2026-01-02', 'Salary', '30000.00'],
            ['2026-01-03', 'Subscription', '-149.00'],
        ]
    );

    $service = createCsvImportService();
    $rows = $service->sampleRows($csv, 2);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['2026-01-01', 'Grocery store', '-100.00']);
});

it('returns empty sample rows for header-only CSV', function () {
    $csv = "Date,Description,Amount\n";

    $service = createCsvImportService();
    $rows = $service->sampleRows($csv);

    expect($rows)->toBe([]);
});

// ==========================================
// Column Mapping (AI)
// ==========================================

it('uses AI for column mapping when available', function () {
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            'transaction_date' => 0,
            'description' => 1,
            'amount' => 2,
            'balance' => 3,
            'posting_date' => null,
            'debit' => null,
            'credit' => null,
            'reference' => null,
            'counterparty' => null,
            'type' => null,
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $mapping = $service->mapColumns(
        ['Date', 'Desc', 'Sum', 'Saldo'],
        [['2026-01-15', 'Test', '-100.00', '5000.00']]
    );

    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['description'])->toBe(1);
    expect($mapping['amount'])->toBe(2);
    expect($mapping['balance'])->toBe(3);
});

it('uses AI for debit/credit split column mapping', function () {
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andReturn([
            'transaction_date' => 0,
            'posting_date' => 1,
            'description' => 2,
            'credit' => 3,
            'debit' => 4,
            'amount' => null,
            'balance' => null,
            'reference' => null,
            'counterparty' => 6,
            'type' => null,
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $mapping = $service->mapColumns(
        ['Utført dato', 'Bokført dato', 'Beskrivelse', 'Beløp inn', 'Beløp ut', 'Valuta', 'Mottakernavn'],
        [['05.01.2026', '05.01.2026', 'Omkostninger', '', '-32', 'NOK', 'NETTBEDR GEBYR']]
    );

    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['posting_date'])->toBe(1);
    expect($mapping['description'])->toBe(2);
    expect($mapping['credit'])->toBe(3);
    expect($mapping['debit'])->toBe(4);
    expect($mapping['counterparty'])->toBe(6);
});

it('falls back to structural analysis when AI fails', function () {
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->once()
        ->andThrow(new Exception('API error'));
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $mapping = $service->mapColumns(
        ['Date', 'Description', 'Amount', 'Balance'],
        [['2026-01-15', 'Test purchase', '-100.00', '5000.00']]
    );

    // Should still resolve via structural data-pattern analysis
    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['description'])->toBe(1);
    expect($mapping['amount'])->toBe(2);
});

// ==========================================
// Structural Fallback Mapping
// ==========================================

it('maps columns via structural analysis for standard CSV', function () {
    $service = createCsvImportService();
    $mapping = $service->mapColumns(
        ['Date', 'Description', 'Amount', 'Balance'],
        [['2026-01-15', 'Test purchase', '-100.00', '5000.00']]
    );

    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['description'])->toBe(1);
    expect($mapping['amount'])->toBe(2);
    expect($mapping['balance'])->toBe(3);
});

it('maps columns via structural analysis regardless of header language', function () {
    $service = createCsvImportService();
    $mapping = $service->mapColumns(
        ['Dato', 'Forklaring', 'Beløp', 'Saldo'],
        [['15.01.2026', 'Dagligvarer butikk', '-250.00', '5000.00']]
    );

    // Structural analysis looks at data patterns, not header names
    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['description'])->toBe(1);
    expect($mapping['amount'])->toBe(2);
    expect($mapping['balance'])->toBe(3);
});

it('structural fallback excludes account numbers and picks correct description', function () {
    // Simulates a complex 17-column BN Bank CSV with account numbers, split amounts, etc.
    $service = createCsvImportService();
    $mapping = $service->mapColumns(
        ['Utført dato', 'Bokført dato', 'Rentedato', 'Beskrivelse', 'Type', 'Undertype',
            'Fra konto', 'Avsender', 'Til konto', 'Mottakernavn', 'Beløp inn', 'Beløp ut',
            'Valuta', 'Status', 'Numref', 'Arkivref', 'Melding'],
        [
            ['29.12.2025', '29.12.2025', '', 'DOMENESHOP.NO', 'Varekjøp', '', '9230 34 28978', 'DRIFTSKONTO', '', '', '', '-378', 'NOK', 'Reservert', '8833', '', 'DOMENESHOP.NO'],
            ['05.12.2025', '05.12.2025', '05.12.2025', 'AWS EMEA', 'Varekjøp', '', '9230 34 28978', 'DRIFTSKONTO', '', '', '', '-12.44', 'NOK', 'Utført', '7722', '', 'AWS EMEA'],
            ['02.12.2025', '02.12.2025', '02.12.2025', 'DEEPOCEAN AS', 'Overføring', '', '9230 34 28978', 'DRIFTSKONTO', '8101 50 88257', 'Datalytic AS', '181183', '', 'NOK', 'Utført', '7650', '', 'Betaling'],
        ]
    );

    // Account number columns (9230 34 28978, 8101 50 88257) should NOT be mapped as balance
    expect($mapping['balance'])->toBeNull();

    // Description should be column 3 (Beskrivelse), not column 16 (Melding)
    expect($mapping['description'])->toBe(3);

    // Dates should be correct
    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['posting_date'])->toBe(1);

    // Amount columns: split credit/debit (columns 10/11 have empty gaps)
    expect($mapping['credit'])->toBe(10);
    expect($mapping['debit'])->toBe(11);
});

it('structural fallback filters out constant-value numeric columns', function () {
    $service = createCsvImportService();
    $mapping = $service->mapColumns(
        ['Date', 'Acct', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-15', '12345678', 'Purchase', '-100.00', '5000.00'],
            ['2026-01-16', '12345678', 'Salary', '3000.00', '8000.00'],
            ['2026-01-17', '12345678', 'Coffee', '-5.50', '7994.50'],
        ]
    );

    // Account number column (constant "12345678") should not be mapped as balance
    expect($mapping['transaction_date'])->toBe(0);
    expect($mapping['description'])->toBe(2);
    // The constant account number (col 1) should be excluded from amount/balance
    expect($mapping['amount'])->not->toBe(1);
    expect($mapping['balance'])->not->toBe(1);
});

// ==========================================
// Import from Content
// ==========================================

it('creates statement and transactions from CSV content', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'fileExtension' => 'csv',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-10', 'Salary', '30000.00', '35000.00'],
            ['2026-01-12', 'Store purchase', '-250.00', '34750.00'],
            ['2026-01-15', 'Subscription', '-149.00', '34601.00'],
        ]
    );

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    expect($statement)->toBeInstanceOf(BankStatement::class);
    expect($statement->user_id)->toBe($user->id);
    expect($statement->file_id)->toBe($file->id);
    expect($statement->bank_name)->toBeNull();
    expect($statement->transaction_count)->toBe(3);
    expect((float) $statement->total_credits)->toBe(30000.00);
    expect((float) $statement->total_debits)->toBe(399.00);
    expect($statement->statement_period_start->format('Y-m-d'))->toBe('2026-01-10');
    expect($statement->statement_period_end->format('Y-m-d'))->toBe('2026-01-15');

    // Verify transactions were created
    expect(BankTransaction::where('bank_statement_id', $statement->id)->count())->toBe(3);

    // Verify extractable entity junction
    expect(ExtractableEntity::where('entity_type', 'bank_statement')
        ->where('entity_id', $statement->id)
        ->exists()
    )->toBeTrue();

    // File status is updated by the job, not the service — verify it stays unchanged
    expect($file->fresh()->status)->toBe('pending');
});

it('correctly identifies credit and debit transactions', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-10', 'Deposit', '1000.00', '6000.00'],
            ['2026-01-12', 'Payment', '-500.00', '5500.00'],
        ]
    );

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    $transactions = BankTransaction::where('bank_statement_id', $statement->id)
        ->orderBy('transaction_date')
        ->get();

    expect($transactions[0]->transaction_type)->toBe('credit');
    expect((float) $transactions[0]->amount)->toBe(1000.00);
    expect($transactions[1]->transaction_type)->toBe('debit');
    expect((float) $transactions[1]->amount)->toBe(-500.00);
});

it('handles European number format', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = "Dato,Forklaring,Beløp,Saldo\n"
        .'15.01.2026,Dagligvarer,"-1.250,50","5.000,00"';

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    $tx = BankTransaction::where('bank_statement_id', $statement->id)->first();

    expect((float) $tx->amount)->toBe(-1250.50);
    expect((float) $tx->balance_after)->toBe(5000.00);
});

it('handles debit/credit split columns via AI mapping', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Debit', 'Credit', 'Balance'],
        [
            ['2026-01-10', 'Salary', '', '30000.00', '35000.00'],
            ['2026-01-12', 'Groceries', '250.00', '', '34750.00'],
        ]
    );

    // Debit/credit split requires AI to correctly identify which column is which
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->andReturn([
            'transaction_date' => 0,
            'description' => 1,
            'debit' => 2,
            'credit' => 3,
            'balance' => 4,
            'posting_date' => null,
            'amount' => null,
            'reference' => null,
            'counterparty' => null,
            'type' => null,
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    $transactions = BankTransaction::where('bank_statement_id', $statement->id)
        ->orderBy('transaction_date')
        ->get();

    expect($transactions[0]->transaction_type)->toBe('credit');
    expect((float) $transactions[0]->amount)->toBe(30000.00);
    expect($transactions[1]->transaction_type)->toBe('debit');
    expect((float) $transactions[1]->amount)->toBe(-250.00);
});

it('throws exception for empty CSV content', function () {
    $service = createCsvImportService();
    $service->importFromContent('', 1, 1);
})->throws(Exception::class, 'CSV content is empty.');

it('throws exception for CSV with only headers', function () {
    $service = createCsvImportService();
    $service->importFromContent("Date,Description,Amount\n", 1, 1);
})->throws(Exception::class, 'CSV file contains no data rows.');

it('skips rows without description', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-10', 'Valid transaction', '-100.00', '5000.00'],
            ['2026-01-11', '', '-50.00', '4950.00'],
        ]
    );

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    expect($statement->transaction_count)->toBe(1);
});

it('parses various date formats', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount'],
        [
            ['2026-01-15', 'ISO format', '-100.00'],
            ['15.01.2026', 'European format', '-200.00'],
        ]
    );

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    $transactions = BankTransaction::where('bank_statement_id', $statement->id)
        ->orderBy('amount', 'desc')
        ->get();

    expect($transactions[0]->transaction_date->format('Y-m-d'))->toBe('2026-01-15');
    expect($transactions[1]->transaction_date->format('Y-m-d'))->toBe('2026-01-15');
});

// ==========================================
// Real-world CSV Format (AI-driven)
// ==========================================

it('parses semicolon-separated CSV with split amounts via AI mapping', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
        'fileExtension' => 'csv',
    ]);

    $csv = "Utført dato;Bokført dato;Beskrivelse;Beløp inn;Beløp ut;Valuta;Mottakernavn\n"
        ."05.01.2026;05.01.2026;Gebyr Nettbank;;-32;NOK;NETTBEDR GEBYR\n"
        ."02.01.2026;02.01.2026;AWS EMEA;;-516.48;NOK;\n"
        ."02.01.2026;02.01.2026;DEEPOCEAN AS;181183;;NOK;Datalytic AS\n";

    // AI provides the correct column mapping for this bank format
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->andReturn([
            'transaction_date' => 0,
            'posting_date' => 1,
            'description' => 2,
            'credit' => 3,
            'debit' => 4,
            'amount' => null,
            'balance' => null,
            'reference' => null,
            'counterparty' => 6,
            'type' => null,
        ]);
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    expect($statement->transaction_count)->toBe(3);

    $transactions = BankTransaction::where('bank_statement_id', $statement->id)
        ->orderBy('amount', 'desc')
        ->get();

    // Credit transaction
    expect((float) $transactions[0]->amount)->toBe(181183.0);
    expect($transactions[0]->transaction_type)->toBe('credit');
    expect($transactions[0]->counterparty_name)->toBe('Datalytic AS');

    // Debit transactions
    expect((float) $transactions[1]->amount)->toBe(-32.0);
    expect($transactions[1]->transaction_type)->toBe('debit');

    expect((float) $transactions[2]->amount)->toBe(-516.48);
});

// ==========================================
// Delimiter Detection
// ==========================================

it('detects semicolon delimiter', function () {
    $csv = "Utført dato;Bokført dato;Beskrivelse;Beløp inn;Beløp ut\n"
        ."05.01.2026;05.01.2026;Omkostninger;;-32\n";

    $service = createCsvImportService();
    expect($service->detectDelimiter($csv))->toBe(';');
});

it('detects comma delimiter', function () {
    $csv = "Date,Description,Amount,Balance\n"
        ."2026-01-15,Test,-100.00,5000.00\n";

    $service = createCsvImportService();
    expect($service->detectDelimiter($csv))->toBe(',');
});

it('detects tab delimiter', function () {
    $csv = "Date\tDescription\tAmount\n"
        ."2026-01-15\tTest\t-100.00\n";

    $service = createCsvImportService();
    expect($service->detectDelimiter($csv))->toBe("\t");
});

// ==========================================
// Encoding Handling
// ==========================================

it('handles ISO-8859-1 encoded CSV content', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    // Build CSV with ISO-8859-1 encoding (Norwegian characters ø, å)
    $utf8Csv = "Date,Description,Amount,Balance\n"
        ."15.01.2026,Kjøp på butikk,-250,5000\n";

    // Convert to ISO-8859-1 to simulate real bank export
    $iso8859Csv = mb_convert_encoding($utf8Csv, 'ISO-8859-1', 'UTF-8');

    $service = createCsvImportService();
    $statement = $service->importFromContent($iso8859Csv, $user->id, $file->id);

    expect($statement)->toBeInstanceOf(BankStatement::class);
    expect($statement->transaction_count)->toBe(1);

    $tx = BankTransaction::where('bank_statement_id', $statement->id)->first();
    expect($tx->description)->toBe('Kjøp på butikk');
    expect((float) $tx->amount)->toBe(-250.0);
});

// ==========================================
// Date Validation & Footer Filtering
// ==========================================

it('skips rows without valid dates (footer/summary rows)', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    // CSV with transaction rows followed by footer/summary rows
    $csv = "Date;Description;Amount;Balance\n"
        ."15.01.2026;Grocery store;-250.00;5000.00\n"
        ."16.01.2026;Salary;30000.00;35000.00\n"
        .";;;\n"
        ."Opening balance: 5250.00;;;\n"
        ."Closing balance: 35000.00;;;\n";

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    // Only 2 real transaction rows — footer/summary rows filtered out
    expect($statement->transaction_count)->toBe(2);
});

it('skips separator-only rows in CSV', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = "Date,Description,Amount\n"
        ."2026-01-15,Purchase,-100.00\n"
        .",,,\n"
        ."2026-01-16,Refund,50.00\n";

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    expect($statement->transaction_count)->toBe(2);
});

// ==========================================
// Footer Enrichment
// ==========================================

it('enriches statement with footer data from AI', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = "Date,Description,Amount\n"
        ."2026-01-15,Purchase,-100.00\n"
        ."2026-01-16,Salary,3000.00\n"
        ."\n"
        ."Opening balance: 5000.00\n"
        ."Closing balance: 7900.00\n"
        ."Account: 1234 56 78901\n";

    $callCount = 0;
    $ai = Mockery::mock(TextAnalysisContract::class);
    $ai->shouldReceive('analyze')
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // First call: column mapping — fail to use structural fallback
                throw new Exception('AI unavailable');
            }

            // Second call: footer extraction
            return [
                'opening_balance' => 5000.00,
                'closing_balance' => 7900.00,
                'account_number' => '1234 56 78901',
                'bank_name' => null,
            ];
        });
    $ai->shouldReceive('getProviderName')->andReturn('mock');

    $service = new CsvImportService($ai);
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    expect((float) $statement->opening_balance)->toBe(5000.00);
    expect((float) $statement->closing_balance)->toBe(7900.00);
    expect($statement->account_number)->toBe('1234 56 78901');
});

it('keeps derived balances when footer extraction fails', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-10', 'Salary', '30000.00', '35000.00'],
            ['2026-01-12', 'Purchase', '-250.00', '34750.00'],
        ]
    );

    // AI fails for all calls — structural fallback for mapping, no footer data
    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    // Opening balance = first balance_after - first amount = 35000 - 30000 = 5000
    expect((float) $statement->opening_balance)->toBe(5000.00);
    // Closing balance = last transaction's balance_after
    expect((float) $statement->closing_balance)->toBe(34750.00);
});

it('correctly derives opening balance from first transaction', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'file_type' => 'document',
    ]);

    $csv = buildCsv(
        ['Date', 'Description', 'Amount', 'Balance'],
        [
            ['2026-01-10', 'Store purchase', '-250.00', '4750.00'],
            ['2026-01-12', 'Coffee', '-50.00', '4700.00'],
        ]
    );

    $service = createCsvImportService();
    $statement = $service->importFromContent($csv, $user->id, $file->id);

    // Opening = 4750 - (-250) = 5000 (balance before first transaction)
    expect((float) $statement->opening_balance)->toBe(5000.00);
    expect((float) $statement->closing_balance)->toBe(4700.00);
});
