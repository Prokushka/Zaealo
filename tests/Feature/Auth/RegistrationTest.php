<?php

test('registration form is rendered on the login screen', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))->assertSuccessful();
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
