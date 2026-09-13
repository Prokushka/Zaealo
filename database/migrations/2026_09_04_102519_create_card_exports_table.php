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
        Schema::create('card_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_generation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('marketplace');
            $table->string('status')->default('not_published')->index();
            $table->json('payload');
            $table->string('external_task_id')->nullable();
            $table->string('external_product_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('cost_zarks')->default(0);
            $table->unsignedSmallInteger('status_checks')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_exports');
    }
};
