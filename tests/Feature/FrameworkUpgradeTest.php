<?php

use App\Services\AI\Providers\OpenAIProvider;
use Inertia\Testing\AssertableInertia as Assert;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

it('renders the initial Inertia page with JSON bootstrap data and the managed title', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('<title data-inertia>', false)
        ->assertSee('<script data-page="app" type="application/json">', false)
        ->assertSee('<div id="app"></div>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('auth.user', null)
        );
});

it('returns shared props for Inertia navigation requests', function () {
    $version = $this->get(route('login'))->viewData('page')['version'];

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ])
        ->get(route('login'))
        ->assertSuccessful()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Auth/Login')
        ->assertJsonPath('props.auth.user', null)
        ->assertJsonStructure([
            'props' => [
                'flash' => ['success', 'error', 'warning', 'info', 'publicLink'],
                'language' => ['messages'],
            ],
        ]);
});

it('requests a full page reload when the client asset version is stale', function () {
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'stale-version',
    ])
        ->get(route('login'))
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', route('login'));
});

it('accepts same-origin form requests without a legacy CSRF token', function () {
    $this->app['env'] = 'local';

    $this->withHeader('Sec-Fetch-Site', 'same-origin')
        ->post(route('login'), [])
        ->assertRedirect()
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

it('rejects cross-site form requests without a CSRF token', function () {
    $this->app['env'] = 'local';

    $this->withHeader('Sec-Fetch-Site', 'cross-site')
        ->post(route('login'), [])
        ->assertStatus(419);

    $this->assertGuest();
});

it('parses document analysis responses from the upgraded OpenAI client', function () {
    $analysis = ['summary' => 'A software licensing agreement.', 'tags' => ['software', 'contract']];

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['content' => json_encode($analysis)]],
            ],
            'usage' => ['total_tokens' => 42],
        ]),
    ]);

    $result = app(OpenAIProvider::class)->analyzeDocument('A software licensing agreement.');

    expect($result)
        ->success->toBeTrue()
        ->data->toBe($analysis)
        ->provider->toBe('openai')
        ->tokens_used->toBe(42);

    OpenAI::assertSent(Chat::class, fn (string $method, array $parameters): bool => $method === 'create' &&
        $parameters['model'] === config('ai.models.document') &&
        $parameters['response_format']['type'] === 'json_schema'
    );
});

it('handles document analysis failures from the upgraded OpenAI client', function () {
    OpenAI::fake([new RuntimeException('Request timed out')]);

    $result = app(OpenAIProvider::class)->analyzeDocument('A software licensing agreement.');

    expect($result)
        ->success->toBeFalse()
        ->error->toBe('Request timed out')
        ->provider->toBe('openai');
});
