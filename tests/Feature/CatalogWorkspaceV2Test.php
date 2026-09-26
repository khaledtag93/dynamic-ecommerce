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

    public function test_product_list_uses_store_currency_label_instead_of_dollar_placeholders(): void
    {
        $source = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $this->assertStringContainsString("{{ __('EGP') }} {{ number_format((float) (\$product->base_price ?? 0), 2) }}", $source);
        $this->assertStringContainsString("<span class=\"input-group-text\">{{ __('EGP') }}</span>", $source);
        $this->assertStringNotContainsString('<span class="input-group-text">$</span>', $source);
    }

    public function test_product_operational_feedback_is_localized(): void
    {
        $source = file_get_contents(app_path('Http/Livewire/Admin/Product/Index.php'));

        $this->assertStringContainsString("__('Base price is required.')", $source);
        $this->assertStringContainsString("__('This product uses variants. Please edit price from variant rows.')", $source);
        $this->assertStringContainsString("__('Product activated, but content still needs: :issues.'", $source);
        $this->assertStringContainsString("__('Product :name deleted successfully.'", $source);
        $this->assertStringContainsString("__(':count selected product(s) marked as featured.'", $source);
        $this->assertStringNotContainsString("session()->flash('error', 'Please select at least one product.')", $source);
    }

    public function test_arabic_catalog_copy_covers_inline_price_and_status_feedback(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/ar.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('سعر الأساس مطلوب.', $translations['Base price is required.'] ?? null);
        $this->assertSame('لا يمكن أن يكون سعر البيع أكبر من سعر الأساس.', $translations['Sale price cannot be greater than base price.'] ?? null);
        $this->assertSame('المنتج رقم :id أصبح نشطًا.', $translations['Product #:id is now active.'] ?? null);
        $this->assertSame('تم حذف المنتج :name بنجاح.', $translations['Product :name deleted successfully.'] ?? null);
    }


    public function test_catalog_select_all_keeps_livewire_state_bounded(): void
    {
        $component = file_get_contents(app_path('Http/Livewire/Admin/Product/Index.php'));
        $view = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $selectAllStart = strpos($component, 'public function selectAllMatching()');
        $resetStart = strpos($component, 'public function resetSelection()', $selectAllStart);
        $selectAllMethod = substr($component, $selectAllStart, $resetStart - $selectAllStart);

        $this->assertStringNotContainsString("->pluck('id')", $selectAllMethod);
        $this->assertStringContainsString('$this->selectedProducts = [];', $selectAllMethod);
        $this->assertStringContainsString('? $this->totalFilteredCount', $component);
        $this->assertStringContainsString('@disabled($selectAll)', $view);
        $this->assertStringContainsString('@checked($selectAll)', $view);
    }

    public function test_catalog_bulk_delete_refuses_unbounded_all_results_selection(): void
    {
        $component = file_get_contents(app_path('Http/Livewire/Admin/Product/Index.php'));
        $view = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $this->assertStringContainsString('if ($this->selectAll) {', $component);
        $this->assertStringContainsString('For safety, bulk delete is limited to explicitly selected products.', $component);
        $this->assertStringContainsString('@disabled($this->selectedCount === 0 || $selectAll)', $view);
    }

    public function test_catalog_export_and_search_are_bounded_for_large_catalogs(): void
    {
        $component = file_get_contents(app_path('Http/Livewire/Admin/Product/Index.php'));

        $this->assertStringContainsString('$products->lazy(500)', $component);
        $this->assertStringContainsString('mb_substr(trim($search), 0, 100)', $component);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $component);
        $this->assertStringContainsString('protected function selectedProductsQuery()', $component);
        $this->assertStringContainsString('return $this->productsQuery(false);', $component);
    }



    public function test_catalog_filters_have_explicit_accessible_labels(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        foreach ([
            'catalogSearch',
            'catalogStatusFilter',
            'catalogCategoryFilter',
            'catalogBrandFilter',
            'catalogReadinessFilter',
            'catalogStockFilter',
            'catalogFeaturedFilter',
            'catalogPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }

    public function test_product_row_controls_have_mobile_touch_targets_and_accessible_names(): void
    {
        $source = file_get_contents(resource_path('views/livewire/admin/product/index.blade.php'));

        $this->assertStringContainsString("aria-label=\"{{ __('Select product :name'", $source);
        $this->assertStringContainsString("aria-label=\"{{ __('Edit product :name'", $source);
        $this->assertStringContainsString("aria-label=\"{{ __('Duplicate product :name'", $source);
        $this->assertStringContainsString("aria-label=\"{{ __('Delete product :name'", $source);
        $this->assertStringContainsString("aria-label=\"{{ __('Edit quantity for :name'", $source);
        $this->assertStringContainsString('width: 44px;', $source);
        $this->assertStringContainsString('min-height: 44px;', $source);
    }

}
