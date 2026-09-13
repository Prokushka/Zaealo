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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Провайдер: 'vkid' или 'telegram'
            $table->string('provider');

            // ID пользователя в самом ВК или ТГ (например, "123456789")
            $table->string('provider_id');

            // Необязательно, но полезно (токены доступа, если захочешь читать аватарки или имена)
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->timestamps();

            // Защита от дублей: один и тот же соц-аккаунт не может быть привязан к двум юзерам
            $table->unique(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
