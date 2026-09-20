<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('support page requires authentication', function () {
    $this->get(route('support'))->assertRedirect(route('login'));
});

test('support page exposes the current user support code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('support'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Support/Index')
            ->where('auth.user.support_code', $user->support_code));
});
