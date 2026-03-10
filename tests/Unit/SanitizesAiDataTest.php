<?php

declare(strict_types=1);

use App\Services\Factories\Concerns\SanitizesAiData;

beforeEach(function () {
    $this->sanitizer = new class
    {
        use SanitizesAiData;

        public function testNullIfEmpty(mixed $value): mixed
        {
            return $this->nullIfEmpty($value);
        }

        /** @param  list<string>  $dateFields */
        public function testSanitizeDates(array $data, array $dateFields): array
        {
            return $this->sanitizeDates($data, $dateFields);
        }
    };
});

it('converts string "null" to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty('null'))->toBeNull();
});

it('converts string "None" to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty('None'))->toBeNull();
});

it('converts string "N/A" to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty('N/A'))->toBeNull();
});

it('converts empty string to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty(''))->toBeNull();
});

it('converts string "undefined" to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty('undefined'))->toBeNull();
});

it('converts string "nil" to actual null', function () {
    expect($this->sanitizer->testNullIfEmpty('nil'))->toBeNull();
});

it('preserves actual null', function () {
    expect($this->sanitizer->testNullIfEmpty(null))->toBeNull();
});

it('preserves valid date strings', function () {
    expect($this->sanitizer->testNullIfEmpty('2024-01-15'))->toBe('2024-01-15');
});

it('preserves non-string values', function () {
    expect($this->sanitizer->testNullIfEmpty(42))->toBe(42);
    expect($this->sanitizer->testNullIfEmpty(true))->toBeTrue();
});

it('handles whitespace-padded stringified nulls', function () {
    expect($this->sanitizer->testNullIfEmpty('  null  '))->toBeNull();
    expect($this->sanitizer->testNullIfEmpty(' N/A '))->toBeNull();
});

it('sanitizes only specified date fields in an array', function () {
    $data = [
        'title' => 'null',
        'document_date' => 'null',
        'expiry_date' => 'None',
        'description' => 'A valid description',
    ];

    $result = $this->sanitizer->testSanitizeDates($data, ['document_date', 'expiry_date']);

    expect($result['title'])->toBe('null')
        ->and($result['document_date'])->toBeNull()
        ->and($result['expiry_date'])->toBeNull()
        ->and($result['description'])->toBe('A valid description');
});

it('leaves missing date fields untouched', function () {
    $data = ['title' => 'Test'];

    $result = $this->sanitizer->testSanitizeDates($data, ['document_date']);

    expect($result)->toBe(['title' => 'Test']);
});
