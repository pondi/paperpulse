<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIService
{
    private array $receiptPrompt = [
        'role' => 'system',
        'content' => 'Du er en ekspert på å analysere kvitteringer fra Norge. Trekk ut strukturert informasjon fra kvitteringsteksten og returner JSON-formaterte data. Fokuser på nøyaktighet og fullstendighet.'
    ];

    private array $documentPrompt = [
        'role' => 'system',
        'content' => 'You are an expert at analyzing documents. Extract structured information from the document text and return JSON-formatted data. Focus on accuracy and completeness.'
    ];

    public function analyzeReceipt(string $content, array $options = []): array
    {
        try {
            $messages = [
                $this->receiptPrompt,
                [
                    'role' => 'user',
                    'content' => "Analyser denne kvitteringen og returner strukturert data i JSON format:\n\n" . $content
                ]
            ];

            $response = OpenAI::chat()->create([
                'model' => config('ai.models.receipt', 'gpt-3.5-turbo'),
                'messages' => $messages,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'functions' => [[
                    'name' => 'extract_receipt_data',
                    'description' => 'Extract structured data from receipt',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'merchant' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'address' => ['type' => 'string'],
                                    'org_number' => ['type' => 'string'],
                                    'phone' => ['type' => 'string']
                                ]
                            ],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'quantity' => ['type' => 'number'],
                                        'price' => ['type' => 'number'],
                                        'total' => ['type' => 'number']
                                    ]
                                ]
                            ],
                            'totals' => [
                                'type' => 'object',
                                'properties' => [
                                    'subtotal' => ['type' => 'number'],
                                    'tax' => ['type' => 'number'],
                                    'total' => ['type' => 'number']
                                ]
                            ],
                            'date' => ['type' => 'string'],
                            'time' => ['type' => 'string'],
                            'receipt_number' => ['type' => 'string'],
                            'payment_method' => ['type' => 'string']
                        ]
                    ]
                ]],
                'function_call' => ['name' => 'extract_receipt_data']
            ]);

            $result = json_decode($response->choices[0]->message->functionCall->arguments, true);
            
            return [
                'success' => true,
                'data' => $result,
                'provider' => 'openai',
                'model' => config('ai.models.receipt', 'gpt-3.5-turbo')
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI receipt analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'openai'
            ];
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            $messages = [
                $this->documentPrompt,
                [
                    'role' => 'user',
                    'content' => "Analyze this document and extract structured metadata:\n\n" . substr($content, 0, 4000)
                ]
            ];

            $response = OpenAI::chat()->create([
                'model' => config('ai.models.document', 'gpt-4'),
                'messages' => $messages,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'functions' => [[
                    'name' => 'extract_document_data',
                    'description' => 'Extract structured metadata from document',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'document_type' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'entities' => [
                                'type' => 'object',
                                'properties' => [
                                    'people' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'organizations' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'locations' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'dates' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'amounts' => ['type' => 'array', 'items' => ['type' => 'string']]
                                ]
                            ],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'language' => ['type' => 'string'],
                            'key_phrases' => ['type' => 'array', 'items' => ['type' => 'string']]
                        ]
                    ]
                ]],
                'function_call' => ['name' => 'extract_document_data']
            ]);

            $result = json_decode($response->choices[0]->message->functionCall->arguments, true);

            return [
                'success' => true,
                'data' => $result,
                'provider' => 'openai',
                'model' => config('ai.models.document', 'gpt-4')
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI document analysis failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'openai'
            ];
        }
    }

    public function extractMerchant(string $content): array
    {
        try {
            $response = OpenAI::completions()->create([
                'model' => 'gpt-3.5-turbo-instruct',
                'prompt' => "Extract merchant name and details from this receipt text. Return only JSON:\n\n" . $content,
                'max_tokens' => 150,
                'temperature' => 0.1
            ]);

            $result = json_decode(trim($response->choices[0]->text), true);
            return $result ?? [];
        } catch (\Exception $e) {
            Log::error('Merchant extraction failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function extractLineItems(string $content): array
    {
        try {
            $response = OpenAI::completions()->create([
                'model' => 'gpt-3.5-turbo-instruct',
                'prompt' => "Extract line items from this receipt. Return JSON array:\n\n" . $content,
                'max_tokens' => 500,
                'temperature' => 0.1
            ]);

            $result = json_decode(trim($response->choices[0]->text), true);
            return $result ?? [];
        } catch (\Exception $e) {
            Log::error('Line items extraction failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Summarize documents concisely. Maximum {$maxLength} characters."
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 3000)
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => (int)($maxLength / 4)
            ]);

            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Log::error('Summary generation failed', ['error' => $e->getMessage()]);
            return 'Summary generation failed';
        }
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Extract relevant tags from documents. Return only a JSON array of strings. Maximum {$maxTags} tags."
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 2000)
                    ]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $result = json_decode($response->choices[0]->message->content, true);
            return array_slice($result['tags'] ?? [], 0, $maxTags);
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

            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Classify document type. Return one of: ' . implode(', ', $types)
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 1500)
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 10
            ]);

            $type = strtolower(trim($response->choices[0]->message->content));
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
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extract entities from text. Return JSON with keys: ' . implode(', ', $types)
                    ],
                    [
                        'role' => 'user',
                        'content' => substr($content, 0, 2000)
                    ]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $result = json_decode($response->choices[0]->message->content, true);
            return array_intersect_key($result, array_flip($types));
        } catch (\Exception $e) {
            Log::error('Entity extraction failed', ['error' => $e->getMessage()]);
            return array_fill_keys($types, []);
        }
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}