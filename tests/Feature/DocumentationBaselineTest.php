<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

it('documents only registered Artisan commands and supported options', function (): void {
    $commands = Artisan::all();
    foreach ([base_path('README.md'), ...File::glob(base_path('docs/*.md'))] as $path) {
        preg_match_all('/^\s*(?:php|php8\.5)\s+artisan\s+([^\r\n`]+)/m', File::get($path), $examples);
        foreach ($examples[1] as $example) {
            $tokens = str_getcsv(trim($example), ' ', '"', '');
            $name = array_shift($tokens);
            $this->assertArrayHasKey($name, $commands, $path.': '.$example);
            $definition = $commands[$name]->getDefinition();
            foreach ($tokens as $token) {
                if (str_starts_with($token, '--') && $name !== 'test') {
                    $option = explode('=', substr($token, 2), 2)[0];
                    expect($definition->hasOption($option) || $commands[$name]->getApplication()->getDefinition()->hasOption($option))->toBeTrue($path.': '.$example);
                }
            }
        }
    }
});

it('keeps documented relative Markdown links valid', function (): void {
    foreach ([base_path('README.md'), ...File::glob(base_path('docs/*.md'))] as $path) {
        preg_match_all('/\]\(([^)\s]+\.md)(?:#[^)]*)?\)/', File::get($path), $links);
        foreach ($links[1] as $link) {
            expect(is_file(dirname($path).'/'.$link))->toBeTrue($path.': '.$link);
        }
    }
});

it('documents container formatting paths and safe migration troubleshooting', function (): void {
    $guide = File::get(base_path('docs/developer.md'));
    expect($guide)->toContain('docker compose exec app sh')
        ->toContain('In a native Git checkout, use `vendor/bin/pint --dirty`')
        ->not->toContain('php artisan migrate:fresh');

    preg_match_all('/^vendor\/bin\/pint ([^\r\n]+)/m', $guide, $commands);
    expect($commands[1])->not->toBeEmpty();
    foreach ($commands[1] as $paths) {
        foreach (explode(' ', $paths) as $path) {
            expect(is_file(base_path($path)))->toBeTrue($path);
        }
    }
});

it('matches the documented PHP and frontend package majors to the lockfiles', function (): void {
    $composer = json_decode(File::get(base_path('composer.lock')), true);
    $packages = collect([...$composer['packages'], ...$composer['packages-dev']])->keyBy('name');
    foreach (['laravel/framework' => 13, 'inertiajs/inertia-laravel' => 3, 'pestphp/pest' => 4, 'phpunit/phpunit' => 12] as $name => $major) {
        expect((int) ltrim($packages[$name]['version'], 'v'))->toBe($major);
    }
    $npm = json_decode(File::get(base_path('package-lock.json')), true)['packages'];
    foreach (['vite' => 8, 'vue' => 3, '@inertiajs/vue3' => 3, 'tailwindcss' => 3] as $name => $major) {
        expect((int) $npm['node_modules/'.$name]['version'])->toBe($major);
    }
    expect(config('filesystems.disks.paperpulse.driver'))->toBe('s3');
    expect(config('filesystems.disks.pulsedav.driver'))->toBe('s3');
    expect(config('filesystems.disks.uplink.driver'))->toBe('s3');
    expect(config('filesystems.disks.local.driver'))->toBe('local');
    expect(Route::has('api.files.content'))->toBeTrue();
    expect(Route::has('api.search'))->toBeTrue();
});
