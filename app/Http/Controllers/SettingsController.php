<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\ImportBatch;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\RecurringTemplate;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'ai_provider' => Setting::get('ai_provider', 'none'),
                'ai_model' => Setting::get('ai_model', ''),
                'ai_base_url' => Setting::get('ai_base_url', ''),
                'ai_api_key_set' => ! empty(Setting::get('ai_api_key')),
            ],
        ]);
    }

    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'ai_provider' => 'required|in:none,claude,openai,ollama',
            'ai_api_key' => 'nullable|string',
            'ai_model' => 'nullable|string|max:255',
            'ai_base_url' => 'nullable|string|max:255',
        ]);

        Setting::set('ai_provider', $validated['ai_provider']);

        if ($validated['ai_api_key'] !== null && $validated['ai_api_key'] !== '') {
            Setting::set('ai_api_key', $validated['ai_api_key']);
        }

        Setting::set('ai_model', $validated['ai_model'] ?? '');
        Setting::set('ai_base_url', $validated['ai_base_url'] ?? '');

        return redirect()->back()->with('success', 'KI-Einstellungen gespeichert.');
    }

    public function clearAll()
    {
        DB::transaction(function () {
            LoanPayment::query()->forceDelete();
            Loan::query()->forceDelete();
            CategoryRule::query()->delete();
            Transaction::query()->forceDelete();
            RecurringTemplate::query()->delete();
            ImportBatch::query()->delete();
            Category::query()->forceDelete();
            Account::query()->forceDelete();
        });

        return redirect()->back()->with('success', 'Alle Daten wurden gelöscht.');
    }
}
