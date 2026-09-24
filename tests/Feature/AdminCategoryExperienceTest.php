<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_workspace_filters_empty_and_content_cleanup_queues(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);

        $empty = $this->category('Empty Category', 'empty-category', '', null);
        $used = $this->category('Used Category', 'used-category', 'Ready content', 'category/ready.webp');

        Product::create([
            'name' => 'Linked Product',
            'slug' => 'linked-product',
            'category_id' => $used->id,
            'base_price' => 10,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 0,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.categories.index', ['usage' => 'empty']))
            ->assertOk()
            ->assertSee('Empty Category')
            ->assertDontSee('Used Category');

        $this->actingAs($owner)
            ->get(route('admin.categories.index', ['readiness' => 'needs_content']))
            ->assertOk()
            ->assertSee('Empty Category')
            ->assertDontSee('Used Category');
    }

    public function test_category_slug_must_be_unique_but_current_category_can_keep_its_slug(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $first = $this->category('First Category', 'shared-slug', 'First description', 'category/first.webp');
        $second = $this->category('Second Category', 'second-slug', 'Second description', 'category/second.webp');

        $payload = [
            'name' => 'Second Category',
            'slug' => 'shared-slug',
            'description' => 'Second description',
            'meta_title' => 'Second Category',
            'meta_keyword' => 'second,category',
            'meta_description' => 'Second category description',
        ];

        $this->actingAs($owner)
            ->put(route('admin.categories.update', $second), $payload)
            ->assertSessionHasErrors('slug');

        $payload['name'] = 'First Category Updated';

        $this->actingAs($owner)
            ->put(route('admin.categories.update', $first), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $first->id,
            'slug' => 'shared-slug',
        ]);
    }

    public function test_category_edit_keeps_canonical_fields_separate_from_arabic_translation(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $category = $this->category('Canonical Category', 'canonical-category', 'Canonical description', 'category/canonical.webp');

        $category->translations()->create([
            'locale' => 'ar',
            'name' => 'قسم عربي',
            'slug' => 'قسم-عربي',
            'description' => 'وصف عربي',
            'meta_title' => 'عنوان عربي',
            'meta_keyword' => 'كلمات عربية',
            'meta_description' => 'وصف ميتا عربي',
        ]);

        app()->setLocale('ar');

        $this->actingAs($owner)
            ->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertSee('value="Canonical Category"', false)
            ->assertSee('value="canonical-category"', false)
            ->assertSee('value="قسم عربي"', false);
    }

    public function test_clearing_optional_arabic_translation_removes_stale_translation(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $category = $this->category('Canonical Category', 'canonical-category', 'Canonical description', 'category/canonical.webp');

        $category->translations()->create([
            'locale' => 'ar',
            'name' => 'قسم عربي',
            'slug' => 'قسم-عربي',
            'description' => 'وصف عربي',
            'meta_title' => 'عنوان عربي',
            'meta_keyword' => 'كلمات عربية',
            'meta_description' => 'وصف ميتا عربي',
        ]);

        $payload = [
            'name' => 'Canonical Category',
            'slug' => 'canonical-category',
            'description' => 'Canonical description',
            'meta_title' => 'Canonical Category',
            'meta_keyword' => 'canonical,category',
            'meta_description' => 'Canonical category description',
            'translations' => [
                'ar' => [
                    'name' => '',
                    'slug' => '',
                    'description' => '',
                    'meta_title' => '',
                    'meta_keyword' => '',
                    'meta_description' => '',
                ],
            ],
        ];

        $this->actingAs($owner)
            ->put(route('admin.categories.update', $category), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('category_translations', [
            'category_id' => $category->id,
            'locale' => 'ar',
        ]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $owner = User::factory()->create(['role_as' => 1]);
        $category = $this->category('Protected Category', 'protected-category', 'Protected', 'category/protected.webp');

        Product::create([
            'name' => 'Protected Product',
            'slug' => 'protected-product',
            'category_id' => $category->id,
            'base_price' => 15,
            'quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 0,
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    private function category(string $name, string $slug, string $description, ?string $image): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image' => $image,
            'meta_title' => $name,
            'meta_keyword' => strtolower(str_replace(' ', ',', $name)),
            'meta_description' => $name . ' description',
            'status' => 0,
        ]);
    }
}
