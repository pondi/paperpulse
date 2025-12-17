<?php

namespace Tests\Unit;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Contracts\Services\ReceiptValidatorContract;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\DocumentAnalysisService;
use App\Services\ReceiptAnalysisService;
use InvalidArgumentException;
use Mockery;
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

    public function test_ai_factory_throws_exception_for_invalid_provider()
    {
        config(['ai.provider' => 'invalid']);

        $this->expectException(InvalidArgumentException::class);

        AIServiceFactory::create();
    }

    public function test_receipt_analysis_service_creation()
    {
        $service = new ReceiptAnalysisService(
            Mockery::mock(ReceiptParserContract::class),
            Mockery::mock(ReceiptValidatorContract::class),
            Mockery::mock(ReceiptEnricherContract::class)
        );

        $this->assertNotNull($service);
    }

    public function test_document_analysis_service_creation()
    {
        $service = new DocumentAnalysisService(Mockery::mock(AIService::class));

        $this->assertNotNull($service);
    }

    public function test_document_summary_generation()
    {
        $content = 'This is a test document with some content that needs to be summarized. It contains multiple sentences and paragraphs that should be condensed into a brief summary.';

        // Mock the AI service response
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('generateSummary')
            ->once()
            ->with($content, 100)
            ->andReturn('Test document summary');

        $service = new DocumentAnalysisService($aiService);
        $summary = $service->generateSummary($content, 100);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    public function test_document_tag_suggestion()
    {
        $content = 'This is a legal contract between Company A and Company B regarding software licensing.';

        // Mock the AI service response
        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('suggestTags')
            ->once()
            ->with($content, 5)
            ->andReturn(['legal', 'contract', 'software', 'licensing']);

        $service = new DocumentAnalysisService($aiService);
        $tags = $service->suggestTags($content, 5);

        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
        $this->assertLessThanOrEqual(5, count($tags));
    }
}
