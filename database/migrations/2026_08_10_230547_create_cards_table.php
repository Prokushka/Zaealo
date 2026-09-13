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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('marketplace')->default('wildberries');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('attributes')->nullable();
            $table->string('status')->default('draft');
            $table->integer('allowed_photo_slots')->default(3);
            $table->boolean('is_exported')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
