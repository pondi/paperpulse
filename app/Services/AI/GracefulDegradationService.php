<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GracefulDegradationService
{
    protected array $fallbackStrategies = [];

    public function __construct()
    {
        $this->initializeFallbackStrategies();
    }

    /**
     * Provide fallback analysis when AI fails
     */
    public function provideFallbackAnalysis(string $content, string $type, array $options = []): array
    {
        try {
            Log::info('[GracefulDegradationService] Providing fallback analysis', [
                'type' => $type,
                'content_length' => strlen($content),
                'options' => array_keys($options),
            ]);

            $strategy = $this->fallbackStrategies[$type] ?? null;

            if (! $strategy) {
                return $this->getBasicFallback($content, $type);
            }

            return $strategy($content, $options);

        } catch (Exception $e) {
            Log::error('[GracefulDegradationService] Fallback analysis failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $this->getBasicFallback($content, $type);
        }
    }

    /**
     * Initialize fallback strategies for different content types
     */
    protected function initializeFallbackStrategies(): void
    {
        $this->fallbackStrategies = [
            'receipt' => [$this, 'analyzeReceiptFallback'],
            'document' => [$this, 'analyzeDocumentFallback'],
            'merchant' => [$this, 'extractMerchantFallback'],
            'summary' => [$this, 'generateSummaryFallback'],
            'classification' => [$this, 'classifyDocumentFallback'],
            'tags' => [$this, 'suggestTagsFallback'],
            'entities' => [$this, 'extractEntitiesFallback'],
        ];
    }

    /**
     * Fallback receipt analysis using pattern matching
     */
    protected function analyzeReceiptFallback(string $content, array $options = []): array
    {
        $data = [
            'merchant' => $this->extractMerchantBasic($content),
            'totals' => $this->extractTotalsBasic($content),
            'receipt_info' => $this->extractReceiptInfoBasic($content),
            'items' => $this->extractItemsBasic($content),
            'payment' => $this->extractPaymentBasic($content),
        ];

        return [
            'success' => true,
            'data' => $data,
            'provider' => 'fallback',
            'method' => 'pattern_matching',
            'confidence' => 0.6, // Lower confidence for fallback
        ];
    }

    /**
     * Fallback document analysis using basic text analysis
     */
    protected function analyzeDocumentFallback(string $content, array $options = []): array
    {
        $words = explode(' ', $content);
        $sentences = preg_split('/[.!?]+/', $content);
        $lines = explode("\n", $content);

        $data = [
            'title' => $this->extractTitleBasic($content),
            'document_type' => $this->classifyDocumentBasic($content),
            'summary' => $this->generateSummaryBasic($content),
            'entities' => $this->extractEntitiesBasic($content),
            'tags' => $this->generateTagsBasic($content),
            'language' => $this->detectLanguageBasic($content),
            'metadata' => [
                'word_count' => count($words),
                'sentence_count' => count($sentences),
                'line_count' => count($lines),
                'confidence' => 0.5,
            ],
        ];

        return [
            'success' => true,
            'data' => $data,
            'provider' => 'fallback',
            'method' => 'basic_analysis',
        ];
    }

    /**
     * Basic merchant extraction using patterns
     */
    protected function extractMerchantBasic(string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $merchant = [];

        // Try to find merchant name (usually first non-empty line)
        foreach ($lines as $line) {
            if (strlen($line) > 3 && ! preg_match('/^\d+[\.\-\/]/', $line)) {
                $merchant['name'] = $line;
                break;
            }
        }

        // Look for organization number
        if (preg_match('/(?:org\.?nr\.?|organisasjonsnummer)[:\s]*(\d{9})/i', $content, $matches)) {
            $merchant['org_number'] = $matches[1];
        }

        // Look for phone number
        if (preg_match('/(?:tlf\.?|telefon)[:\s]*([+\d\s\-]{8,})/i', $content, $matches)) {
            $merchant['phone'] = trim($matches[1]);
        }

        // Look for address patterns
        if (preg_match('/(\d+[^\n]*(?:gate|vei|plass|sentrum)[^\n]*)/i', $content, $matches)) {
            $merchant['address'] = trim($matches[1]);
        }

        return $merchant;
    }

    /**
     * Basic totals extraction using patterns
     */
    protected function extractTotalsBasic(string $content): array
    {
        $totals = [];

        // Look for total amount patterns
        $patterns = [
            '/(?:totalt?|total|sum|beløp)[:\s]*([0-9]+[,.]?\d*)/i',
            '/(?:å betale|til betaling)[:\s]*([0-9]+[,.]?\d*)/i',
            '/([0-9]+[,.]?\d*)\s*(?:kr|nok)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $amount = str_replace(',', '.', $matches[1]);
                if (is_numeric($amount)) {
                    $totals['total_amount'] = (float) $amount;
                    break;
                }
            }
        }

        // Look for MVA/VAT
        if (preg_match('/(?:mva|vat)[:\s]*([0-9]+[,.]?\d*)/i', $content, $matches)) {
            $vatAmount = str_replace(',', '.', $matches[1]);
            if (is_numeric($vatAmount)) {
                $totals['tax_amount'] = (float) $vatAmount;
            }
        }

        return $totals;
    }

    /**
     * Basic receipt info extraction
     */
    protected function extractReceiptInfoBasic(string $content): array
    {
        $info = [];

        // Look for date patterns
        $datePatterns = [
            '/(\d{1,2}[\.\-\/]\d{1,2}[\.\-\/]\d{4})/',
            '/(\d{4}[\.\-\/]\d{1,2}[\.\-\/]\d{1,2})/',
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $info['date'] = $matches[1];
                break;
            }
        }

        // Look for time patterns
        if (preg_match('/(\d{1,2}[:\.\-]\d{2})/', $content, $matches)) {
            $info['time'] = $matches[1];
        }

        // Look for receipt number
        if (preg_match('/(?:kvittering|receipt|bon)[#:\s]*([a-z0-9\-]+)/i', $content, $matches)) {
            $info['receipt_number'] = $matches[1];
        }

        return $info;
    }

    /**
     * Basic items extraction
     */
    protected function extractItemsBasic(string $content): array
    {
        $items = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Look for lines with price patterns
            if (preg_match('/^(.+?)\s+([0-9]+[,.]?\d*)\s*(?:kr|nok)?\s*$/i', trim($line), $matches)) {
                $name = trim($matches[1]);
                $price = str_replace(',', '.', $matches[2]);

                if (strlen($name) > 2 && is_numeric($price)) {
                    $items[] = [
                        'name' => $name,
                        'quantity' => 1,
                        'total_price' => (float) $price,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Basic payment method extraction
     */
    protected function extractPaymentBasic(string $content): array
    {
        $payment = [];

        $paymentMethods = [
            'kontant' => 'cash',
            'kort' => 'card',
            'visa' => 'card',
            'mastercard' => 'card',
            'vipps' => 'mobile',
            'bankaxept' => 'card',
        ];

        foreach ($paymentMethods as $norwegian => $english) {
            if (stripos($content, $norwegian) !== false) {
                $payment['method'] = $english;
                break;
            }
        }

        // Look for currency
        if (preg_match('/\b(nok|kr|eur|usd)\b/i', $content, $matches)) {
            $payment['currency'] = strtoupper($matches[1]);
        } else {
            $payment['currency'] = 'NOK'; // Default for Norwegian receipts
        }

        return $payment;
    }

    /**
     * Basic title extraction from document
     */
    protected function extractTitleBasic(string $content): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        if (empty($lines)) {
            return 'Untitled Document';
        }

        // Use first substantive line as title
        foreach ($lines as $line) {
            if (strlen($line) > 5 && strlen($line) < 100) {
                return $line;
            }
        }

        // Fallback: use first 50 characters
        return Str::limit($content, 50);
    }

    /**
     * Basic document classification using keywords
     */
    protected function classifyDocumentBasic(string $content): string
    {
        $content = strtolower($content);

        $classifications = [
            'invoice' => ['invoice', 'faktura', 'bill', 'regning'],
            'contract' => ['contract', 'kontrakt', 'agreement', 'avtale'],
            'report' => ['report', 'rapport', 'analysis', 'analyse'],
            'letter' => ['letter', 'brev', 'correspondence'],
            'email' => ['email', 'e-post', 'subject:', 'from:', 'to:'],
            'legal' => ['legal', 'juridisk', 'law', 'lov', 'court'],
            'financial' => ['financial', 'økonomi', 'budget', 'accounting'],
            'technical' => ['technical', 'teknisk', 'specification', 'manual'],
        ];

        foreach ($classifications as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($content, $keyword)) {
                    return $type;
                }
            }
        }

        return 'other';
    }

    /**
     * Basic summary generation
     */
    protected function generateSummaryBasic(string $content, int $maxLength = 200): string
    {
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', trim($content));

        // Take first few sentences or first paragraph
        $sentences = preg_split('/[.!?]+/', $content);
        $summary = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 10) {
                if (strlen($summary.$sentence) < $maxLength - 10) {
                    $summary .= $sentence.'. ';
                } else {
                    break;
                }
            }
        }

        return trim($summary) ?: Str::limit($content, $maxLength);
    }

    /**
     * Basic tag generation using keyword extraction
     */
    protected function generateTagsBasic(string $content, int $maxTags = 5): array
    {
        $content = strtolower($content);
        $words = preg_split('/\W+/', $content);

        // Filter out common words
        $stopWords = ['and', 'or', 'but', 'the', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords);
        });

        // Count word frequency
        $wordCounts = array_count_values($words);
        arsort($wordCounts);

        // Return most frequent words as tags
        return array_slice(array_keys($wordCounts), 0, $maxTags);
    }

    /**
     * Basic entity extraction using patterns
     */
    protected function extractEntitiesBasic(string $content): array
    {
        $entities = [
            'people' => [],
            'organizations' => [],
            'locations' => [],
            'dates' => [],
            'amounts' => [],
        ];

        // Extract dates
        $datePattern = '/\b\d{1,2}[\.\-\/]\d{1,2}[\.\-\/]\d{2,4}\b/';
        if (preg_match_all($datePattern, $content, $matches)) {
            $entities['dates'] = array_unique($matches[0]);
        }

        // Extract amounts
        $amountPattern = '/\b\d+[,.]?\d*\s*(?:kr|nok|eur|usd)\b/i';
        if (preg_match_all($amountPattern, $content, $matches)) {
            $entities['amounts'] = array_unique($matches[0]);
        }

        // Extract potential organization names (capitalized words)
        $orgPattern = '/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s+(?:AS|ASA|SA|AB|AB|INC|LLC|LTD|CORP))\b/';
        if (preg_match_all($orgPattern, $content, $matches)) {
            $entities['organizations'] = array_unique($matches[0]);
        }

        // Extract potential person names (Title Case)
        $namePattern = '/\b[A-Z][a-z]+\s+[A-Z][a-z]+\b/';
        if (preg_match_all($namePattern, $content, $matches)) {
            $entities['people'] = array_unique($matches[0]);
        }

        return $entities;
    }

    /**
     * Basic language detection
     */
    protected function detectLanguageBasic(string $content): string
    {
        $content = strtolower($content);

        // Norwegian indicators
        $norwegianWords = ['og', 'er', 'til', 'på', 'med', 'av', 'for', 'ikke', 'det', 'som', 'kr', 'nok', 'mva'];
        $norwegianScore = 0;

        foreach ($norwegianWords as $word) {
            $norwegianScore += substr_count($content, $word);
        }

        // English indicators
        $englishWords = ['and', 'the', 'is', 'to', 'on', 'with', 'of', 'for', 'not', 'that', 'usd', 'eur'];
        $englishScore = 0;

        foreach ($englishWords as $word) {
            $englishScore += substr_count($content, $word);
        }

        if ($norwegianScore > $englishScore) {
            return 'no';
        } elseif ($englishScore > 0) {
            return 'en';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get basic fallback when no specific strategy exists
     */
    protected function getBasicFallback(string $content, string $type): array
    {
        return [
            'success' => true,
            'data' => [
                'content' => Str::limit($content, 500),
                'type' => $type,
                'fallback_reason' => 'No AI providers available',
                'extraction_method' => 'basic_fallback',
            ],
            'provider' => 'fallback',
            'method' => 'basic',
            'confidence' => 0.3,
        ];
    }
}
