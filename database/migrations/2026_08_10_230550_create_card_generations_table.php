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
        Schema::create('card_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('fast');
            $table->string('selected_style')->nullable();
            $table->text('prompt_input')->nullable();
            $table->string('generated_title')->nullable();
            $table->text('generated_description')->nullable();
            $table->json('generated_bullets')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('cost_zarks', 8, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_generations');
    }
};
