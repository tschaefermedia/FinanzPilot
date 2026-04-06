<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTemplate;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecurringTemplateController extends Controller
{
    public function index()
    {
        $templates = RecurringTemplate::with(['category', 'account'])
            ->orderByDesc('is_active')
            ->orderBy('next_due_date')
            ->get();

        return Inertia::render('Recurring/Index', [
            'templates' => $templates,
            'categories' => Category::tree(),
            'accounts' => Account::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'category_id' => 'nullable|exists:categories,id',
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'next_due_date' => 'required|date',
            'is_active' => 'boolean',
            'auto_generate' => 'boolean',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        RecurringTemplate::create($validated);

        return redirect()->back()->with('success', 'Dauerauftrag erstellt.');
    }

    public function update(Request $request, RecurringTemplate $recurring)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'category_id' => 'nullable|exists:categories,id',
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'next_due_date' => 'required|date',
            'is_active' => 'boolean',
            'auto_generate' => 'boolean',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $recurring->update($validated);

        return redirect()->back()->with('success', 'Dauerauftrag aktualisiert.');
    }

    public function destroy(RecurringTemplate $recurring)
    {
        $recurring->delete();

        return redirect()->back()->with('success', 'Dauerauftrag gelöscht.');
    }

    /**
     * Manually trigger generation of a single template's next transaction.
     */
    public function generate(RecurringTemplate $recurring)
    {
        if (! $recurring->is_active) {
            return redirect()->back()->with('error', 'Dauerauftrag ist deaktiviert.');
        }

        $this->generateTransaction($recurring);

        return redirect()->back()->with('success', 'Buchung erstellt.');
    }

    /**
     * Generate a transaction from a recurring template and advance the due date.
     */
    public static function generateTransaction(RecurringTemplate $template): void
    {
        Transaction::create([
            'date' => $template->next_due_date->format('Y-m-d'),
            'amount' => $template->amount,
            'description' => $template->description,
            'counterparty' => null,
            'category_id' => $template->category_id,
            'source' => 'recurring',
            'reference' => 'recurring-'.$template->id.'-'.$template->next_due_date->format('Y-m-d'),
            'hash' => hash('sha256', 'recurring|'.$template->id.'|'.$template->next_due_date->format('Y-m-d')),
            'notes' => 'Automatisch erstellt aus Dauerauftrag',
            'account_id' => $template->account_id,
        ]);

        // Advance next_due_date
        $nextDate = match ($template->frequency) {
            'weekly' => $template->next_due_date->addWeek(),
            'monthly' => $template->next_due_date->addMonth(),
            'quarterly' => $template->next_due_date->addMonths(3),
            'yearly' => $template->next_due_date->addYear(),
        };

        $template->update(['next_due_date' => $nextDate]);
    }

}
