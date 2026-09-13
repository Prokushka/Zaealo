<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TelegramOidc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TelegramAuthenticationController extends Controller
{
    public function redirect(Request $request, TelegramOidc $telegram): RedirectResponse
    {
        $authorization = $telegram->authorization();

        $request->session()->put([
            'telegram_oauth_state' => $authorization['state'],
            'telegram_oauth_verifier' => $authorization['verifier'],
        ]);

        return redirect()->away($authorization['url']);
    }

    public function callback(Request $request, TelegramOidc $telegram): RedirectResponse
    {
        $state = $request->session()->pull('telegram_oauth_state');
        $verifier = $request->session()->pull('telegram_oauth_verifier');

        $returnedState = $request->query('state');

        if (! is_string($state) || ! is_string($verifier) || ! is_string($returnedState) || ! hash_equals($state, $returnedState)) {
            return $this->failed('Сессия авторизации Telegram истекла. Попробуйте ещё раз.');
        }

        if ($request->filled('error')) {
            return $this->failed('Вход через Telegram был отменён.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failed('Telegram не вернул код авторизации. Попробуйте ещё раз.');
        }

        try {
            $telegramUser = $telegram->user($code, $verifier);
            $user = $this->findOrCreateUser($telegramUser);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('Не удалось войти через Telegram. Попробуйте ещё раз.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['telegram' => $message]);
    }

    /**
     * @param  array<string, mixed>  $telegramUser
     */
    private function findOrCreateUser(array $telegramUser): User
    {
        return DB::transaction(function () use ($telegramUser): User {
            $account = SocialAccount::query()
                ->where('provider', 'telegram')
                ->where('provider_id', $telegramUser['sub'])
                ->first();

            if ($account !== null) {
                return $account->user;
            }

            $user = new User([
                'name' => $telegramUser['name'] ?? $telegramUser['preferred_username'] ?? 'Пользователь Telegram',
                'password' => Str::password(32),
            ]);
            $user->email_verified_at = now();
            $user->save();

            $user->socialAccounts()->create([
                'provider' => 'telegram',
                'provider_id' => $telegramUser['sub'],
            ]);

            return $user;
        });
    }
}
