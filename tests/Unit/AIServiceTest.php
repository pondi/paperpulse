<?php

namespace Tests\Unit;

use App\Services\AI\AIServiceFactory;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\DocumentAnalysisService;
use App\Services\ReceiptAnalysisService;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    public function test_ai_factory_creates_openai_provider()
    {
        config(['ai.provider' => 'openai']);

        $service = AIServiceFactory::create();

        $this->assertInstanceOf(OpenAIProvider::class, $service);
        $this->assertEquals('openai', $service->getProviderName());
    }

    public function test_ai_factory_creates_anthropic_provider()
    {
        config(['ai.provider' => 'anthropic']);

        $service = AIServiceFactory::create();

        $this->assertInstanceOf(AnthropicProvider::class, $service);
        $this->assertEquals('anthropic', $service->getProviderName());
    }

    public function test_ai_factory_throws_exception_for_invalid_provider()
    {
        config(['ai.provider' => 'invalid']);

        $this->expectException(\InvalidArgumentException::class);

        AIServiceFactory::create();
    }

    public function test_ai_factory_with_fallback()
    {
        $service = AIServiceFactory::createWithFallback(['openai', 'anthropic']);

        $this->assertNotNull($service);
        $this->assertContains($service->getProviderName(), ['openai', 'anthropic']);
    }

    public function test_receipt_analysis_service_creation()
    {
        $service = new ReceiptAnalysisService;

        $this->assertNotNull($service);
    }

    public function test_document_analysis_service_creation()
    {
        $service = new DocumentAnalysisService;

        $this->assertNotNull($service);
    }

    public function test_document_summary_generation()
    {
        $service = new DocumentAnalysisService;

        $content = 'This is a test document with some content that needs to be summarized. It contains multiple sentences and paragraphs that should be condensed into a brief summary.';

        // Mock the AI service response
        $this->mock(\App\Services\AI\AIService::class, function ($mock) {
            $mock->shouldReceive('generateSummary')
                ->once()
                ->andReturn('Test document summary');
        });

        $summary = $service->generateSummary($content, 100);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    public function test_document_tag_suggestion()
    {
        $service = new DocumentAnalysisService;

        $content = 'This is a legal contract between Company A and Company B regarding software licensing.';

        // Mock the AI service response
        $this->mock(\App\Services\AI\AIService::class, function ($mock) {
            $mock->shouldReceive('suggestTags')
                ->once()
                ->andReturn(['legal', 'contract', 'software', 'licensing']);
        });

        $tags = $service->suggestTags($content, 5);

        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
        $this->assertLessThanOrEqual(5, count($tags));
    }
}
