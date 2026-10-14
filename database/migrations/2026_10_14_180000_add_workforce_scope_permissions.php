<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $scope = Permission::query()->firstOrCreate(
            ['slug' => 'workforce.scope.all'],
            [
                'name' => 'View and manage all employees',
                'group' => 'workforce',
                'description' => 'Organization-wide Workforce data access; not payroll or private medical records.',
            ]
        );
        foreach (['operations_manager', 'super_admin'] as $slug) {
            $role = Role::query()->where('slug', $slug)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching([$scope->id]);
            }
        }

        $teamRole = Role::query()->firstOrCreate(
            ['slug' => 'team_supervisor'],
            [
                'name' => 'Team Supervisor',
                'description' => 'Manage only own reporting team. No payroll salary access.',
                'is_system' => true,
            ]
        );
        $ids = Permission::query()->whereIn('slug', [
            'dashboard.view', 'workforce.view', 'workforce.manage',
            'workforce.clock', 'workforce.payroll.self',
        ])->pluck('id')->all();
        $teamRole->permissions()->syncWithoutDetaching($ids);
    }

    public function down(): void
    {
        // Never auto-revoke organization access or delete a role already assigned
        // to real users. Explicit administrative review is required.
    }
};
