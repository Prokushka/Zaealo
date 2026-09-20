<?php

namespace App\Providers;

use App\Events\CardImageGenerationFailed;
use App\Listeners\RefundFailedCardImageGeneration;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CardImageGenerationFailed::class, RefundFailedCardImageGeneration::class);

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Подтвердите электронную почту — ZARQ')
            ->greeting('Здравствуйте!')
            ->line('Подтвердите адрес электронной почты, чтобы получить доступ к ZARQ.')
            ->action('Подтвердить почту', $url)
            ->line('Ссылка действует 60 минут.')
            ->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.'));

        Vite::prefetch(concurrency: 3);
    }
}
