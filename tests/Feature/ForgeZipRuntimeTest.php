<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('creates ZIP output across all named queues using one database worker', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    foreach (config('queue.worker_queues') as $queue) {
        $path = 'private/exports/'.$user->id.'/'.$queue.'.zip';
        Queue::connection('database')->push(function () use ($path): void {
            Storage::disk('local')->makeDirectory(dirname($path));
            $archive = new ZipArchive;
            $archive->open(Storage::disk('local')->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $archive->addFromString('fixture.txt', 'Queued ZIP content');
            $archive->close();
        }, queue: $queue);
    }
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => implode(',', config('queue.worker_queues')),
        '--stop-when-empty' => true, '--sleep' => 0, '--memory' => 512, '--no-interaction' => true])->assertSuccessful();

    foreach (config('queue.worker_queues') as $queue) {
        $path = 'private/exports/'.$user->id.'/'.$queue.'.zip';
        $archive = new ZipArchive;
        expect($archive->open(Storage::disk('local')->path($path), ZipArchive::RDONLY))->toBeTrue();
        expect($archive->getFromName('fixture.txt'))->toBe('Queued ZIP content');
        $archive->close();
    }
});
