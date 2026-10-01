<?php

use App\Exceptions\AIResponseException;
use App\Exceptions\GeminiApiException;
use App\Services\AI\OpenAI\ResponseParser;
use App\Services\AI\Providers\GeminiResponseParser;
use App\Services\AI\Shared\ResponseShapeValidator;

it('rejects malformed empty scalar and list OpenAI output', function (?string $content) {
    $response = (object) ['choices' => [(object) ['finishReason' => 'stop', 'message' => (object) ['content' => $content]]]];
    expect(fn () => ResponseParser::jsonContent($response))->toThrow(AIResponseException::class);
})->with(['{broken', '', null, '{}', '[]', 'null', '0']);

it('classifies truncated output separately from refusal', function (string $finish, bool $retryable) {
    $response = (object) ['choices' => [(object) ['finishReason' => $finish, 'message' => (object) ['content' => '{"total":0}']]]];
    try {
        ResponseParser::jsonContent($response);
        $this->fail('Expected response failure');
    } catch (AIResponseException $error) {
        expect($error->retryable)->toBe($retryable);
    }
})->with([['length', true], ['content_filter', false]]);

it('rejects Gemini finish states before parsing and does not repair truncated JSON', function () {
    $parser = new GeminiResponseParser;
    expect(fn () => $parser->extractTextResponse(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"total":0}']]]]]]))->toThrow(GeminiApiException::class)
        ->and(fn () => $parser->parseJsonResponse('{"total":0, broken'))->toThrow(GeminiApiException::class)
        ->and(fn () => $parser->parseJsonResponse('{}'))->toThrow(GeminiApiException::class);
});

it('preserves valid zero fields from completed output', function () {
    $response = (object) ['choices' => [(object) ['finishReason' => 'stop', 'message' => (object) ['content' => '{"total":0}']]]];
    expect(ResponseParser::jsonContent($response))->toBe(['total' => 0]);
});

it('rejects missing fields and wrong nested types before normalization', function () {
    $schema = ['type' => 'object', 'required' => ['total'], 'properties' => ['total' => ['type' => 'number']]];
    expect(fn () => ResponseShapeValidator::validate(['other' => 1], $schema))->toThrow(AIResponseException::class)
        ->and(fn () => ResponseShapeValidator::validate(['total' => 'unknown'], $schema))->toThrow(AIResponseException::class);
});
