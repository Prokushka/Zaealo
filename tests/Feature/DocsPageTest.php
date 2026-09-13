<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

test('guests are redirected from the instructions page', function () {
    $this->get(route('docs'))
        ->assertRedirect(route('login'));
});

test('authenticated users can open the API key instructions page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('docs'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Docs/Index'));
});

test('API key guide screenshots are available publicly', function () {
    $screenshots = [
        'images/guides/ozon/01-open-settings.jpg',
        'images/guides/ozon/02-open-seller-api.jpg',
        'images/guides/ozon/03-generate-key.jpg',
        'images/guides/ozon/04-key-settings.jpg',
        'images/guides/ozon/05-token-roles.jpg',
        'images/guides/ozon/06-copy-key.jpg',
        'images/guides/wildberries/01-open-api-integrations.jpg',
        'images/guides/wildberries/02-create-token.jpg',
        'images/guides/wildberries/03-manual-integration.jpg',
        'images/guides/wildberries/04-basic-token.jpg',
        'images/guides/wildberries/05-permissions.jpg',
        'images/guides/wildberries/06-name-token.jpg',
        'images/guides/wildberries/07-copy-token.jpg',
    ];

    foreach ($screenshots as $screenshot) {
        expect(File::exists(public_path($screenshot)))->toBeTrue();
    }
});
