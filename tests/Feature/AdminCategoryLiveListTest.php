<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryLiveListTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_live_list_preserves_filters_permissions_sorting_and_escaped_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $owner = User::factory()->create(['role_as' => 1]);
        $cashier = User::factory()->create(['role_as' => 1]);
        $cashier->roles()->sync([Role::where('slug', 'cashier')->firstOrFail()->id]);

        Category::create([
            'name' => '<script>Live Category</script>',
            'slug' => 'live-category',
            'description' => '',
            'status' => 1,
        ]);
        Category::create([
            'name' => 'Other Category',
            'slug' => 'other-category',
            'description' => 'Ready',
            'image' => 'category/ready.jpg',
            'status' => 0,
        ]);

        $url = route('admin.categories.index', [
            'search' => 'live-category',
            'visibility' => 'hidden',
            'usage' => 'empty',
            'readiness' => 'needs_content',
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-live-list', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-link', false)
            ->assertSee('&lt;script&gt;Live Category&lt;/script&gt;', false)
            ->assertDontSee('Other Category');

        $this->withHeader('X-Live-List', '1')->get($url)
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('&lt;script&gt;Live Category&lt;/script&gt;', false)
            ->assertDontSee('Other Category')
            ->assertDontSee('data-live-filter', false)
            ->assertDontSee('<html', false);

        $this->actingAs($cashier)->get($url)->assertForbidden();
    }
}
