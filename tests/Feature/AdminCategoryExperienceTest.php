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
