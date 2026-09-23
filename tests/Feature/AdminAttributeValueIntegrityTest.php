<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Attribute\Values;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAttributeValueIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_from_another_attribute_cannot_be_edited(): void
    {
        $color = $this->attribute('Color');
        $size = $this->attribute('Size');
        $medium = ProductAttributeValue::create(['attribute_id' => $size->id, 'value' => 'Medium']);

        try {
            Livewire::test(Values::class, ['id' => $color->id])
                ->call('edit', $medium->id);

            $this->fail('A value from another attribute must not be editable.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(ProductAttributeValue::class, $exception->getModel());
        }
    }

    public function test_in_use_value_cannot_be_renamed(): void
    {
        [$attribute, $value] = $this->usedValue('Color', 'Red');

        Livewire::test(Values::class, ['id' => $attribute->id])
            ->call('edit', $value->id)
            ->set('value', 'Crimson')
            ->call('save')
            ->assertHasErrors(['value']);

        $this->assertDatabaseHas('product_attribute_values', ['id' => $value->id, 'value' => 'Red']);
        $this->assertDatabaseMissing('product_attribute_values', ['attribute_id' => $attribute->id, 'value' => 'Crimson']);
    }

    public function test_in_use_value_cannot_be_deleted(): void
    {
        [$attribute, $value] = $this->usedValue('Size', 'Large');

        Livewire::test(Values::class, ['id' => $attribute->id])
            ->call('requestDelete', $value->id)
            ->assertSet('pendingDeleteId', null);

        $this->assertDatabaseHas('product_attribute_values', ['id' => $value->id]);
    }

    public function test_unused_value_can_be_renamed_and_deleted(): void
    {
        $attribute = $this->attribute('Material');
        $value = ProductAttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Cotton']);

        Livewire::test(Values::class, ['id' => $attribute->id])
            ->call('edit', $value->id)
            ->set('value', 'Organic Cotton')
            ->call('save')
            ->assertHasNoErrors();

        $value->refresh();
        $this->assertSame('Organic Cotton', $value->value);

        Livewire::test(Values::class, ['id' => $attribute->id])
            ->call('requestDelete', $value->id)
            ->assertSet('pendingDeleteId', $value->id)
            ->call('confirmDelete');

        $this->assertDatabaseMissing('product_attribute_values', ['id' => $value->id]);
    }

    public function test_duplicate_value_within_same_attribute_is_rejected(): void
    {
        $attribute = $this->attribute('Finish');
        ProductAttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Matte']);

        Livewire::test(Values::class, ['id' => $attribute->id])
            ->set('value', 'Matte')
            ->call('save')
            ->assertHasErrors(['value' => 'unique']);
    }

    private function usedValue(string $attributeName, string $label): array
    {
        $attribute = $this->attribute($attributeName);
        $value = ProductAttributeValue::create(['attribute_id' => $attribute->id, 'value' => $label]);

        $category = Category::create([
            'name' => $attributeName . ' Test',
            'slug' => strtolower($attributeName) . '-attribute-test',
            'description' => 'Test category',
            'meta_title' => 'Test',
            'meta_keyword' => 'test',
            'meta_description' => 'Test category',
            'status' => false,
        ]);

        $product = Product::create([
            'name' => $attributeName . ' Product',
            'slug' => strtolower($attributeName) . '-attribute-product',
            'category_id' => $category->id,
            'base_price' => 100,
            'quantity' => 1,
            'status' => false,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($attributeName) . '-VARIANT-1',
            'price' => 100,
            'stock' => 1,
            'status' => true,
        ]);

        ProductVariantAttribute::create([
            'variant_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'attribute_value' => $label,
        ]);

        return [$attribute, $value];
    }

    private function attribute(string $name): ProductAttribute
    {
        return ProductAttribute::create(['name' => $name, 'type' => 'select']);
    }
}
