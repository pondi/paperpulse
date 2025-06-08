<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIService;
use Anthropic\Client;
use Anthropic\Factory;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AIService
{
    private Client $client;

    public function __construct()
    {
        $this->client = Factory::new()
            ->withApiKey(config('services.anthropic.api_key'))
            ->make();
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        try {
            $prompt = "Du er en ekspert på å analysere kvitteringer fra Norge. Analyser følgende kvitteringstekst og returner strukturert data i JSON format.

Kvittering:
{$content}

Returner JSON med følgende struktur:
{
  \"merchant\": {
    \"name\": \"string\",
    \"address\": \"string\",
    \"org_number\": \"string\",
    \"phone\": \"string\"
  },
  \"items\": [
    {
      \"name\": \"string\",
      \"quantity\": number,
      \"price\": number,
      \"total\": number
    }
  ],
  \"totals\": {
    \"subtotal\": number,
    \"tax\": number,
    \"total\": number
  },
  \"date\": \"string\",
  \"time\": \"string\",
  \"receipt_number\": \"string\",
  \"payment_method\": \"string\"
}";

            $response = $this->client->messages()->create([
                'model' => config('ai.models.anthropic_receipt', 'claude-3-haiku-20240307'),
                'max_tokens' => 1024,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            
            // Extract JSON from response
            preg_match('/\{.*\}/s', $content, $matches);
            $result = json_decode($matches[0] ?? '{}', true);

            return [
                'success' => true,
                'data' => $result,
                'provider' => 'anthropic',
                'model' => config('ai.models.anthropic_receipt', 'claude-3-haiku-20240307')
            ];
        } catch (\Exception $e) {
            Log::error('Anthropic receipt analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'anthropic'
            ];
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            $prompt = "Analyze the following document and extract structured metadata. Return JSON format.

Document:
" . substr($content, 0, 4000) . "

Return JSON with this structure:
{
  \"title\": \"string\",
  \"document_type\": \"string\",
  \"summary\": \"string\",
  \"entities\": {
    \"people\": [\"string\"],
    \"organizations\": [\"string\"],
    \"locations\": [\"string\"],
    \"dates\": [\"string\"],
    \"amounts\": [\"string\"]
  },
  \"tags\": [\"string\"],
  \"language\": \"string\",
  \"key_phrases\": [\"string\"]
}";

            $response = $this->client->messages()->create([
                'model' => config('ai.models.anthropic_document', 'claude-3-sonnet-20240229'),
                'max_tokens' => 2048,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            
            // Extract JSON from response
            preg_match('/\{.*\}/s', $content, $matches);
            $result = json_decode($matches[0] ?? '{}', true);

            return [
                'success' => true,
                'data' => $result,
                'provider' => 'anthropic',
                'model' => config('ai.models.anthropic_document', 'claude-3-sonnet-20240229')
            ];
        } catch (\Exception $e) {
            Log::error('Anthropic document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'anthropic'
            ];
        }
    }

    public function extractMerchant(string $content): array
    {
        try {
            $prompt = "Extract only the merchant name and details from this receipt text. Return JSON with name, address, org_number, and phone fields:\n\n{$content}";

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 200,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            preg_match('/\{.*\}/s', $content, $matches);
            
            return json_decode($matches[0] ?? '{}', true) ?: [];
        } catch (\Exception $e) {
            Log::error('Merchant extraction failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function extractLineItems(string $content): array
    {
        try {
            $prompt = "Extract line items from this receipt. Return a JSON array of objects with name, quantity, price, and total fields:\n\n{$content}";

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 500,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            preg_match('/\[.*\]/s', $content, $matches);
            
            return json_decode($matches[0] ?? '[]', true) ?: [];
        } catch (\Exception $e) {
            Log::error('Line items extraction failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        try {
            $prompt = "Summarize this document in maximum {$maxLength} characters:\n\n" . substr($content, 0, 3000);

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => (int)($maxLength / 3),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            return trim($response->content[0]->text);
        } catch (\Exception $e) {
            Log::error('Summary generation failed', ['error' => $e->getMessage()]);
            return 'Summary generation failed';
        }
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        try {
            $prompt = "Extract {$maxTags} relevant tags from this document. Return only a JSON array of tag strings:\n\n" . substr($content, 0, 2000);

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 100,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            preg_match('/\[.*\]/s', $content, $matches);
            
            $tags = json_decode($matches[0] ?? '[]', true) ?: [];
            return array_slice($tags, 0, $maxTags);
        } catch (\Exception $e) {
            Log::error('Tag suggestion failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function classifyDocumentType(string $content): string
    {
        try {
            $types = [
                'invoice', 'contract', 'report', 'letter', 'memo',
                'presentation', 'spreadsheet', 'email', 'legal',
                'financial', 'technical', 'other'
            ];

            $prompt = "Classify this document type. Return only one of these types: " . implode(', ', $types) . "\n\n" . substr($content, 0, 1500);

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $type = strtolower(trim($response->content[0]->text));
            return in_array($type, $types) ? $type : 'other';
        } catch (\Exception $e) {
            Log::error('Document classification failed', ['error' => $e->getMessage()]);
            return 'other';
        }
    }

    public function extractEntities(string $content, array $types = []): array
    {
        $defaultTypes = ['people', 'organizations', 'locations', 'dates', 'amounts'];
        $types = empty($types) ? $defaultTypes : array_intersect($types, $defaultTypes);

        try {
            $prompt = "Extract these entity types from the text: " . implode(', ', $types) . ". Return JSON with these keys.\n\n" . substr($content, 0, 2000);

            $response = $this->client->messages()->create([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 300,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            $content = $response->content[0]->text;
            preg_match('/\{.*\}/s', $content, $matches);
            
            $result = json_decode($matches[0] ?? '{}', true) ?: [];
            return array_intersect_key($result, array_flip($types));
        } catch (\Exception $e) {
            Log::error('Entity extraction failed', ['error' => $e->getMessage()]);
            return array_fill_keys($types, []);
        }
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }
}