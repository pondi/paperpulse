<?php

use App\Models\Tag;
use App\Models\User;
use App\Rules\ExistsForUser;
use Illuminate\Support\Facades\Validator;

it('passes when the record belongs to the authenticated user', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $validator = Validator::make(
        ['tag_id' => $tag->id],
        ['tag_id' => ['required', new ExistsForUser('tags')]]
    );

    expect($validator->passes())->toBeTrue();
});

it('fails when the record belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherTag = Tag::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user);

    $validator = Validator::make(
        ['tag_id' => $otherTag->id],
        ['tag_id' => ['required', new ExistsForUser('tags')]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('tag_id'))->toBe('The selected tag id does not exist.');
});

it('fails when the record does not exist at all', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $validator = Validator::make(
        ['tag_id' => 99999],
        ['tag_id' => ['required', new ExistsForUser('tags')]]
    );

    expect($validator->fails())->toBeTrue();
});

it('supports custom column names', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $validator = Validator::make(
        ['email' => $user->email],
        ['email' => ['required', new ExistsForUser('users', 'email', 'id')]]
    );

    // This should pass because we're checking users.email where id = auth()->id()
    expect($validator->passes())->toBeTrue();
});
