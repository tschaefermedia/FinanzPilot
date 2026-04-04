<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Services\AmortizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function __construct(
        private AmortizationService $amortization,
    ) {}

    public function index()
    {
        $loans = Loan::with(['payments' => fn ($q) => $q->orderByDesc('date')])
            ->orderByDesc('created_at')
            ->get();

        // Calculate summaries for each loan
        $loansData = $loans->map(function ($loan) {
            $summary = $this->amortization->calculateSummary($loan);

            return [
                'id' => $loan->id,
                'name' => $loan->name,
                'type' => $loan->type,
                'principal' => $loan->principal,
                'interest_rate' => $loan->interest_rate,
                'start_date' => $loan->start_date?->format('Y-m-d'),
                'term_months' => $loan->term_months,
                'payment_day' => $loan->payment_day,
                'monthly_rate' => $loan->monthly_rate,
                'initial_balance' => $loan->initial_balance,
                'match_description' => $loan->match_description,
                'account_id' => $loan->account_id,
                'direction' => $loan->direction,
                'notes' => $loan->notes,
                'payments' => $loan->payments,
                'summary' => $summary,
            ];
        });

        $accounts = Account::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Loans/Index', [
            'loans' => $loansData,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bank,informal',
            'principal' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'term_months' => 'nullable|integer|min:1',
            'payment_day' => 'nullable|integer|min:1|max:31',
            'monthly_rate' => 'nullable|numeric|min:0',
            'initial_balance' => 'nullable|numeric|min:0',
            'match_description' => 'nullable|string|max:255',
            'account_id' => 'nullable|exists:accounts,id',
            'direction' => 'required|in:owed_by_me,owed_to_me',
            'notes' => 'nullable|string',
        ]);

        Loan::create($validated);

        return redirect()->back()->with('success', 'Darlehen erstellt.');
    }

    public function update(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bank,informal',
            'principal' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'term_months' => 'nullable|integer|min:1',
            'payment_day' => 'nullable|integer|min:1|max:31',
            'monthly_rate' => 'nullable|numeric|min:0',
            'initial_balance' => 'nullable|numeric|min:0',
            'match_description' => 'nullable|string|max:255',
            'account_id' => 'nullable|exists:accounts,id',
            'direction' => 'required|in:owed_by_me,owed_to_me',
            'notes' => 'nullable|string',
        ]);

        $loan->update($validated);

        return redirect()->back()->with('success', 'Darlehen aktualisiert.');
    }

    public function destroy(Loan $loan)
    {
        $loan->delete();

        return redirect()->back()->with('success', 'Darlehen gelöscht.');
    }

    /**
     * Show detailed view of a single loan with amortization schedule.
     */
    public function show(Loan $loan)
    {
        $loan->load(['payments' => fn ($q) => $q->with('transaction')->orderByDesc('date')]);
        $summary = $this->amortization->calculateSummary($loan);

        return Inertia::render('Loans/Show', [
            'loan' => [
                'id' => $loan->id,
                'name' => $loan->name,
                'type' => $loan->type,
                'principal' => $loan->principal,
                'interest_rate' => $loan->interest_rate,
                'start_date' => $loan->start_date?->format('Y-m-d'),
                'term_months' => $loan->term_months,
                'payment_day' => $loan->payment_day,
                'monthly_rate' => $loan->monthly_rate,
                'initial_balance' => $loan->initial_balance,
                'match_description' => $loan->match_description,
                'account_id' => $loan->account_id,
                'direction' => $loan->direction,
                'notes' => $loan->notes,
                'payments' => $loan->payments,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Add a manual payment to a loan.
     */
    public function addPayment(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:scheduled,extra,manual',
        ]);

        LoanPayment::create([
            'loan_id' => $loan->id,
            'date' => $validated['date'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
        ]);

        return redirect()->back()->with('success', 'Zahlung erfasst.');
    }

    /**
     * Auto-match imported transactions as loan payments.
     */
    public function autoMatch(Loan $loan)
    {
        $matched = $this->amortization->autoMatchPayments($loan);

        if ($matched > 0) {
            return redirect()->back()->with('success', "{$matched} Zahlung(en) automatisch zugeordnet.");
        }

        return redirect()->back()->with('info', 'Keine passenden Buchungen gefunden.');
    }

    /**
     * Return unmatched transactions that could belong to this loan.
     */
    public function unmatchedTransactions(Loan $loan)
    {
        $query = Transaction::where('amount', '<', 0)
            ->whereDoesntHave('loanPayment')
            ->where('date', '>=', $loan->start_date)
            ->orderByDesc('date');

        if ($loan->account_id) {
            $query->where('account_id', $loan->account_id);
        }

        if ($loan->match_description) {
            $query->where('description', 'like', '%'.$loan->match_description.'%');
        }

        return $query->limit(50)->get(['id', 'date', 'description', 'amount', 'account_id']);
    }

    /**
     * Manually link a transaction to this loan as a payment.
     */
    public function matchTransaction(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        LoanPayment::create([
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
            'date' => $transaction->date,
            'amount' => abs((float) $transaction->amount),
            'type' => 'scheduled',
        ]);

        return redirect()->back()->with('success', 'Buchung zugeordnet.');
    }
}
