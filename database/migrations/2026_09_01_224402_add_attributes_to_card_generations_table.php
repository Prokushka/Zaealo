<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('card_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('attributes_category_id')->nullable();
            $table->unsignedBigInteger('attributes_type_id')->nullable();
            $table->json('attributes_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_generations', function (Blueprint $table) {
            $table->dropColumn([
                'attributes_category_id',
                'attributes_type_id',
                'attributes_data',
            ]);
        });
    }
};
