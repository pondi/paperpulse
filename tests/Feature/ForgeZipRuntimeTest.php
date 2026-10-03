<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('creates ZIP output from a database queue worker in the selected PHP runtime', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = 'private/exports/'.$user->id.'/runtime.zip';
    Queue::connection('database')->push(function () use ($path): void {
        Storage::disk('local')->makeDirectory(dirname($path));
        $archive = new ZipArchive;
        $archive->open(Storage::disk('local')->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('fixture.txt', 'Queued ZIP content');
        $archive->close();
    }, queue: 'exports');
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'exports', '--once' => true, '--no-interaction' => true])->assertSuccessful();
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('local')->path($path), ZipArchive::RDONLY))->toBeTrue();
    expect($archive->getFromName('fixture.txt'))->toBe('Queued ZIP content');
    $archive->close();
});
