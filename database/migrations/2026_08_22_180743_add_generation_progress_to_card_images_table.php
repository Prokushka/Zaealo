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
        Schema::table('card_images', function (Blueprint $table) {
            $table->string('generation_status')->default('completed')->after('type');
            $table->string('generation_category')->nullable()->after('generation_status');
            $table->string('generation_subcategory')->nullable()->after('generation_category');
            $table->json('generation_features')->nullable()->after('generation_subcategory');
            $table->string('generation_error')->nullable()->after('generation_features');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_images', function (Blueprint $table) {
            $table->dropColumn([
                'generation_status',
                'generation_category',
                'generation_subcategory',
                'generation_features',
                'generation_error',
            ]);
        });
    }
};
