<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    public function create(): Response|RedirectResponse
    {
        if (! User::query()->exists()) {
            return redirect()->route('setup');
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): HttpResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Anmeldeversuche. Bitte in '.RateLimiter::availableIn($throttleKey).' Sekunden erneut versuchen.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'E-Mail oder Passwort ist falsch.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Full page load so the CSRF meta tag used by fetch() calls matches the new session.
        return Inertia::location(redirect()->intended(route('dashboard'))->getTargetUrl());
    }

    public function destroy(Request $request): HttpResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }
}
