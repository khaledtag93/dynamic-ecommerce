<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierWorkspaceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/SupplierController.php'));

        $this->assertStringContainsString('mb_substr(trim((string) $request->string(\'search\')), 0, 100)', $controller);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_supplier_list_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/suppliers/index.blade.php'));

        foreach ([
            'supplierSearch',
            'supplierStatus',
            'supplierUsage',
            'supplierPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }

    public function test_supplier_profile_controls_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/suppliers/_form.blade.php'));

        foreach ([
            'supplierName',
            'supplierCompany',
            'supplierContactName',
            'supplierEmail',
            'supplierPhone',
            'supplierCountry',
            'supplierAddress',
            'supplierNotes',
            'supplierIsActive',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertMatchesRegularExpression(
            '/id="supplierName"[^>]*aria-required="true"/',
            $view
        );

        foreach ([
            'supplierName',
            'supplierCompany',
            'supplierContactName',
            'supplierEmail',
            'supplierPhone',
            'supplierCountry',
        ] as $controlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . $controlId . '"[^>]*maxlength="255"/',
                $view
            );
        }
    }

    public function test_supplier_delete_is_locked_and_preserves_purchase_history(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/SupplierController.php'));

        $this->assertStringContainsString('DB::transaction(function () use ($supplier)', $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);

        $admin = $this->createSuperAdmin();

        $protected = Supplier::create(['name' => 'Protected supplier']);
        $purchase = Purchase::create([
            'supplier_id' => $protected->id,
            'status' => Purchase::STATUS_ORDERED,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.suppliers.destroy', $protected))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $protected->id]);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id]);

        $unused = Supplier::create(['name' => 'Unused supplier']);

        $this->delete(route('admin.suppliers.destroy', $unused))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('suppliers', ['id' => $unused->id]);
    }
}
