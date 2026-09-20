<?php

use App\Models\User;

test('users receive unique public support codes', function () {
    $users = User::factory()->count(3)->create();

    expect($users->pluck('support_code')->unique())->toHaveCount(3);

    foreach ($users as $user) {
        expect($user->support_code)->toMatch('/^ZQ-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/');
    }
});

test('support code cannot be changed after user creation', function () {
    $user = User::factory()->create();
    $supportCode = $user->support_code;

    $user->support_code = 'ZQ-2222-2222-2222';
    $user->save();

    expect($user->refresh()->support_code)->toBe($supportCode);
});
