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
        $isPostgres = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('wb_categories', function (Blueprint $table) use ($isPostgres) {
            $table->id();
            $table->unsignedBigInteger('subject_id')->unique();
            $table->string('subject_name');
            $table->string('parent_name')->nullable();
            $table->string('full_path')->index();
            $isPostgres
                ? $table->vector('embedding', dimensions: 1536)->nullable()->index()
                : $table->json('embedding')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wb_categories');
    }
};
