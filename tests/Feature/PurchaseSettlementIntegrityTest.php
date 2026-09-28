<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseSettlement;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Commerce\PurchaseReceiptReversalService;
use App\Services\Commerce\PurchaseService;
use App\Services\Commerce\PurchaseSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseSettlementIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_received_value_drives_payable_and_payments_are_idempotent_and_bounded(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 5, 10);
        app(PurchaseService::class)->receivePartial(
            $purchase,
            [$item->id => 2],
            (string) Str::uuid()
        );

        $service = app(PurchaseSettlementService::class);
        $this->assertSame([
            'payable' => '20.00',
            'paid' => '0.00',
            'balance' => '20.00',
            'status' => 'unpaid',
        ], $service->summary($purchase));

        $key = (string) Str::uuid();
        $this->assertTrue($service->record($purchase, '7.50', 'bank_transfer', 'BANK-1', $key));
        $this->assertFalse($service->record($purchase, '7.50', 'bank_transfer', 'BANK-1', $key));

        $this->assertSame([
            'payable' => '20.00',
            'paid' => '7.50',
            'balance' => '12.50',
            'status' => 'partially_paid',
        ], $service->summary($purchase));
        try {
            $service->record($purchase, '12.51', 'cash', null, (string) Str::uuid());
            $this->fail('Supplier overpayment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseCount('purchase_settlements', 1);
    }

    public function test_supplier_payment_service_rejects_non_decimal_or_over_precision_amounts(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase);
        $service = app(PurchaseSettlementService::class);

        foreach (['5.009', '1e1', '10000000000.00'] as $invalidAmount) {
            try {
                $service->record(
                    $purchase,
                    $invalidAmount,
                    'cash',
                    null,
                    (string) Str::uuid()
                );
                $this->fail('Invalid supplier payment precision or format should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }

        $this->assertDatabaseCount('purchase_settlements', 0);
    }

    public function test_supplier_settlement_service_enforces_reference_and_void_reason_storage_bounds(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase);
        $service = app(PurchaseSettlementService::class);

        try {
            $service->record(
                $purchase,
                '5.00',
                'cash',
                str_repeat('R', 101),
                (string) Str::uuid()
            );
            $this->fail('Supplier payment reference must respect the database length boundary.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reference', $exception->errors());
        }

        $this->assertTrue($service->record(
            $purchase,
            '5.00',
            'cash',
            'REF-OK',
            (string) Str::uuid()
        ));
        $settlement = PurchaseSettlement::query()->firstOrFail();

        try {
            $service->void($purchase, $settlement, str_repeat('V', 1001));
            $this->fail('Supplier payment void reason must respect the database length boundary.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('void_reason', $exception->errors());
        }

        $this->assertSame(PurchaseSettlement::STATUS_ACTIVE, $settlement->fresh()->status);
    }

    public function test_operational_supplier_payable_excludes_purchase_shipping_and_tax(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $purchase->update([
            'shipping_total' => '8.00',
            'tax_total' => '2.00',
            'subtotal' => '20.00',
            'grand_total' => '30.00',
        ]);
        $item = $this->item($purchase, $product, 2, 10);

        app(PurchaseService::class)->receive($purchase);

        $summary = app(PurchaseSettlementService::class)->summary($purchase);

        $this->assertSame('20.00', $summary['payable']);
        $this->assertSame('20.00', $summary['balance']);
        $this->assertSame('unpaid', $summary['status']);
        $this->assertNotSame($purchase->grand_total, $summary['payable']);
    }

    public function test_supplier_payment_effective_date_cannot_precede_receipt_or_be_in_future_and_idempotency_includes_date(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase);

        $receiptAt = $purchase->receipts()->latest('id')->value('received_at');
        $service = app(PurchaseSettlementService::class);

        try {
            $service->record(
                $purchase,
                '5.00',
                'cash',
                'PAST-DATE',
                (string) Str::uuid(),
                \Illuminate\Support\Carbon::parse($receiptAt)->subSecond()->toDateTimeString()
            );
            $this->fail('Supplier payment cannot predate the first active receipt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paid_at', $exception->errors());
        }

        try {
            $service->record(
                $purchase,
                '5.00',
                'cash',
                'FUTURE-DATE',
                (string) Str::uuid(),
                now()->addDay()->toDateTimeString()
            );
            $this->fail('Supplier payment cannot be dated in the future.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paid_at', $exception->errors());
        }

        $key = (string) Str::uuid();
        $validPaidAt = now()->toDateTimeString();
        $this->assertTrue($service->record($purchase, '5.00', 'cash', 'DATE-IDEM', $key, $validPaidAt));
        $this->assertFalse($service->record($purchase, '5.00', 'cash', 'DATE-IDEM', $key, $validPaidAt));

        try {
            $service->record(
                $purchase,
                '5.00',
                'cash',
                'DATE-IDEM',
                $key,
                now()->subSecond()->toDateTimeString()
            );
            $this->fail('The same supplier payment key cannot be replayed with a different effective date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settlement_key', $exception->errors());
        }
    }

    public function test_backdated_supplier_payment_is_bounded_by_received_value_available_on_that_date(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 3, 10);
        $service = app(PurchaseService::class);

        Carbon::setTestNow('2026-09-10 10:00:00');
        $service->receivePartial($purchase, [$item->id => 1], (string) Str::uuid());

        Carbon::setTestNow('2026-09-20 10:00:00');
        $service->receivePartial($purchase, [$item->id => 2], (string) Str::uuid());

        $settlements = app(PurchaseSettlementService::class);

        try {
            $settlements->record(
                $purchase,
                '25.00',
                'bank_transfer',
                'BACKDATED-OVER',
                (string) Str::uuid(),
                '2026-09-15 12:00:00'
            );
            $this->fail('Backdated payment must not use receipts that happened after its effective date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertTrue($settlements->record(
            $purchase,
            '10.00',
            'bank_transfer',
            'BACKDATED-OK',
            (string) Str::uuid(),
            '2026-09-15 12:00:00'
        ));

        $this->assertSame('30.00', $settlements->summary($purchase)['payable']);
        $this->assertSame('20.00', $settlements->summary($purchase)['balance']);
    }

    public function test_backdated_supplier_payment_respects_settlements_that_were_active_on_that_date(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        $purchaseService = app(PurchaseService::class);
        $settlements = app(PurchaseSettlementService::class);

        Carbon::setTestNow('2026-09-10 09:00:00');
        $purchaseService->receive($purchase);
        Carbon::setTestNow('2026-09-10 11:00:00');
        $this->assertTrue($settlements->record(
            $purchase,
            '15.00',
            'bank_transfer',
            'HISTORICAL-ACTIVE',
            (string) Str::uuid(),
            '2026-09-10 10:00:00'
        ));

        $settlement = PurchaseSettlement::query()->firstOrFail();

        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->assertTrue($settlements->void($purchase, $settlement, 'Correcting supplier payment history.'));

        try {
            $settlements->record(
                $purchase,
                '10.00',
                'bank_transfer',
                'BACKDATED-AFTER-VOID',
                (string) Str::uuid(),
                '2026-09-15 12:00:00'
            );
            $this->fail('Backdated supplier payment must count settlements that were still active on its effective date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseCount('purchase_settlements', 1);
    }

    public function test_backdated_supplier_payment_uses_receipts_that_were_active_on_that_date_even_if_reversed_later(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 3, 10);
        $purchaseService = app(PurchaseService::class);
        $reversalService = app(PurchaseReceiptReversalService::class);
        $settlements = app(PurchaseSettlementService::class);

        Carbon::setTestNow('2026-09-10 10:00:00');
        $purchaseService->receivePartial($purchase, [$item->id => 1], (string) Str::uuid());
        $firstReceipt = PurchaseReceipt::query()->where('purchase_id', $purchase->id)->latest('id')->firstOrFail();

        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->assertTrue($reversalService->reverse(
            $purchase,
            $firstReceipt,
            'Reverse first delivery before replacement receipt.'
        ));

        Carbon::setTestNow('2026-09-25 10:00:00');
        $purchaseService->receivePartial($purchase, [$item->id => 2], (string) Str::uuid());

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->assertTrue($settlements->record(
            $purchase,
            '10.00',
            'bank_transfer',
            'HISTORICAL-RECEIPT',
            (string) Str::uuid(),
            '2026-09-15 12:00:00'
        ));

        $summary = $settlements->summary($purchase);
        $this->assertSame('20.00', $summary['payable']);
        $this->assertSame('10.00', $summary['paid']);
        $this->assertSame('10.00', $summary['balance']);
    }

    public function test_supplier_payment_is_not_allowed_before_goods_are_received(): void
    {
        $purchase = $this->purchase();
        $product = $this->product();
        $this->item($purchase, $product, 2, 10);

        try {
            app(PurchaseSettlementService::class)->record(
                $purchase,
                '5.00',
                'cash',
                null,
                (string) Str::uuid()
            );
            $this->fail('A purchase without received value must not be payable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
    }
    public function test_voided_payment_reopens_balance_without_deleting_ledger(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase);

        $service = app(PurchaseSettlementService::class);
        $service->record($purchase, '20.00', 'cash', 'CASH-1', (string) Str::uuid());
        $settlement = PurchaseSettlement::query()->firstOrFail();

        $this->assertSame('paid', $service->summary($purchase)['status']);
        $this->assertTrue($service->void($purchase, $settlement, 'Entry recorded against the wrong cash drawer.'));
        $this->assertFalse($service->void($purchase, $settlement, 'Duplicate void.'));

        $fresh = $settlement->fresh();
        $this->assertSame(PurchaseSettlement::STATUS_VOIDED, $fresh->status);
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame('unpaid', $service->summary($purchase)['status']);
        $this->assertDatabaseCount('purchase_settlements', 1);
    }
    public function test_receipt_reversal_is_blocked_when_it_would_make_supplier_payments_exceed_received_value(): void
    {
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 3, 10);
        $purchaseService = app(PurchaseService::class);

        $purchaseService->receivePartial($purchase, [$item->id => 2], (string) Str::uuid());
        $purchaseService->receivePartial($purchase, [$item->id => 1], (string) Str::uuid());

        $receipts = $purchase->receipts()->orderBy('id')->get();
        $latestReceipt = $receipts->last();

        $settlements = app(PurchaseSettlementService::class);
        $settlements->record($purchase, '25.00', 'bank_transfer', 'BANK-25', (string) Str::uuid());
        $payment = PurchaseSettlement::query()->firstOrFail();

        try {
            app(PurchaseReceiptReversalService::class)->reverse(
                $purchase,
                $latestReceipt,
                'Latest delivery was incorrect.'
            );
            $this->fail('Receipt reversal should be blocked while active supplier payments exceed remaining received value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt', $exception->errors());
        }
        $this->assertNull($latestReceipt->fresh()->reversed_at);
        $this->assertSame(3, (int) $item->fresh()->received_quantity);

        $settlements->void($purchase, $payment, 'Supplier payment must be corrected before receipt reversal.');

        $this->assertTrue(app(PurchaseReceiptReversalService::class)->reverse(
            $purchase,
            $latestReceipt,
            'Latest delivery was incorrect.'
        ));
        $this->assertSame(2, (int) $item->fresh()->received_quantity);
        $this->assertSame('20.00', $settlements->summary($purchase)['payable']);
    }

    public function test_supplier_settlement_permissions_separate_finance_from_inventory_operations(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $operations = $this->staffWithRole('operations_manager');
        $finance = $this->staffWithRole('finance_manager');

        $this->assertFalse($operations->hasPermission('purchasing.settlements.manage'));
        $this->assertTrue($finance->hasPermission('purchasing.settlements.manage'));
        $purchase = $this->purchase();
        $product = $this->product();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase);

        $this->actingAs($operations)
            ->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertDontSee(__('Record supplier payment'));

        $this->post(route('admin.purchases.settlements.store', $purchase), [
            'amount' => '5.00',
            'payment_method' => 'cash',
            'settlement_key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->actingAs($finance)
            ->get(route('admin.purchases.index'))
            ->assertOk()
            ->assertDontSee(__('New purchase'));

        $this->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('Record supplier payment'))
            ->assertDontSee(__('Receive all remaining'));

        $this->post(route('admin.purchases.receive', $purchase))->assertForbidden();

        $this->post(route('admin.purchases.settlements.store', $purchase), [
            'amount' => '5.00',
            'payment_method' => 'cash',
            'settlement_key' => (string) Str::uuid(),
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertDatabaseHas('purchase_settlements', [
            'purchase_id' => $purchase->id,
            'amount' => '5.00',
            'recorded_by' => $finance->id,
        ]);
    }

    public function test_admin_purchase_page_can_record_and_void_supplier_payment(): void
    {
        $admin = $this->createSuperAdmin();
        $product = $this->product();
        $purchase = $this->purchase();
        $item = $this->item($purchase, $product, 2, 10);
        app(PurchaseService::class)->receive($purchase, $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.purchases.show', $purchase))
            ->assertOk()
            ->assertSee(__('Supplier settlement'))
            ->assertSee(__('Received value'))
            ->assertSee('20.00');

        $key = (string) Str::uuid();
        $this->post(route('admin.purchases.settlements.store', $purchase), [
            'amount' => '8.00',
            'payment_method' => 'cash',
            'reference' => 'CASH-HTTP-1',
            'settlement_key' => $key,
        ])->assertRedirect(route('admin.purchases.show', $purchase))
            ->assertSessionHas('success');

        $settlement = PurchaseSettlement::query()->firstOrFail();
        $this->assertSame('8.00', $settlement->amount);

        $this->post(route('admin.purchases.settlements.void', [$purchase, $settlement]), [
            'void_reason' => 'Correcting the supplier payment entry.',
        ])->assertRedirect(route('admin.purchases.show', $purchase))
            ->assertSessionHas('success');

        $this->assertSame(PurchaseSettlement::STATUS_VOIDED, $settlement->fresh()->status);
    }

    private function staffWithRole(string $slug): User
    {
        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Settlement Supplier '.Str::lower(Str::random(8))]);
    }

    private function purchase(): Purchase
    {
        return Purchase::create([
            'supplier_id' => $this->supplier()->id,
            'status' => Purchase::STATUS_ORDERED,
            'currency' => 'EGP',
        ]);
    }
    private function product(): Product
    {
        $token = Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Settlement Category '.$token,
            'slug' => 'settlement-category-'.$token,
            'description' => 'Settlement test category',
            'meta_title' => 'Settlement test',
            'meta_keyword' => 'settlement',
            'meta_description' => 'Settlement test category',
            'status' => false,
        ]);

        return Product::create([
            'name' => 'Settlement Product '.$token,
            'slug' => 'settlement-product-'.$token,
            'category_id' => $category->id,
            'base_price' => 30,
            'cost_price' => 5,
            'quantity' => 0,
            'has_variants' => false,
            'status' => true,
        ]);
    }
    private function item(Purchase $purchase, Product $product, int $quantity, int $unitCost)
    {
        return $purchase->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
        ]);
    }
}
