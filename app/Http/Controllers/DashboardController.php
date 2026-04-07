<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Services\AmortizationService;
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
            ->whereDoesntHave('category', fn ($q) => $q->where('type', 'transfer'))
            ->groupByRaw("strftime('%Y-%m', date)")
            ->orderBy('month')
            ->get();

        $monthStart = $selectedDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedDate->copy()->endOfMonth()->toDateString();

        $categoryData = Transaction::select('categories.name', DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.amount', '<', 0)
            ->where('categories.type', '!=', 'transfer')
            ->whereBetween('transactions.date', [$monthStart, $monthEnd])
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
        $accounts = Account::activeOrdered()
            ->withSum('transactions', 'amount')
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

        // Loans summary
        $loansSummary = null;
        $loans = Loan::with('payments')->get();
        if ($loans->isNotEmpty()) {
            $amortization = new AmortizationService;
            $owedByMe = ['principal' => 0, 'remaining' => 0, 'count' => 0];
            $owedToMe = ['principal' => 0, 'remaining' => 0, 'count' => 0];

            foreach ($loans as $loan) {
                $summary = $amortization->calculateSummary($loan);
                if ($loan->direction === 'owed_by_me') {
                    $owedByMe['principal'] += (float) $loan->principal;
                    $owedByMe['remaining'] += $summary['remainingBalance'];
                    $owedByMe['count']++;
                } else {
                    $owedToMe['principal'] += (float) $loan->principal;
                    $owedToMe['remaining'] += $summary['remainingBalance'];
                    $owedToMe['count']++;
                }
            }

            $loansSummary = [
                'owedByMe' => [
                    'count' => $owedByMe['count'],
                    'totalPrincipal' => round($owedByMe['principal'], 2),
                    'totalRemaining' => round($owedByMe['remaining'], 2),
                    'progressPercent' => $owedByMe['principal'] > 0
                        ? round((($owedByMe['principal'] - $owedByMe['remaining']) / $owedByMe['principal']) * 100, 1)
                        : 0,
                ],
                'owedToMe' => [
                    'count' => $owedToMe['count'],
                    'totalPrincipal' => round($owedToMe['principal'], 2),
                    'totalRemaining' => round($owedToMe['remaining'], 2),
                    'progressPercent' => $owedToMe['principal'] > 0
                        ? round((($owedToMe['principal'] - $owedToMe['remaining']) / $owedToMe['principal']) * 100, 1)
                        : 0,
                ],
            ];
        }

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
            'loansSummary' => $loansSummary,
        ]);
    }
}
