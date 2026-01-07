<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the scanner page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('scanner'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scanner/Index')
        );
});
