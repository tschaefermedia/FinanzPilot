<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private Account $checking;

    private Account $paypal;

    private Category $transferCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checking = Account::create(['name' => 'Giro', 'type' => 'checking']);
        $this->paypal = Account::create(['name' => 'PayPal', 'type' => 'other']);
        $this->transferCategory = Category::create(['name' => 'Übertragungen', 'type' => 'transfer']);
    }

    private function transaction(Account $account, float $amount, string $date = '2026-03-10', array $attributes = []): Transaction
    {
        return Transaction::create([
            'date' => $date,
            'amount' => $amount,
            'description' => 'PayPal Aufladung',
            'account_id' => $account->id,
            ...$attributes,
        ]);
    }

    public function test_linking_pairs_both_legs_and_assigns_the_transfer_category(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50, '2026-03-11');

        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($incoming->id, $outgoing->fresh()->transfer_transaction_id);
        $this->assertSame($outgoing->id, $incoming->fresh()->transfer_transaction_id);
        $this->assertSame($this->transferCategory->id, $outgoing->fresh()->category_id);
        $this->assertSame($this->transferCategory->id, $incoming->fresh()->category_id);
    }

    public function test_linked_transfer_is_excluded_from_dashboard_income_and_expenses(): void
    {
        $this->travelTo('2026-03-20');
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50);
        $this->transaction($this->checking, 1000, attributes: ['description' => 'Gehalt']);

        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.income', 1000)
            ->where('stats.expenses', 0)
        );
    }

    public function test_linking_rejects_invalid_pairs(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $sameAccount = $this->transaction($this->checking, 50);
        $wrongAmount = $this->transaction($this->paypal, 49.99);

        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $sameAccount->id])
            ->assertSessionHasErrors(['transfer' => 'Die Buchungen müssen auf unterschiedlichen Konten liegen.']);
        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $wrongAmount->id])
            ->assertSessionHasErrors(['transfer' => 'Die Beträge müssen sich genau aufheben (z. B. -100 € und +100 €).']);

        $this->assertNull($outgoing->fresh()->transfer_transaction_id);
    }

    public function test_candidates_match_opposite_amount_on_other_accounts_within_five_days(): void
    {
        $outgoing = $this->transaction($this->checking, -50, '2026-03-10');
        $nearest = $this->transaction($this->paypal, 50, '2026-03-11');
        $further = $this->transaction($this->paypal, 50, '2026-03-14');
        $this->transaction($this->paypal, 50, '2026-03-16');
        $this->transaction($this->paypal, 40, '2026-03-10');
        $this->transaction($this->checking, 50, '2026-03-10');

        $this->getJson("/transactions/{$outgoing->id}/transfer-candidates")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $nearest->id)
            ->assertJsonPath('1.id', $further->id)
            ->assertJsonPath('0.account', 'PayPal');
    }

    public function test_creating_a_transfer_writes_both_linked_legs(): void
    {
        $this->post('/transfers', [
            'from_account_id' => $this->checking->id,
            'to_account_id' => $this->paypal->id,
            'amount' => 75.5,
            'date' => '2026-03-10',
            'description' => '',
        ])->assertSessionHasNoErrors();

        $outgoing = Transaction::where('account_id', $this->checking->id)->sole();
        $incoming = Transaction::where('account_id', $this->paypal->id)->sole();

        $this->assertSame('-75.50', $outgoing->amount);
        $this->assertSame('75.50', $incoming->amount);
        $this->assertSame('Umbuchung', $outgoing->description);
        $this->assertSame($incoming->id, $outgoing->transfer_transaction_id);
        $this->assertSame($outgoing->id, $incoming->transfer_transaction_id);
    }

    public function test_creating_a_transfer_requires_different_accounts(): void
    {
        $this->post('/transfers', [
            'from_account_id' => $this->checking->id,
            'to_account_id' => $this->checking->id,
            'amount' => 10,
            'date' => '2026-03-10',
        ])->assertSessionHasErrors('to_account_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_unlinking_clears_both_legs_and_their_transfer_category(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50);
        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id]);

        $this->delete("/transactions/{$incoming->id}/transfer-link")->assertSessionHasNoErrors();

        foreach ([$outgoing->fresh(), $incoming->fresh()] as $leg) {
            $this->assertNull($leg->transfer_transaction_id);
            $this->assertNull($leg->category_id);
        }
    }

    public function test_deleting_one_leg_unlinks_the_other(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50);
        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id]);

        $this->delete("/transactions/{$outgoing->id}");

        $this->assertSoftDeleted($outgoing);
        $this->assertNull($incoming->fresh()->transfer_transaction_id);
    }

    public function test_editing_a_linked_leg_cannot_break_the_pair(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50);
        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id]);

        $unchanged = [
            'date' => '2026-03-12',
            'amount' => -50,
            'description' => 'Neue Beschreibung',
            'category_id' => $this->transferCategory->id,
            'account_id' => $this->checking->id,
        ];

        $this->put("/transactions/{$outgoing->id}", [...$unchanged, 'amount' => -60])->assertSessionHasErrors('transfer');
        $this->put("/transactions/{$outgoing->id}", [...$unchanged, 'account_id' => null])->assertSessionHasErrors('transfer');
        $this->put("/transactions/{$outgoing->id}", [...$unchanged, 'category_id' => null])->assertSessionHasErrors('transfer');

        $this->put("/transactions/{$outgoing->id}", $unchanged)->assertSessionHasNoErrors();
        $this->assertSame('Neue Beschreibung', $outgoing->fresh()->description);
    }

    public function test_bulk_account_update_rejects_linked_legs(): void
    {
        $outgoing = $this->transaction($this->checking, -50);
        $incoming = $this->transaction($this->paypal, 50);
        $this->post("/transactions/{$outgoing->id}/transfer-link", ['counterpart_id' => $incoming->id]);

        $this->post('/transactions/bulk-update-account', [
            'transaction_ids' => [$outgoing->id],
            'account_id' => $this->paypal->id,
        ])->assertSessionHasErrors('transaction_ids');

        $this->assertSame($this->checking->id, $outgoing->fresh()->account_id);
    }
}
