<?php

declare(strict_types=1);

use App\Models\File;
use App\Services\Factories\BaseEntityFactory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->file = new File;
    $this->file->forceFill(['id' => 1, 'user_id' => 42]);
});

it('injects file_id and user_id into attributes', function () {
    $factory = createTestFactory(
        fields: ['title'],
    );

    $attributes = $factory->exposedResolveAttributes(['title' => 'Test'], $this->file);

    expect($attributes['file_id'])->toBe(1)
        ->and($attributes['user_id'])->toBe(42)
        ->and($attributes['title'])->toBe('Test');
});

it('applies defaults for missing fields', function () {
    $factory = createTestFactory(
        fields: ['currency', 'title'],
        defaults: ['currency' => 'NOK'],
    );

    $attributes = $factory->exposedResolveAttributes(['title' => 'Test'], $this->file);

    expect($attributes['currency'])->toBe('NOK')
        ->and($attributes['title'])->toBe('Test');
});

it('data values override defaults', function () {
    $factory = createTestFactory(
        fields: ['currency'],
        defaults: ['currency' => 'NOK'],
    );

    $attributes = $factory->exposedResolveAttributes(['currency' => 'USD'], $this->file);

    expect($attributes['currency'])->toBe('USD');
});

it('sanitizes date fields with string null values', function () {
    $factory = createTestFactory(
        fields: ['title', 'effective_date', 'expiry_date'],
        dateFields: ['effective_date', 'expiry_date'],
    );

    $attributes = $factory->exposedResolveAttributes([
        'title' => 'null',
        'effective_date' => 'null',
        'expiry_date' => '2024-01-15',
    ], $this->file);

    expect($attributes['title'])->toBe('null')
        ->and($attributes['effective_date'])->toBeNull()
        ->and($attributes['expiry_date'])->toBe('2024-01-15');
});

it('appends raw data field when configured', function () {
    $factory = createTestFactory(
        fields: ['title'],
        rawDataField: 'contract_data',
    );

    $data = ['title' => 'Test', 'extra' => 'info'];
    $attributes = $factory->exposedResolveAttributes($data, $this->file);

    expect($attributes['contract_data'])->toBe($data);
});

it('uses existing raw data field value if present', function () {
    $factory = createTestFactory(
        fields: ['title'],
        rawDataField: 'contract_data',
    );

    $rawPayload = ['original' => true];
    $attributes = $factory->exposedResolveAttributes([
        'title' => 'Test',
        'contract_data' => $rawPayload,
    ], $this->file);

    expect($attributes['contract_data'])->toBe($rawPayload);
});

it('returns null when shouldCreate is false', function () {
    $factory = createTestFactory(fields: ['title']);

    $result = $factory->create([], $this->file);

    expect($result)->toBeNull();
});

it('calls prepareData before resolving attributes', function () {
    $factory = createTestFactory(
        fields: ['title', 'computed'],
        prepareData: fn (array $data) => array_merge($data, ['computed' => 'injected']),
    );

    $attributes = $factory->exposedResolveAttributes(
        $factory->exposedPrepareData(['title' => 'Test'], $this->file),
        $this->file
    );

    expect($attributes['computed'])->toBe('injected');
});

it('sets null for fields not in data and not in defaults', function () {
    $factory = createTestFactory(
        fields: ['title', 'optional_field'],
    );

    $attributes = $factory->exposedResolveAttributes(['title' => 'Test'], $this->file);

    expect($attributes['optional_field'])->toBeNull();
});

// --- Helper ---

function createTestFactory(
    array $fields = [],
    array $dateFields = [],
    array $defaults = [],
    ?string $rawDataField = null,
    ?Closure $prepareData = null,
): object {
    return new class($fields, $dateFields, $defaults, $rawDataField, $prepareData) extends BaseEntityFactory
    {
        public function __construct(
            private readonly array $testFields,
            private readonly array $testDateFields,
            private readonly array $testDefaults,
            private readonly ?string $testRawDataField,
            private readonly ?Closure $testPrepareData,
        ) {}

        protected function modelClass(): string
        {
            return Model::class;
        }

        protected function fields(): array
        {
            return $this->testFields;
        }

        protected function dateFields(): array
        {
            return $this->testDateFields;
        }

        protected function defaults(): array
        {
            return $this->testDefaults;
        }

        protected function rawDataField(): ?string
        {
            return $this->testRawDataField;
        }

        protected function prepareData(array $data, File $file): array
        {
            return $this->testPrepareData
                ? ($this->testPrepareData)($data)
                : $data;
        }

        public function exposedResolveAttributes(array $data, File $file): array
        {
            return $this->resolveAttributes($data, $file);
        }

        public function exposedPrepareData(array $data, File $file): array
        {
            return $this->prepareData($data, $file);
        }
    };
}
