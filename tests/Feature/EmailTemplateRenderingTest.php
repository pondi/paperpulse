<?php

use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

it('escapes names and metadata while preserving template markup and safe links', function () {
    $template = new EmailTemplate([
        'subject' => 'Shared: {{ document_title }}',
        'body' => '<h1>{{ user_name }}</h1><p>{{ document_title }}</p><a href="{{ document_url }}">Open</a>',
    ]);
    $rendered = $template->render([
        'user_name' => '<img src=x onerror=alert(1)>',
        'document_title' => 'A & B "quoted"',
        'document_url' => 'https://example.com/documents/1?x=1&y=2',
    ]);

    expect($rendered['body'])->toBe('<h1>&lt;img src=x onerror=alert(1)&gt;</h1><p>A &amp; B &quot;quoted&quot;</p><a href="https://example.com/documents/1?x=1&amp;y=2">Open</a>')
        ->and($rendered['subject'])->toBe('Shared: A & B "quoted"');
});

it('allows only explicitly trusted generated fragments and keeps subjects plain', function () {
    $template = new EmailTemplate([
        'subject' => '{{ categories_summary }} {{ user_name }}',
        'body' => '{{ categories_summary }} {{ merchants_summary }} {{ user_name }}',
    ]);
    $rendered = $template->render([
        'categories_summary' => new HtmlString('<strong>'.e('A < B').'</strong>'),
        'merchants_summary' => '<script>alert(1)</script>',
        'user_name' => new HtmlString("<b>Untrusted</b>\r\nName"),
    ]);

    expect($rendered['body'])->toBe("<strong>A &lt; B</strong> &lt;script&gt;alert(1)&lt;/script&gt; &lt;b&gt;Untrusted&lt;/b&gt;\r\nName")
        ->and($rendered['subject'])->toBe('A &lt; B Untrusted Name');
});

it('rejects unsafe URL substitutions', function (string $url) {
    $template = new EmailTemplate(['subject' => 'Open', 'body' => '<a href="{{ destination }}">Open</a>']);

    expect(fn () => $template->render(['destination' => $url]))->toThrow(InvalidArgumentException::class);
})->with([
    'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>',
    '//example.com/path', 'https://example.com/" onclick="alert(1)',
]);

it('validates composed links in their URL context', function () {
    $template = new EmailTemplate(['subject' => 'Open', 'body' => '<a href="{{ app_url }}/documents/{{ id }}">Open</a>']);

    expect($template->render(['app_url' => 'https://example.com', 'id' => 12])['body'])
        ->toBe('<a href="https://example.com/documents/12">Open</a>');
});

it('does not reinterpret placeholders within substituted values', function () {
    $template = new EmailTemplate(['subject' => '{{ user_name }}', 'body' => '{{ user_name }}']);
    $rendered = $template->render(['user_name' => '{{ categories_summary }}', 'categories_summary' => new HtmlString('<b>Trusted</b>')]);

    expect($rendered)->toBe(['subject' => '{{ categories_summary }}', 'body' => '{{ categories_summary }}']);
});

it('uses the same safe rendering in previews and mail and redacts failure variables', function () {
    EmailTemplate::create([
        'key' => 'safe_test', 'name' => 'Safe test', 'is_active' => true,
        'subject' => 'Hello {{ user_name }}', 'body' => '<p>{{ user_name }}</p>',
    ]);
    $variables = ['user_name' => '<script>secret</script>'];
    $preview = app(EmailService::class)->previewTemplate('safe_test', $variables);
    $mail = new TemplatedMail('safe_test', $variables);

    expect($preview['body'])->toBe('<p>&lt;script&gt;secret&lt;/script&gt;</p>')
        ->and($mail->content()->htmlString)->toContain($preview['body'])
        ->and($mail->envelope()->subject)->toBe('Hello secret');

    Log::shouldReceive('error')->once()->with('Email sending failed', [
        'template' => 'safe_test', 'exception' => RuntimeException::class,
    ]);
    $mail->failed(new RuntimeException('secret'));
});
