<?php

use App\Models\OzonCategory;
use App\Models\User;
use App\Models\WbCategory;

test('returns local Ozon categories using native category and type IDs', function (): void {
    OzonCategory::query()->create([
        'description_category_id' => 101,
        'type_id' => 501,
        'category_name' => 'Одежда',
        'type_name' => 'Свитшоты',
        'full_path' => 'Одежда > Свитшоты',
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('marketplace-categories.search', [
            'marketplace' => 'ozon',
            'query' => 'свит',
        ]))
        ->assertSuccessful()
        ->assertExactJson([
            'categories' => [[
                'category_id' => 101,
                'type_id' => 501,
                'full_path' => 'Одежда > Свитшоты',
            ]],
        ]);
});

test('returns local Wildberries categories using the subject ID', function (): void {
    WbCategory::query()->create([
        'subject_id' => 202,
        'subject_name' => 'Худи',
        'parent_name' => 'Одежда',
        'full_path' => 'Одежда > Худи',
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('marketplace-categories.search', [
            'marketplace' => 'wildberries',
            'query' => 'худи',
        ]))
        ->assertSuccessful()
        ->assertExactJson([
            'categories' => [[
                'category_id' => 202,
                'type_id' => null,
                'full_path' => 'Одежда > Худи',
            ]],
        ]);
});

test('requires authentication to search local categories', function (): void {
    $this->getJson(route('marketplace-categories.search', [
        'marketplace' => 'ozon',
        'query' => 'свит',
    ]))->assertRedirect(route('login'));
});
