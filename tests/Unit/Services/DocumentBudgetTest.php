<?php

use App\Models\Document;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Shared\DocumentContentBudget;
use App\Services\DocumentAnalysisService;
use OpenAI\Laravel\Facades\OpenAI;

it('keeps the final clause in a bounded multibyte provider prompt through document analysis', function () {
    config(['ai.document_analysis.context_tokens' => 16000, 'ai.document_analysis.max_chars' => 5000]);
    $chat = Mockery::mock();
    $chat->shouldReceive('create')->once()->andReturnUsing(function (array $payload): object {
        $prompt = implode(' ', array_column($payload['messages'], 'content'));
        expect($prompt)->toContain('FINAL CLAUSE: refund permitted')
            ->and(mb_check_encoding($prompt, 'UTF-8'))->toBeTrue()
            ->and(strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + $payload['max_completion_tokens'])->toBeLessThanOrEqual(16000);

        return (object) ['choices' => [(object) ['finishReason' => 'stop', 'message' => (object) ['content' => '{"title":"Contract","document_type":"contract"}']]], 'usage' => (object) ['totalTokens' => 100]];
    });
    $client = Mockery::mock();
    $client->shouldReceive('chat')->once()->andReturn($chat);
    OpenAI::swap($client);
    $document = new Document(['content' => 'Opening terms '.str_repeat('å', 10000).' FINAL CLAUSE: refund permitted']);
    $result = (new DocumentAnalysisService(new OpenAIProvider))->analyze($document);
    expect($result['title'])->toBe('Contract');
});

it('records truncation coverage without content and reserves the omission marker', function () {
    $selected = DocumentContentBudget::select(str_repeat('é', 1000).' Tail', 128);
    expect(strlen($selected['content']))->toBeLessThanOrEqual(128)
        ->and($selected['content'])->toEndWith(' Tail')
        ->and($selected['coverage']['truncated'])->toBeTrue()
        ->and(array_keys($selected['coverage']))->toBe(['total_bytes', 'selected_bytes', 'truncated']);
});
