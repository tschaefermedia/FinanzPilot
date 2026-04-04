<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $now = now()->format('Y-m');
        $selectedMonth = $request->query('month');

        if (! $selectedMonth || ! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = $now;
        }

        $firstMonth = Transaction::selectRaw("strftime('%Y-%m', MIN(date)) as m")->value('m');
        $lastMonth = Transaction::selectRaw("strftime('%Y-%m', MAX(date)) as m")->value('m');

        if (! $firstMonth) {
            $firstMonth = $now;
            $lastMonth = $now;
        }

        if ($lastMonth > $now) {
            $lastMonth = $now;
        }

        $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $prevMonth = $selectedMonth > $firstMonth ? $selectedDate->copy()->subMonth()->format('Y-m') : null;
        $nextMonth = $selectedMonth < $lastMonth ? $selectedDate->copy()->addMonth()->format('Y-m') : null;

        $currentMonth = $selectedMonth;

        $monthlyData = Transaction::selectRaw("
                strftime('%Y-%m', date) as month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
            ")
            ->where('date', '>=', now()->subMonths(12)->startOfMonth()->toDateString())
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderBy('month')
            ->get();

        $categoryData = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->whereRaw("strftime('%Y-%m', transactions.date) = ?", [$currentMonth])
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $runningBalance = 0;
        $balanceData = $monthlyData->map(function ($month) use (&$runningBalance) {
            $runningBalance += $month->income - $month->expenses;

            return [
                'month' => $month->month,
                'balance' => round($runningBalance, 2),
            ];
        })->values();

        $currentMonthData = $monthlyData->firstWhere('month', $currentMonth);
        $income = $currentMonthData?->income ?? 0;
        $expenses = $currentMonthData?->expenses ?? 0;
        $balance = $income - $expenses;
        $savingsRate = $income > 0 ? round(($balance / $income) * 100, 1) : 0;

        // Account balances
        $accounts = Account::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'icon' => $account->icon,
                    'color' => $account->color,
                    'current_balance' => $account->current_balance,
                ];
            });

        $totalBalance = $accounts->sum('current_balance');

        return Inertia::render('Dashboard', [
            'selectedMonth' => $selectedMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'accounts' => $accounts,
            'totalBalance' => round($totalBalance, 2),
            'stats' => [
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'balance' => round($balance, 2),
                'savingsRate' => $savingsRate,
            ],
            'monthlyData' => $monthlyData->map(fn ($m) => [
                'month' => $m->month,
                'income' => round($m->income, 2),
                'expenses' => round($m->expenses, 2),
            ])->values(),
            'categoryData' => $categoryData->map(fn ($c) => [
                'name' => $c->name,
                'total' => round($c->total, 2),
            ])->values(),
            'balanceData' => $balanceData,
        ]);
    }
}
