<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsWorkspaceV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_sectioned_searchable_permissions_workspace(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->sync([$role->id]);

        $response = $this->actingAs($user)->get(route('admin.permissions.index'));

        $response->assertOk();
        $response->assertSee('data-admin-section-tabs="permissions"', false);
        $response->assertSee('data-admin-section-panel="overview"', false);
        $response->assertSee('data-admin-section-panel="staff"', false);
        $response->assertSee('data-admin-section-panel="roles"', false);
        $response->assertSee('data-admin-section-panel="matrix"', false);
        $response->assertSee('data-permission-search', false);
        $response->assertSee('data-staff-search', false);
        $response->assertSee('data-staff-card', false);
        $response->assertSee('data-permission-bulk="select"', false);
        $response->assertSee('data-permission-bulk="clear"', false);
        $response->assertSee('data-permission-row', false);
        $response->assertDontSee('Legacy fallback');
        $response->assertDontSee('Full legacy fallback');
    }

    public function test_permissions_workspace_uses_arabic_role_and_permission_copy(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $user = User::factory()->create(['role_as' => 1]);
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->sync([$role->id]);

        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get(route('admin.permissions.index'));

        $response->assertOk();
        $response->assertSee('الصلاحيات وأدوار الموظفين');
        $response->assertSee('المدير الأعلى');
        $response->assertSee('عرض لوحة التحكم');
        $response->assertSee('إدارة الطلبات');
        $response->assertSee('البحث في الصلاحيات');
        $response->assertSee('ابحث عن الموظفين بالاسم أو البريد الإلكتروني أو الدور');
        $response->assertSee('تحديد الكل');
        $response->assertSee('إلغاء تحديد الكل');
    }

    public function test_permissions_workspace_uses_shared_confirmation_and_explicit_role_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/permissions/index.blade.php'));

        $this->assertStringContainsString('window.adminConfirmAction(confirmChange', $view);
        $this->assertStringContainsString('form.requestSubmit();', $view);
        $this->assertStringNotContainsString('window.confirm(', $view);

        $this->assertStringContainsString('for="staffRole-{{ $admin->id }}"', $view);
        $this->assertStringContainsString('id="staffRole-{{ $admin->id }}"', $view);
        $this->assertStringContainsString('for="newRoleName"', $view);
        $this->assertStringContainsString('id="newRoleName"', $view);
        $this->assertStringContainsString('for="newRoleDescription"', $view);
        $this->assertStringContainsString('id="newRoleDescription"', $view);
    }

    public function test_permissions_forms_have_explicit_labels_and_required_semantics(): void
    {
        $view = file_get_contents(resource_path('views/admin/permissions/index.blade.php'));

        foreach ([
            'newRoleName',
            'newRoleDescription',
            'customPermissionName',
            'customPermissionGroup',
            'customPermissionDescription',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }

        $this->assertStringContainsString('for="roleName-{{ $role->id }}"', $view);
        $this->assertStringContainsString('id="roleName-{{ $role->id }}"', $view);
        $this->assertStringContainsString('for="roleDescription-{{ $role->id }}"', $view);
        $this->assertStringContainsString('id="roleDescription-{{ $role->id }}"', $view);

        foreach (['newRoleName', 'customPermissionName'] as $requiredControlId) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($requiredControlId, '/') . '"[^>]*aria-required="true"/',
                $view
            );
        }

        $this->assertStringContainsString('id="roleName-{{ $role->id }}" type="text" name="name"', $view);
        $this->assertStringContainsString('required aria-required="true"', $view);
    }

    public function test_roleless_admin_is_not_presented_as_super_admin_in_workspace_data(): void
    {
        app(AuthorizationService::class)->syncDefaults();

        $super = User::factory()->create(['role_as' => 1]);
        $superRole = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $super->roles()->sync([$superRole->id]);

        $unassigned = User::factory()->create([
            'role_as' => 1,
            'name' => 'Unassigned Staff',
            'email' => 'unassigned-staff@example.test',
        ]);

        $response = $this->actingAs($super)->get(route('admin.permissions.index'));

        $response->assertOk();
        $response->assertSee('Unassigned Staff');
        $response->assertSee('Unassigned admin');
        $response->assertSee('Unassigned admin accounts require attention');
        $response->assertDontSee('Full legacy fallback');
        $this->assertFalse($unassigned->fresh()->isSuperAdmin());
    }
}
