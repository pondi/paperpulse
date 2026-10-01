<?php

use App\Services\AI\Providers\OpenAIProvider;
use OpenAI\Laravel\Facades\OpenAI;

it('validates tag suggestions against their requested string array schema', function (array $response, array $expected): void {
    $chat = Mockery::mock();
    $chat->shouldReceive('create')->once()->withArgs(function (array $payload): bool {
        expect($payload['response_format']['json_schema']['schema']['properties']['tags']['items']['type'])->toBe('string');

        return true;
    })->andReturn((object) ['choices' => [(object) ['finishReason' => 'stop', 'message' => (object) ['content' => json_encode($response)]]]]);
    $client = Mockery::mock();
    $client->shouldReceive('chat')->once()->andReturn($chat);
    OpenAI::swap($client);

    expect((new OpenAIProvider)->suggestTags('Document content'))->toBe($expected);
})->with([
    'valid tags' => [['tags' => ['invoice', 'travel']], ['invoice', 'travel']],
    'wrong tag list type' => [['tags' => 'invoice'], []],
    'wrong tag item type' => [['tags' => [42]], []],
    'missing tags' => [['unrelated' => 'invoice'], []],
]);

it('validates selected entity lists before returning provider output', function (array $response, array $expected): void {
    $chat = Mockery::mock();
    $chat->shouldReceive('create')->once()->withArgs(function (array $payload): bool {
        expect($payload['messages'][0]['content'])->toContain('people, organizations');

        return true;
    })->andReturn((object) ['choices' => [(object) ['finishReason' => 'stop', 'message' => (object) ['content' => json_encode($response)]]]]);
    $client = Mockery::mock();
    $client->shouldReceive('chat')->once()->andReturn($chat);
    OpenAI::swap($client);

    expect((new OpenAIProvider)->extractEntities('Document content', ['people', 'organizations']))->toBe($expected);
})->with([
    'valid selected lists' => [['people' => ['Ada'], 'organizations' => ['Example'], 'dates' => ['2026-01-01']], ['people' => ['Ada'], 'organizations' => ['Example']]],
    'valid empty selected lists' => [['people' => [], 'organizations' => []], ['people' => [], 'organizations' => []]],
    'wrong list type' => [['people' => 'Ada', 'organizations' => []], ['people' => [], 'organizations' => []]],
    'object instead of list' => [['people' => ['name' => 'Ada'], 'organizations' => []], ['people' => [], 'organizations' => []]],
    'missing selected key' => [['people' => ['Ada']], ['people' => [], 'organizations' => []]],
]);
