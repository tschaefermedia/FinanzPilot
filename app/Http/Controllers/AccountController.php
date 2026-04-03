<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'starting_balance' => $account->starting_balance,
                    'currency' => $account->currency,
                    'icon' => $account->icon,
                    'color' => $account->color,
                    'sort_order' => $account->sort_order,
                    'is_active' => $account->is_active,
                    'current_balance' => $account->current_balance,
                    'transaction_count' => $account->transactions()->count(),
                ];
            });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:checking,savings,credit_card,cash,other',
            'starting_balance' => 'required|numeric',
            'icon' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        Account::create($validated);

        return redirect()->back()->with('success', 'Konto erstellt.');
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:checking,savings,credit_card,cash,other',
            'starting_balance' => 'required|numeric',
            'icon' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $account->update($validated);

        return redirect()->back()->with('success', 'Konto aktualisiert.');
    }

    public function destroy(Account $account)
    {
        $account->transactions()->update(['account_id' => null]);
        $account->recurringTemplates()->update(['account_id' => null]);
        $account->delete();

        return redirect()->back()->with('success', 'Konto gelöscht.');
    }
}
