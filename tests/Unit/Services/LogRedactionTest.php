<?php

use App\Http\Middleware\Api\ApiRequestLogger;
use App\Services\Jobs\RedactLogRecords;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;

it('leaves no document bodies nested credentials or signed URLs in application logs', function () {
    $handler = new TestHandler;
    $logger = new Logger(new MonologLogger('test', [$handler]));
    (new RedactLogRecords)($logger);
    $logger->error('Provider error: private document body API_KEY=secret https://download.test/file?X-Amz-Signature=signed', [
        'file_id' => 12, 'stage' => 'extraction', 'provider' => 'gemini',
        'raw_content' => 'private document body', 'nested' => ['credentials' => ['api_key' => 'secret'], 'download_url' => 'https://download.test/?signature=signed'],
        'exception' => new RuntimeException('private document body'),
    ]);
    $serialized = json_encode($handler->getRecords());
    expect($serialized)->not->toContain('private document body')->not->toContain('secret')->not->toContain('signed')
        ->and($handler->getRecords()[0]->context['file_id'])->toBe(12)
        ->and($handler->getRecords()[0]->context['stage'])->toBe('extraction');
});

it('records request counts without retaining nested payload metadata', function () {
    $middleware = new class extends ApiRequestLogger
    {
        public function payload(Request $request): array
        {
            return $this->sanitizePayload($request);
        }
    };
    $request = Request::create('/api/import', 'POST', ['document' => ['text' => 'private', 'api_key' => 'secret']]);
    expect($middleware->payload($request))->toBe(['field_count' => 1, 'file_count' => 0]);
});

it('bounds diagnostic metadata depth entries and string lengths', function () {
    $context = RedactLogRecords::context(['stage' => str_repeat('x', 1000), 'nested' => ['nested' => ['nested' => ['nested' => ['secret' => 'bad']]]]]);
    expect(strlen($context['stage']))->toBe(120)
        ->and($context['nested']['nested']['nested']['nested'])->toBe([]);
});
