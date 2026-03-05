<?php

use App\Models\Collection;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('PATCH /files/{id}', function () {
    it('updates file note', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(route('api.files.update', $file), [
            'note' => 'Updated note',
        ]);

        $response->assertSuccessful();
        expect($file->fresh()->note)->toBe('Updated note');
    });

    it('syncs tags on file', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);
        $tags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(route('api.files.update', $file), [
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertSuccessful();
        expect($file->fresh()->tags)->toHaveCount(2);
    });

    it('syncs collections on file', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);
        $collections = Collection::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(route('api.files.update', $file), [
            'collection_ids' => $collections->pluck('id')->toArray(),
        ]);

        $response->assertSuccessful();
        expect($file->fresh()->collections)->toHaveCount(2);
    });

    it('returns 404 for other users file', function () {
        $other = User::factory()->create();
        $file = File::factory()->create(['user_id' => $other->id]);

        $response = $this->patchJson(route('api.files.update', $file), [
            'note' => 'Hacked',
        ]);

        $response->assertNotFound();
    });

    it('returns 404 for non-existent file', function () {
        $response = $this->patchJson(route('api.files.update', 99999), [
            'note' => 'Missing',
        ]);

        $response->assertNotFound();
    });

    it('validates input', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $response = $this->patchJson(route('api.files.update', $file), [
            'tag_ids' => 'not-an-array',
        ]);

        $response->assertStatus(422);
    });
});

describe('DELETE /files/{id}', function () {
    it('soft deletes a file', function () {
        $file = File::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson(route('api.files.destroy', $file));

        $response->assertStatus(204);
        $this->assertSoftDeleted('files', ['id' => $file->id]);
    });

    it('returns 404 for other users file', function () {
        $other = User::factory()->create();
        $file = File::factory()->create(['user_id' => $other->id]);

        $response = $this->deleteJson(route('api.files.destroy', $file));

        $response->assertNotFound();
    });

    it('returns 404 for non-existent file', function () {
        $response = $this->deleteJson(route('api.files.destroy', 99999));

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->app['auth']->forgetGuards();
        $file = File::factory()->create();

        $response = $this->deleteJson(route('api.files.destroy', $file));

        $response->assertUnauthorized();
    });
});
