<?php

use App\Models\Logo;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LogoService;

it('renders uploaded and existing base64 logos as the original image bytes', function () {
    $merchant = Merchant::create(['user_id' => User::factory()->create()->id, 'name' => 'Acme']);
    $path = createFixturePngPath();
    $image = file_get_contents($path);
    unlink($path);
    $service = app(LogoService::class);
    $service->updateModelLogo($merchant, $image, 'image/png');
    $uploaded = $merchant->fresh()->logo;
    $existing = new Logo(['logo_data' => base64_encode($image), 'mime_type' => 'image/png']);

    foreach ([$uploaded, $existing] as $logo) {
        $url = $logo->getUrl();
        expect($logo->getUrl())->toBe($url)
            ->and($url)->toBe($service->getImageUrl($merchant, $logo->logo_data, $logo->mime_type))
            ->and(base64_decode(explode(',', $logo->getUrl(), 2)[1], true))->toBe($image);
    }
});

it('keeps identical logo payloads associated with each model and tenant', function () {
    $owners = User::factory()->count(2)->create();
    $models = collect([
        Merchant::create(['user_id' => $owners[0]->id, 'name' => 'Acme']),
        Merchant::create(['user_id' => $owners[0]->id, 'name' => 'Acme Inc']),
        Merchant::create(['user_id' => $owners[1]->id, 'name' => 'Acme']),
        Vendor::create(['user_id' => $owners[1]->id, 'name' => 'Acme']),
    ]);
    $service = app(LogoService::class);
    foreach ($models as $model) {
        $service->updateModelLogo($model, 'same image', 'image/png');
    }
    $logos = $models->map(fn ($model) => $model->fresh()->logo);
    expect($logos->pluck('id')->unique())->toHaveCount(4);
    foreach ($logos as $index => $logo) {
        expect($logo->logoable_id)->toBe($models[$index]->id)
            ->and($logo->logoable_type)->toBe($models[$index]::class)
            ->and($logo->logo_data)->toBe(base64_encode('same image'));
    }

    $service->updateModelLogo($models[0], 'replacement', 'image/jpeg');
    expect($models[0]->fresh()->logo->logo_data)->toBe(base64_encode('replacement'))
        ->and($models[1]->fresh()->logo->logo_data)->toBe(base64_encode('same image'))
        ->and(Logo::count())->toBe(4);
});

it('retains the previous association when saving a replacement fails', function () {
    $merchant = Merchant::create(['user_id' => User::factory()->create()->id, 'name' => 'Acme']);
    $service = app(LogoService::class);
    $service->updateModelLogo($merchant, 'original', 'image/png');
    $original = $merchant->fresh()->logo;
    Logo::saved(function (): void {
        throw new RuntimeException('Save failed');
    });

    try {
        expect(fn () => $service->updateModelLogo($merchant, 'new', 'image/jpeg'))->toThrow(RuntimeException::class);
        expect($merchant->fresh()->logo->id)->toBe($original->id)
            ->and($merchant->fresh()->logo->logo_data)->toBe(base64_encode('original'));
    } finally {
        Logo::flushEventListeners();
    }
});
