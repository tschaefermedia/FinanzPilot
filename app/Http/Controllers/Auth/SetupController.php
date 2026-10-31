<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * First-run setup: creates the single user account. Only reachable while no user exists.
 */
class SetupController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/Setup');
    }

    public function store(Request $request): HttpResponse
    {
        abort_if(User::query()->exists(), 403, 'Die Einrichtung wurde bereits abgeschlossen.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($validated);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return Inertia::location(route('dashboard'));
    }
}
