<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogWorkspaceV2Test extends TestCase
{
    public function test_brand_and_attribute_workspaces_use_shared_admin_primitives(): void
    {
        $paths = [
            resource_path('views/livewire/admin/brand/index.blade.php'),
            resource_path('views/livewire/admin/attribute/index.blade.php'),
            resource_path('views/livewire/admin/attribute/values.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString('<x-admin.page-header', $source);
            $this->assertStringContainsString('<x-admin.stat-card', $source);
            $this->assertStringNotContainsString('admin-card admin-stat-card h-100', $source);
        }
    }

    public function test_catalog_tables_use_logical_rtl_action_alignment(): void
    {
        foreach ([
            resource_path('views/livewire/admin/brand/index.blade.php'),
            resource_path('views/livewire/admin/attribute/index.blade.php'),
            resource_path('views/livewire/admin/attribute/values.blade.php'),
            resource_path('views/livewire/admin/product/index.blade.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString('rtl-text-start', $source);
            $this->assertStringContainsString('rtl-justify-start', $source);
        }
    }

    public function test_product_health_cards_use_shared_stat_visual_contract(): void
    {
        $source = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $this->assertStringContainsString('<x-admin.stat-card', $source);
        $this->assertStringContainsString('admin-stat-card--v2 admin-stat-action', $source);
        $this->assertStringNotContainsString('class="card admin-card stat-card h-100"', $source);
    }

    public function test_product_bulk_status_actions_use_in_app_confirmations(): void
    {
        $source = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $this->assertStringNotContainsString('onclick="return confirm(', $source);
        $this->assertStringContainsString('productBulkActivateConfirmationModal', $source);
        $this->assertStringContainsString('productBulkHideConfirmationModal', $source);
        $this->assertStringContainsString('wire:click="bulkSetStatus(true)"', $source);
        $this->assertStringContainsString('wire:click="bulkSetStatus(false)"', $source);
    }

    public function test_product_form_has_no_mixed_language_meta_description_label(): void
    {
        $source = file_get_contents(resource_path('views/livewire/admin/product/product-form.blade.php'));

        $this->assertStringContainsString("{{ __('Meta Description') }}", $source);
        $this->assertStringNotContainsString("Meta {{ __('Description') }}", $source);
    }

    public function test_arabic_catalog_copy_covers_new_confirmation_and_cleanup_labels(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('مرتبطة', $translations['Linked'] ?? null);
        $this->assertSame('تحديث الكتالوج', $translations['Catalog update'] ?? null);
        $this->assertSame('هل تريد تفعيل المنتجات المحددة؟', $translations['Activate selected products?'] ?? null);
        $this->assertSame('إخفاء المحدد', $translations['Hide selected'] ?? null);
    }
}
