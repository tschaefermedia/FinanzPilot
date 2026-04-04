<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\ImportBatch;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\RecurringTemplate;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe all existing data
        LoanPayment::truncate();
        Loan::truncate();
        Transaction::truncate();
        RecurringTemplate::truncate();
        CategoryRule::truncate();
        ImportBatch::truncate();
        Account::truncate();
        Category::truncate();
        Setting::truncate();
        User::truncate();

        // Re-seed base data
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
        ]);

        // Look up category IDs by name
        $cat = fn (string $name) => Category::where('name', $name)->first()->id;

        // ── Accounts ──────────────────────────────────────────────
        $checking = Account::create([
            'name' => 'Sparkasse Girokonto',
            'type' => 'checking',
            'starting_balance' => 2450.00,
            'currency' => 'EUR',
            'icon' => 'pi-building-columns',
            'color' => '#E63946',
            'sort_order' => 0,
        ]);

        $savings = Account::create([
            'name' => 'Tagesgeld ING',
            'type' => 'savings',
            'starting_balance' => 12800.00,
            'currency' => 'EUR',
            'icon' => 'pi-piggy-bank',
            'color' => '#2A9D8F',
            'sort_order' => 1,
        ]);

        $credit = Account::create([
            'name' => 'Barclays Visa',
            'type' => 'credit_card',
            'starting_balance' => 0.00,
            'currency' => 'EUR',
            'icon' => 'pi-credit-card',
            'color' => '#457B9D',
            'sort_order' => 2,
        ]);

        $cash = Account::create([
            'name' => 'Bargeld',
            'type' => 'cash',
            'starting_balance' => 950.00,
            'currency' => 'EUR',
            'icon' => 'pi-wallet',
            'color' => '#E9C46A',
            'sort_order' => 3,
        ]);

        // ── 12 months of transactions ────────────────────────────
        $now = Carbon::now();

        for ($m = 11; $m >= 0; $m--) {
            $month = $now->copy()->subMonths($m);
            $monthStr = $month->format('Y-m');
            $daysInMonth = $month->daysInMonth;

            // Salary — income
            $salaryBase = $m < 6 ? 3850.00 : 3650.00; // raise 6 months ago
            $salary = $salaryBase + rand(-20, 20);
            $this->tx($checking, $month->copy()->day(28), $salary, 'Gehalt '.$month->translatedFormat('F Y'), 'Arbeitgeber GmbH', $cat('Gehalt'), 'recurring');

            // Side income — roughly every other month
            if ($m % 2 === 0) {
                $this->tx($checking, $month->copy()->day(rand(5, 20)), rand(150, 450) + rand(0, 99) / 100, 'Freelance Webentwicklung', 'Kunde Müller', $cat('Nebeneinkommen'));
            }

            // Rent
            $this->tx($checking, $month->copy()->day(1), -920.00, 'Miete Wohnung', 'Hausverwaltung Schmidt', $cat('Miete'), 'recurring');

            // Utilities — quarterly in Jan, Apr, Jul, Oct
            if (in_array($month->month, [1, 4, 7, 10])) {
                $this->tx($checking, $month->copy()->day(15), -rand(180, 240) * 1.0, 'Nebenkosten Q'.ceil($month->month / 3), 'Hausverwaltung Schmidt', $cat('Nebenkosten'));
            }

            // Electricity
            $this->tx($checking, $month->copy()->day(5), -68.50, 'Stadtwerke Strom', 'Stadtwerke München', $cat('Strom'), 'recurring');

            // Internet
            $this->tx($checking, $month->copy()->day(3), -39.99, 'Vodafone Internet', 'Vodafone GmbH', $cat('Internet'), 'recurring');

            // Groceries — 8-12 transactions per month across accounts
            $groceryStores = ['REWE', 'EDEKA', 'Aldi Süd', 'Lidl', 'dm Drogerie', 'Netto'];
            $groceryCount = rand(8, 12);
            for ($i = 0; $i < $groceryCount; $i++) {
                $store = $groceryStores[array_rand($groceryStores)];
                $day = min(rand(1, $daysInMonth), $daysInMonth);
                $amount = -(rand(1200, 8500) / 100);
                $acct = $i % 3 === 0 ? $credit : ($i % 5 === 0 ? $cash : $checking);
                $this->tx($acct, $month->copy()->day($day), $amount, $store.' Einkauf', $store, $cat('Supermarkt'));
            }

            // Restaurants — 2-4 per month
            $restaurants = ['Bella Italia', 'Zum Goldenen Hirsch', 'Asia Wok', 'Café Luitpold', 'Burger House'];
            for ($i = 0; $i < rand(2, 4); $i++) {
                $place = $restaurants[array_rand($restaurants)];
                $day = min(rand(1, $daysInMonth), $daysInMonth);
                $this->tx($credit, $month->copy()->day($day), -(rand(1500, 5500) / 100), $place, $place, $cat('Restaurant'));
            }

            // Food delivery — 1-2 per month
            if (rand(0, 1)) {
                $this->tx($credit, $month->copy()->day(rand(1, min(28, $daysInMonth))), -(rand(1800, 3200) / 100), 'Lieferando Bestellung', 'Lieferando', $cat('Lieferdienst'));
            }

            // Public transport
            $this->tx($checking, $month->copy()->day(1), -59.00, 'Deutschlandticket', 'MVV München', $cat('ÖPNV'), 'recurring');

            // Fuel — 1-2 per month
            for ($i = 0; $i < rand(1, 2); $i++) {
                $day = min(rand(1, $daysInMonth), $daysInMonth);
                $this->tx($credit, $month->copy()->day($day), -(rand(5500, 8500) / 100), 'Tankstelle '.['Aral', 'Shell', 'Total'][rand(0, 2)], null, $cat('Tanken'));
            }

            // Health — occasional
            if (rand(0, 2) === 0) {
                $this->tx($checking, $month->copy()->day(rand(5, 25)), -(rand(15, 80) * 1.0), 'Praxis Dr. Weber', 'Dr. Weber', $cat('Arzt'));
            }
            if (rand(0, 3) === 0) {
                $this->tx($cash, $month->copy()->day(rand(5, 25)), -(rand(500, 2500) / 100), 'Apotheke am Markt', 'Apotheke am Markt', $cat('Apotheke'));
            }

            // Health insurance
            $this->tx($checking, $month->copy()->day(15), -289.50, 'Techniker Krankenkasse', 'TK', $cat('Krankenversicherung'), 'recurring');

            // Sports/gym
            $this->tx($checking, $month->copy()->day(1), -34.90, 'FitX Mitgliedschaft', 'FitX GmbH', $cat('Sport'), 'recurring');

            // Culture — occasional
            if (rand(0, 2) === 0) {
                $events = ['Kino Mathäser', 'Staatsoper Tickets', 'Deutsches Museum', 'Konzert Olympiahalle'];
                $event = $events[array_rand($events)];
                $this->tx($credit, $month->copy()->day(rand(10, min(28, $daysInMonth))), -(rand(1200, 6500) / 100), $event, null, $cat('Kultur'));
            }

            // Hobbies — occasional
            if (rand(0, 3) === 0) {
                $this->tx($credit, $month->copy()->day(rand(5, 25)), -(rand(2000, 8000) / 100), 'Amazon Marketplace', 'Amazon', $cat('Hobbys'));
            }

            // Clothing — occasional
            if (rand(0, 2) === 0) {
                $shops = ['Zalando', 'H&M München', 'Uniqlo', 'About You'];
                $shop = $shops[array_rand($shops)];
                $this->tx($credit, $month->copy()->day(rand(5, min(28, $daysInMonth))), -(rand(3000, 12000) / 100), $shop, $shop, $cat('Kleidung'));
            }

            // Insurance — monthly
            $this->tx($checking, $month->copy()->day(1), -8.50, 'Haftpflichtversicherung', 'HUK-COBURG', $cat('Haftpflicht'), 'recurring');
            $this->tx($checking, $month->copy()->day(1), -12.80, 'Hausratversicherung', 'HUK-COBURG', $cat('Hausrat'), 'recurring');
            $this->tx($checking, $month->copy()->day(1), -48.00, 'Berufsunfähigkeitsversicherung', 'Allianz', $cat('Berufsunfähigkeit'), 'recurring');

            // Savings transfer — monthly
            $savingsAmount = $m < 6 ? 500.00 : 400.00;
            $this->tx($checking, $month->copy()->day($daysInMonth < 29 ? $daysInMonth : 29), -$savingsAmount, 'Sparplan Tagesgeld', null, $cat('Tagesgeld'));
            $this->tx($savings, $month->copy()->day($daysInMonth < 29 ? $daysInMonth : 29), $savingsAmount, 'Eingang Sparplan', null, $cat('Tagesgeld'));

            // ETF savings plan
            $this->tx($checking, $month->copy()->day(15), -250.00, 'ETF Sparplan MSCI World', 'Scalable Capital', $cat('ETF'), 'recurring');

            // Vacation — one big expense in July/August
            if ($month->month === 7 && $m > 0) {
                $this->tx($credit, $month->copy()->day(10), -1250.00, 'Flug Barcelona', 'Lufthansa', $cat('Urlaub'));
                $this->tx($credit, $month->copy()->day(11), -890.00, 'Hotel Barcelona 5 Nächte', 'Booking.com', $cat('Urlaub'));
            }

            // Car insurance — quarterly
            if (in_array($month->month, [1, 4, 7, 10])) {
                $this->tx($checking, $month->copy()->day(10), -185.00, 'KFZ-Versicherung', 'HUK-COBURG', $cat('KFZ-Versicherung'));
            }

            // Car maintenance — twice a year
            if ($month->month === 3) {
                $this->tx($checking, $month->copy()->day(18), -420.00, 'TÜV + Inspektion', 'ATU München', $cat('Werkstatt'));
            }
            if ($month->month === 9) {
                $this->tx($checking, $month->copy()->day(12), -85.00, 'Reifenwechsel', 'Vergölst', $cat('Werkstatt'));
            }

            // Credit card settlement — pay off credit card monthly
            $creditSpend = Transaction::where('account_id', $credit->id)
                ->whereRaw("strftime('%Y-%m', date) = ?", [$monthStr])
                ->where('amount', '<', 0)
                ->sum('amount');

            if ($creditSpend < 0) {
                $settlementDay = min(28, $daysInMonth);
                $this->tx($checking, $month->copy()->day($settlementDay), $creditSpend, 'Kreditkartenabrechnung', 'Barclays', $cat('Übertragungen'));
                $this->tx($credit, $month->copy()->day($settlementDay), abs($creditSpend), 'Zahlung eingegangen', 'Girokonto', $cat('Übertragungen'));
            }
        }

        // ── Loans ─────────────────────────────────────────────────
        $carLoan = Loan::create([
            'name' => 'Autokredit VW Golf',
            'type' => 'bank',
            'principal' => 15000.00,
            'interest_rate' => 3.49,
            'start_date' => $now->copy()->subMonths(18)->startOfMonth(),
            'term_months' => 48,
            'payment_day' => 5,
            'direction' => 'owed_by_me',
            'notes' => 'VW Bank Autofinanzierung, monatliche Rate 340,52 €',
        ]);

        // Loan payments for the last 12 months
        for ($m = 11; $m >= 0; $m--) {
            $payDate = $now->copy()->subMonths($m)->day(5);
            $tx = $this->tx($checking, $payDate, -340.52, 'Autokredit Rate', 'VW Bank', $cat('Mobilität'));
            LoanPayment::create([
                'loan_id' => $carLoan->id,
                'transaction_id' => $tx->id,
                'date' => $payDate,
                'amount' => 340.52,
                'type' => 'scheduled',
            ]);
        }

        $friendLoan = Loan::create([
            'name' => 'Darlehen an Thomas',
            'type' => 'informal',
            'principal' => 2000.00,
            'interest_rate' => 0,
            'start_date' => $now->copy()->subMonths(4)->startOfMonth(),
            'direction' => 'owed_to_me',
            'notes' => 'Thomas braucht Überbrückung bis neuer Job startet',
        ]);

        // Two repayments received so far
        $repay1 = $this->tx($checking, $now->copy()->subMonths(2)->day(15), 500.00, 'Rückzahlung Thomas', 'Thomas K.', $cat('Sonstiges'));
        LoanPayment::create([
            'loan_id' => $friendLoan->id,
            'transaction_id' => $repay1->id,
            'date' => $now->copy()->subMonths(2)->day(15),
            'amount' => 500.00,
            'type' => 'manual',
        ]);

        $repay2 = $this->tx($checking, $now->copy()->subMonths(0)->day(3), 500.00, 'Rückzahlung Thomas', 'Thomas K.', $cat('Sonstiges'));
        LoanPayment::create([
            'loan_id' => $friendLoan->id,
            'transaction_id' => $repay2->id,
            'date' => $now->copy()->subMonths(0)->day(3),
            'amount' => 500.00,
            'type' => 'manual',
        ]);

        // ── Recurring Templates ───────────────────────────────────
        RecurringTemplate::create([
            'description' => 'Gehalt',
            'amount' => 3850.00,
            'category_id' => $cat('Gehalt'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(28),
            'is_active' => true,
            'auto_generate' => true,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'Miete Wohnung',
            'amount' => -920.00,
            'category_id' => $cat('Miete'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(1),
            'is_active' => true,
            'auto_generate' => true,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'Vodafone Internet',
            'amount' => -39.99,
            'category_id' => $cat('Internet'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(3),
            'is_active' => true,
            'auto_generate' => true,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'ETF Sparplan MSCI World',
            'amount' => -250.00,
            'category_id' => $cat('ETF'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(15),
            'is_active' => true,
            'auto_generate' => true,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'Deutschlandticket',
            'amount' => -59.00,
            'category_id' => $cat('ÖPNV'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(1),
            'is_active' => true,
            'auto_generate' => false,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'Spotify Premium Familie',
            'amount' => -17.99,
            'category_id' => $cat('Hobbys'),
            'frequency' => 'monthly',
            'next_due_date' => $now->copy()->addMonth()->day(12),
            'is_active' => true,
            'auto_generate' => true,
            'account_id' => $checking->id,
        ]);

        RecurringTemplate::create([
            'description' => 'KFZ-Versicherung',
            'amount' => -185.00,
            'category_id' => $cat('KFZ-Versicherung'),
            'frequency' => 'quarterly',
            'next_due_date' => $now->copy()->addQuarter()->startOfQuarter()->day(10),
            'is_active' => true,
            'auto_generate' => false,
            'account_id' => $checking->id,
        ]);

        // ── Category Rules ────────────────────────────────────────
        $rules = [
            ['pattern' => 'REWE', 'target' => 'Supermarkt', 'hits' => 34],
            ['pattern' => 'EDEKA', 'target' => 'Supermarkt', 'hits' => 21],
            ['pattern' => 'Aldi', 'target' => 'Supermarkt', 'hits' => 18],
            ['pattern' => 'Lidl', 'target' => 'Supermarkt', 'hits' => 15],
            ['pattern' => 'Netto', 'target' => 'Supermarkt', 'hits' => 8],
            ['pattern' => 'dm Drogerie', 'target' => 'Supermarkt', 'hits' => 12],
            ['pattern' => 'Lieferando', 'target' => 'Lieferdienst', 'hits' => 7],
            ['pattern' => 'Stadtwerke', 'target' => 'Strom', 'hits' => 12],
            ['pattern' => 'Vodafone', 'target' => 'Internet', 'hits' => 12],
            ['pattern' => 'Hausverwaltung', 'target' => 'Miete', 'hits' => 12],
            ['pattern' => 'Deutschlandticket', 'target' => 'ÖPNV', 'hits' => 12],
            ['pattern' => 'Amazon', 'target' => 'Hobbys', 'hits' => 5],
            ['pattern' => 'Zalando', 'target' => 'Kleidung', 'hits' => 4],
            ['pattern' => 'HUK-COBURG', 'target' => 'Haftpflicht', 'hits' => 12],
            ['pattern' => 'VW Bank', 'target' => 'Mobilität', 'hits' => 12],
            ['pattern' => 'Scalable Capital', 'target' => 'ETF', 'hits' => 12],
            ['pattern' => 'Techniker Krankenkasse', 'target' => 'Krankenversicherung', 'hits' => 12],
        ];

        foreach ($rules as $priority => $rule) {
            CategoryRule::create([
                'pattern' => $rule['pattern'],
                'is_regex' => false,
                'target_category_id' => $cat($rule['target']),
                'priority' => $priority,
                'confidence' => 0.95,
                'hit_count' => $rule['hits'],
            ]);
        }

        // ── Import Batch (to show import history) ─────────────────
        ImportBatch::create([
            'filename' => 'sparkasse_export_2026_q1.csv',
            'source_type' => 'sparkasse',
            'uploaded_at' => $now->copy()->subWeeks(2),
            'row_count' => 47,
            'status' => 'committed',
        ]);

        ImportBatch::create([
            'filename' => 'paypal_activities_march.csv',
            'source_type' => 'paypal',
            'uploaded_at' => $now->copy()->subWeeks(1),
            'row_count' => 12,
            'status' => 'committed',
        ]);
    }

    private function tx(Account $account, Carbon $date, float $amount, string $description, ?string $counterparty, int $categoryId, string $source = 'manual'): Transaction
    {
        return Transaction::create([
            'date' => $date->toDateString(),
            'amount' => round($amount, 2),
            'description' => $description,
            'counterparty' => $counterparty,
            'category_id' => $categoryId,
            'source' => $source,
            'account_id' => $account->id,
        ]);
    }
}
