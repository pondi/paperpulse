<?php

use App\Models\User;
use App\Models\UserPreference;

test('only supported preferences can save for the authenticated owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $payload = UserPreference::defaultPreferences();
    $this->actingAs($owner)->patch(route('preferences.update'), $payload + ['user_id' => $other->id])
        ->assertSessionHasErrors('user_id');
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $other->id]);
    $this->patch(route('preferences.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($owner->preferences()->first()->language)->toBe('en');
    expect($owner->preferences()->first()->user_id)->toBe($owner->id);
});

test('unknown preferences and unsupported format or sort are rejected', function (string $field, mixed $value) {
    $owner = User::factory()->create();
    $payload = array_replace(UserPreference::defaultPreferences(), [$field => $value]);
    $this->actingAs($owner)->patch(route('preferences.update'), $payload)->assertSessionHasErrors($field);
    expect($owner->preferences()->exists())->toBeFalse();
})->with([['unexpected', true], ['date_format', 'invalid'], ['default_sort', 'invalid']]);
