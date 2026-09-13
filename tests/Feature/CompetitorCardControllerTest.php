<?php

use App\Models\User;

test('guests cannot search competitor cards', function () {
    $this->post(route('competitor-cards.search'))
        ->assertRedirect(route('login'));
});

test('competitor card search validates marketplace search query and marketplace', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('competitor-cards.search'), [
            'marketplace_search_query' => '',
            'marketplace' => 'unsupported',
        ])
        ->assertSessionHasErrors(['marketplace_search_query', 'marketplace']);
});

test('competitor card search query contains no more than three words', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('competitor-cards.search'), [
            'marketplace_search_query' => 'слишком длинный поисковый запрос',
            'marketplace' => 'ozon',
        ])
        ->assertSessionHasErrors('marketplace_search_query');
});
