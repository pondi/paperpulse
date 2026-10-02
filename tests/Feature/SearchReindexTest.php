<?php

use App\Console\Commands\ReindexMeilisearch;
use App\Jobs\Search\ReindexFile;
use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksResults;
use Meilisearch\Endpoints\Indexes;

it('reindexes multiple documents without lazy loading or foreign owners', function () {
    $file = File::factory()->create(['status' => 'completed']);
    $documents = Document::factory()->count(2)->create(['user_id' => $file->user_id, 'file_id' => $file->id]);
    Document::factory()->create(['file_id' => $file->id, 'user_id' => User::factory()->create()->id]);
    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('update')->once()->withArgs(function ($models) use ($documents) {
        expect($models->modelKeys())->toBe($documents->modelKeys());
        foreach ($models as $document) {
            expect($document->relationLoaded('file'))->toBeTrue();
            expect($document->toSearchableArray()['user_id'])->toBe($document->user_id);
        }

        return true;
    });
    $this->mock(EngineManager::class)->shouldReceive('engine')->andReturn($engine);
    config(['scout.queue' => false]);
    Model::preventLazyLoading();

    (new ReindexFile($file->id))->handle();
});

it('imports every configured searchable model and waits for engine results', function () {
    $models = array_keys(config('scout.meilisearch.index-settings'));
    expect($models)->toHaveCount(10);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getTasks')->once()->andReturn(new TasksResults(['results' => []]));
    $command = Mockery::mock(ReindexMeilisearch::class)->makePartial();
    $command->__construct();
    foreach ($models as $position => $model) {
        expect(class_uses_recursive($model))->toContain(Searchable::class);
        $command->shouldReceive('call')->with('scout:import', ['model' => $model])->once()->andReturn(0);
        $index = Mockery::mock(Indexes::class);
        $client->shouldReceive('index')->with((new $model)->indexableAs())->once()->andReturn($index);
        $index->shouldReceive('getTasks')->once()->andReturn(new TasksResults(['results' => [['uid' => $position]]]));
        $client->shouldReceive('waitForTask')->with($position, 60000)->once()->andReturn(['status' => 'succeeded']);
    }
    $this->app->instance(Client::class, $client);
    $this->app->instance(ReindexMeilisearch::class, $command);
    config(['scout.queue' => true]);

    $this->artisan('scout:reindex-all')->assertSuccessful();
    expect(config('scout.queue'))->toBeTrue();
});

it('fails when an import or flush command fails', function (bool $fresh) {
    config(['scout.meilisearch.index-settings' => [Receipt::class => []]]);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getTasks')->once()->andReturn(new TasksResults(['results' => []]));
    $command = Mockery::mock(ReindexMeilisearch::class)->makePartial();
    $command->__construct();
    $command->shouldReceive('call')->with($fresh ? 'scout:flush' : 'scout:import', ['model' => Receipt::class])
        ->once()->andReturn(1);
    $this->app->instance(Client::class, $client);
    $this->app->instance(ReindexMeilisearch::class, $command);

    $this->artisan('scout:reindex-all', ['--fresh' => $fresh])->assertFailed();
})->with([false, true]);

it('fails when an asynchronously accepted import fails', function () {
    config(['scout.meilisearch.index-settings' => [Receipt::class => []]]);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getTasks')->once()->andReturn(new TasksResults(['results' => [['uid' => 4]]]));
    $index = Mockery::mock(Indexes::class);
    $client->shouldReceive('index')->with('receipts')->once()->andReturn($index);
    $index->shouldReceive('getTasks')->once()->andReturn(new TasksResults(['results' => [['uid' => 5]]]));
    $client->shouldReceive('waitForTask')->with(5, 60000)->once()->andReturn(['status' => 'failed', 'error' => ['message' => 'Invalid data']]);
    $command = Mockery::mock(ReindexMeilisearch::class)->makePartial();
    $command->__construct();
    $command->shouldReceive('call')->with('scout:import', ['model' => Receipt::class])->once()->andReturn(0);
    $this->app->instance(Client::class, $client);
    $this->app->instance(ReindexMeilisearch::class, $command);

    $this->artisan('scout:reindex-all')->expectsOutput('Index task 5 failed: Invalid data')->assertFailed();
});

it('configures prefixed indices and checks the settings result', function (string $status) {
    $settings = ['filterableAttributes' => ['user_id']];
    config(['scout.prefix' => 'test_', 'scout.meilisearch.index-settings' => [Receipt::class => $settings]]);
    $client = Mockery::mock(Client::class);
    $index = Mockery::mock(Indexes::class);
    $client->shouldReceive('index')->with('test_receipts')->once()->andReturn($index);
    $index->shouldReceive('updateSettings')->with($settings)->once()->andReturn(['taskUid' => 1]);
    $client->shouldReceive('waitForTask')->with(1, 60000)->once()->andReturn(['status' => $status]);
    $this->app->instance(Client::class, $client);

    $this->artisan('meilisearch:configure')->assertExitCode($status === 'succeeded' ? 0 : 1);
})->with(['succeeded', 'failed']);

it('includes the receipt owner and matching text field in line item payloads', function () {
    $receipt = Receipt::factory()->create();
    $item = $receipt->lineItems()->create(['text' => 'Coffee', 'qty' => 1, 'price' => 12]);
    $this->actingAs(User::factory()->create());

    expect($item->toSearchableArray())->toMatchArray([
        'id' => $item->id, 'receipt_id' => $receipt->id, 'user_id' => $receipt->user_id, 'description' => 'Coffee',
    ]);
});
