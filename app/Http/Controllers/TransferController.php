<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transfers,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|gt:0',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $this->transfers->create(
            Account::findOrFail($validated['from_account_id']),
            Account::findOrFail($validated['to_account_id']),
            (float) $validated['amount'],
            $validated['date'],
            $validated['description'] ?: 'Umbuchung',
        );

        return redirect()->back()->with('success', 'Umbuchung erstellt.');
    }

    public function candidates(Transaction $transaction)
    {
        return $this->transfers->candidates($transaction)
            ->map(fn (Transaction $candidate) => [
                'id' => $candidate->id,
                'date' => $candidate->date->toDateString(),
                'amount' => $candidate->amount,
                'description' => $candidate->description,
                'counterparty' => $candidate->counterparty,
                'account' => $candidate->account?->name,
            ]);
    }

    public function link(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'counterpart_id' => 'required|exists:transactions,id',
        ]);

        $this->transfers->link($transaction, Transaction::findOrFail($validated['counterpart_id']));

        return redirect()->back()->with('success', 'Buchungen als Umbuchung verknüpft.');
    }

    public function unlink(Transaction $transaction)
    {
        $this->transfers->unlink($transaction);

        return redirect()->back()->with('success', 'Verknüpfung gelöst.');
    }
}
