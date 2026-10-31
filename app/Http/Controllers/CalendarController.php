<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTemplate;
use App\Models\ScheduledPayment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->query('month');
        $now = now()->format('Y-m');

        if (! $selectedMonth || ! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = $now;
        }

        $selectedDate = Carbon::createFromFormat('!Y-m', $selectedMonth)->startOfMonth();
        $rangeStart = $selectedDate->copy()->startOfMonth();
        $rangeEnd = $selectedDate->copy()->addMonths(2)->endOfMonth();

        // Project recurring template events
        $recurringEvents = [];
        $templates = RecurringTemplate::with('category', 'account')
            ->where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            $date = $template->next_due_date->copy();

            // Project forward until end of range
            while ($date->lte($rangeEnd)) {
                if ($date->gte($rangeStart)) {
                    $recurringEvents[] = [
                        'id' => 'recurring-'.$template->id.'-'.$date->format('Y-m-d'),
                        'type' => 'recurring',
                        'description' => $template->description,
                        'amount' => (float) $template->amount,
                        'date' => $date->format('Y-m-d'),
                        'category' => $template->category?->name,
                        'account' => $template->account?->name,
                        'templateId' => $template->id,
                        'frequency' => $template->frequency,
                    ];
                }

                $date = match ($template->frequency) {
                    'weekly' => $date->addWeek(),
                    'monthly' => $date->addMonth(),
                    'quarterly' => $date->addMonths(3),
                    'yearly' => $date->addYear(),
                };
            }
        }

        // Get scheduled payments in range
        $scheduledPayments = ScheduledPayment::with('category', 'account')
            ->where('is_completed', false)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->map(fn ($p) => [
                'id' => 'scheduled-'.$p->id,
                'type' => 'scheduled',
                'description' => $p->description,
                'amount' => (float) $p->amount,
                'date' => $p->date->format('Y-m-d'),
                'category' => $p->category?->name,
                'account' => $p->account?->name,
                'paymentId' => $p->id,
                'notes' => $p->notes,
            ])
            ->toArray();

        $events = collect(array_merge($recurringEvents, $scheduledPayments))
            ->sortBy('date')
            ->values()
            ->toArray();

        // Build 3-month list
        $months = [];
        for ($i = 0; $i < 3; $i++) {
            $months[] = $selectedDate->copy()->addMonths($i)->format('Y-m');
        }

        // Summary per month
        $summary = [];
        foreach ($months as $month) {
            $monthEvents = array_filter($events, fn ($e) => str_starts_with($e['date'], $month));
            $income = array_sum(array_map(fn ($e) => $e['amount'] > 0 ? $e['amount'] : 0, $monthEvents));
            $expenses = array_sum(array_map(fn ($e) => $e['amount'] < 0 ? abs($e['amount']) : 0, $monthEvents));
            $summary[$month] = [
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'net' => round($income - $expenses, 2),
                'count' => count($monthEvents),
            ];
        }

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'months' => $months,
            'selectedMonth' => $selectedMonth,
            'summary' => $summary,
            'categories' => Category::tree(),
            'accounts' => Account::activeOrdered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        ScheduledPayment::create($validated);

        return redirect()->back()->with('success', 'Geplante Zahlung erstellt.');
    }

    public function update(Request $request, ScheduledPayment $scheduledPayment)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $scheduledPayment->update($validated);

        return redirect()->back()->with('success', 'Geplante Zahlung aktualisiert.');
    }

    public function destroy(ScheduledPayment $scheduledPayment)
    {
        $scheduledPayment->delete();

        return redirect()->back()->with('success', 'Geplante Zahlung gelöscht.');
    }

    public function complete(ScheduledPayment $scheduledPayment)
    {
        // Create a real transaction from the scheduled payment
        Transaction::create([
            'date' => $scheduledPayment->date->format('Y-m-d'),
            'amount' => $scheduledPayment->amount,
            'description' => $scheduledPayment->description,
            'category_id' => $scheduledPayment->category_id,
            'account_id' => $scheduledPayment->account_id,
            'source' => 'manual',
            'notes' => $scheduledPayment->notes,
            'hash' => hash('sha256', 'scheduled|'.$scheduledPayment->id.'|'.$scheduledPayment->date->format('Y-m-d')),
        ]);

        $scheduledPayment->update(['is_completed' => true]);

        return redirect()->back()->with('success', 'Zahlung als erledigt markiert und Buchung erstellt.');
    }
}
