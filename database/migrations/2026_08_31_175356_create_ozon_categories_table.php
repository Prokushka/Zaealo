<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($isPostgres) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('ozon_categories', function (Blueprint $table) use ($isPostgres) {
            $table->id();
            $table->unsignedBigInteger('description_category_id');
            $table->unsignedBigInteger('type_id');
            $table->string('category_name');
            $table->string('type_name');
            $table->string('full_path')->index();
            $isPostgres
                ? $table->vector('embedding', dimensions: 1536)->nullable()->index()
                : $table->json('embedding')->nullable();
            $table->timestamps();

            $table->unique(['description_category_id', 'type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('ozon_categories');
    }
};
