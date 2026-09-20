<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

test('registration form is rendered on the login screen', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class);

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('gmail addresses can not be used to register', function (string $email) {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('users', ['email' => mb_strtolower($email)]);
})->with([
    'gmail' => 'test@gmail.com',
    'gmail uppercase' => 'Test@GMAIL.COM',
    'legacy googlemail' => 'test@googlemail.com',
]);

test('non gmail addresses can be used to register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@yandex.ru',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
