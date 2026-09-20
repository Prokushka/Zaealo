<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\YandexOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class YandexAuthenticationController extends Controller
{
    public function redirect(Request $request, YandexOAuth $yandex): RedirectResponse
    {
        $authorization = $yandex->authorization();

        $request->session()->put([
            'yandex_oauth_state' => $authorization['state'],
            'yandex_oauth_verifier' => $authorization['verifier'],
        ]);

        return redirect()->away($authorization['url']);
    }

    public function callback(Request $request, YandexOAuth $yandex): RedirectResponse
    {
        $state = $request->session()->pull('yandex_oauth_state');
        $verifier = $request->session()->pull('yandex_oauth_verifier');
        $returnedState = $request->query('state');

        if (! is_string($state) || ! is_string($verifier) || ! is_string($returnedState) || ! hash_equals($state, $returnedState)) {
            return $this->failed('Сессия авторизации Яндекс истекла. Попробуйте ещё раз.');
        }

        if ($request->filled('error')) {
            return $this->failed('Вход через Яндекс был отменён.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failed('Яндекс не вернул код авторизации. Попробуйте ещё раз.');
        }

        try {
            $yandexUser = $yandex->user($code, $verifier);
            $user = $this->findOrCreateUser($yandexUser);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('Не удалось войти через Яндекс. Попробуйте ещё раз.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['yandex' => $message]);
    }

    /**
     * @param  array<string, mixed>  $yandexUser
     */
    private function findOrCreateUser(array $yandexUser): User
    {
        return DB::transaction(function () use ($yandexUser): User {
            $account = SocialAccount::query()
                ->where('provider', 'yandex')
                ->where('provider_id', $yandexUser['id'])
                ->first();

            if ($account !== null) {
                if (! $account->user->hasVerifiedEmail()) {
                    $account->user->markEmailAsVerified();
                }

                return $account->user;
            }

            $email = is_string($yandexUser['default_email'] ?? null) ? $yandexUser['default_email'] : null;
            $user = $email === null ? null : User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = new User([
                    'name' => $yandexUser['real_name'] ?? $yandexUser['display_name'] ?? $yandexUser['login'] ?? 'Пользователь Яндекс',
                    'email' => $email,
                    'password' => Str::password(32),
                ]);
                $user->email_verified_at = now();
                $user->save();
            } elseif (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->socialAccounts()->create([
                'provider' => 'yandex',
                'provider_id' => $yandexUser['id'],
            ]);

            return $user;
        });
    }
}
