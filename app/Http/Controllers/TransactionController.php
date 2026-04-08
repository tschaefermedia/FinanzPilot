<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['category', 'account']);

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('counterparty', 'like', $search)
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', $search));
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->filled('type')) {
            $query->where('amount', $request->type === 'income' ? '>' : '<', 0);
        }

        if ($request->input('account_id') === 'none') {
            $query->whereNull('account_id');
        } elseif ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $sortField = $request->input('sort_field', 'date');
        $sortOrder = $request->input('sort_order', 'desc');

        if (! in_array($sortField, ['date', 'amount', 'description'])) {
            $sortField = 'date';
        }

        $direction = $sortOrder === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $direction);
        if ($sortField !== 'date') {
            $query->orderBy('date', $direction);
        }
        $query->orderBy('id', $direction);

        $transactions = $query->paginate(25)->withQueryString();

        $accounts = Account::activeOrdered()->get();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => Category::with('parent')->orderBy('name')->get(),
            'categoryTree' => Category::tree(),
            'filters' => $request->only(['search', 'category_id', 'date_from', 'date_to', 'type', 'account_id', 'sort_field', 'sort_order']),
            'accounts' => $accounts,
        ]);
    }

    public function create()
    {
        return Inertia::render('Transactions/Form', [
            'categories' => Category::tree(),
            'accounts' => Account::activeOrdered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
            'counterparty' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'notes' => 'nullable|string|max:10000',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $validated['source'] = 'manual';

        Transaction::create($validated);

        return redirect()->route('transactions.index')->with('success', 'Buchung erstellt.');
    }

    public function edit(Transaction $transaction)
    {
        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction->load('category'),
            'categories' => Category::tree(),
            'accounts' => Account::activeOrdered()->get(),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
            'counterparty' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'notes' => 'nullable|string|max:10000',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        $transaction->update($validated);

        return redirect()->route('transactions.index')->with('success', 'Buchung aktualisiert.');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return redirect()->back()->with('success', 'Buchung gelöscht.');
    }

    public function bulkUpdateAccount(Request $request)
    {
        $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'exists:transactions,id',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        Transaction::whereIn('id', $request->transaction_ids)
            ->update(['account_id' => $request->account_id]);

        return redirect()->back()->with(
            'success',
            count($request->transaction_ids).' Buchungen aktualisiert.'
        );
    }
}
