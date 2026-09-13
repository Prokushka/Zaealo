<?php

use Illuminate\Support\Facades\Schema;

it('creates the marketplace card and billing tables', function () {
    expect(Schema::hasColumns('pricing_rates', [
        'key', 'title', 'cost_zarks', 'value', 'group',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('zark_packages', [
            'name', 'zarks_amount', 'price_rub', 'discount_percent', 'is_popular', 'is_active',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('cards', [
            'user_id', 'parent_id', 'marketplace', 'title', 'description', 'attributes',
            'status', 'allowed_photo_slots', 'is_exported',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('competitor_data', [
            'card_id', 'marketplace', 'parsed_cards',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('card_generations', [
            'card_id', 'mode', 'selected_style', 'prompt_input', 'generated_title',
            'generated_description', 'generated_bullets', 'category_match', 'attributes_category_id',
            'attributes_type_id', 'attributes_data', 'status', 'cost_zarks',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('card_images', [
            'card_id', 'generation_id', 'type', 'generation_status',
            'generation_category', 'generation_subcategory', 'generation_features',
            'generation_error', 'path', 'is_main', 'is_paid',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('card_exports', [
            'card_generation_id', 'marketplace', 'status', 'payload', 'external_task_id',
            'external_product_id', 'error', 'cost_zarks', 'status_checks', 'completed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('transactions', [
            'user_id', 'amount', 'type', 'description', 'reference_type', 'reference_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('payments', [
            'user_id', 'zark_package_id', 'payment_system', 'external_payment_id',
            'amount_rub', 'zarks_added', 'status',
        ]))->toBeTrue();
});
