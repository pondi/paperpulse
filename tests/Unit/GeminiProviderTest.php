<?php

namespace Tests\Unit;

use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_analyze_file_includes_text_context_for_text_files(): void
    {
        config([
            'ai.providers.gemini.text_max_bytes' => 100,
            'ai.providers.gemini.api_key' => 'test-key',
            'ai.providers.gemini.model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"entities": [{"type": "document", "data": {}}]}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gemini_text_'.uniqid().'.txt';
        file_put_contents($path, 'Plain text content for Gemini processing.');

        $provider = new GeminiProvider;
        $result = $provider->analyzeFile($path, [
            'name' => 'document',
            'entities' => ['document'],
        ], 'Analyze this document.');

        $this->assertNotNull($result['text_input']);
        $this->assertSame(false, $result['text_input']['truncated']);
        $this->assertStringContainsString('Plain text content', $result['text_input']['excerpt']);

        @unlink($path);
    }

    public function test_large_pdf_context_includes_sample_pages(): void
    {
        config([
            'ai.providers.gemini.large_pdf_page_limit' => 5,
            'ai.providers.gemini.large_pdf_sample_size' => 2,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'gemini_pdf_');
        file_put_contents($path, 'PDF placeholder');
        $fileSize = filesize($path) ?: 0;

        $provider = new class extends GeminiProvider
        {
            public function exposedBuildLargeFileContext(string $filePath, string $mime, int $fileSize, ?array $textContext): ?array
            {
                return $this->buildLargeFileContext($filePath, $mime, $fileSize, $textContext);
            }

            protected function getPdfPageCount(string $filePath): int
            {
                return 12;
            }
        };

        $context = $provider->exposedBuildLargeFileContext($path, 'application/pdf', $fileSize, null);

        $this->assertNotNull($context);
        $this->assertSame('sample_pages', $context['strategy']);
        $this->assertSame(12, $context['page_count']);
        $this->assertSame(5, $context['page_limit']);
        $this->assertSame([1, 2, 11, 12], $context['sample_pages']);

        @unlink($path);
    }
}
