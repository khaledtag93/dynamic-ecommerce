<?php

namespace App\Services\Auth;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AuthorizationService
{
    public function permissions(): array
    {
        return [
            ['group' => 'dashboard', 'slug' => 'dashboard.view', 'name' => 'View dashboard', 'description' => 'Access the admin dashboard and overview widgets.'],
            ['group' => 'catalog', 'slug' => 'catalog.manage', 'name' => 'Manage catalog', 'description' => 'Create and edit categories, products, brands, and attributes.'],
            ['group' => 'orders', 'slug' => 'orders.view', 'name' => 'View orders', 'description' => 'Review customer orders and order details.'],
            ['group' => 'orders', 'slug' => 'orders.manage', 'name' => 'Manage orders', 'description' => 'Update order statuses and perform order operations.'],
            ['group' => 'payments', 'slug' => 'payments.view', 'name' => 'View payments', 'description' => 'Access payment records and payment detail pages.'],
            ['group' => 'payments', 'slug' => 'payments.manage', 'name' => 'Manage payments', 'description' => 'Authorize, mark paid, fail, or refund payment records.'],
            ['group' => 'payments', 'slug' => 'payments.settings', 'name' => 'Manage payment settings', 'description' => 'Configure enabled payment methods and gateway placeholders.'],
            ['group' => 'customers', 'slug' => 'customers.manage', 'name' => 'Manage customers', 'description' => 'Review customers and change admin/customer access.'],
            ['group' => 'inventory', 'slug' => 'inventory.manage', 'name' => 'Manage inventory', 'description' => 'Access suppliers, purchases, and inventory movements.'],
            ['group' => 'promotions', 'slug' => 'promotions.manage', 'name' => 'Manage promotions', 'description' => 'Access coupons and promotion rules.'],
            ['group' => 'delivery', 'slug' => 'delivery.view', 'name' => 'View deliveries', 'description' => 'Access delivery records and shipment data.'],
            ['group' => 'delivery', 'slug' => 'delivery.manage', 'name' => 'Manage deliveries', 'description' => 'Update delivery status, tracking, and shipment notes.'],
            ['group' => 'notifications', 'slug' => 'notifications.view', 'name' => 'View notifications', 'description' => 'Access the notification inbox.'],
            ['group' => 'permissions', 'slug' => 'permissions.manage', 'name' => 'Manage permissions', 'description' => 'Assign staff roles and inspect the permission matrix.'],
            ['group' => 'settings', 'slug' => 'settings.manage', 'name' => 'Manage settings', 'description' => 'Access branding and delivery/payment related settings.'],
            ['group' => 'deploy', 'slug' => 'deploy.manage', 'name' => 'Manage deploy center', 'description' => 'Run deploy, rollback, dry-run safety checks, and review deploy logs.'],
            ['group' => 'imports', 'slug' => 'imports.manage', 'name' => 'Manage imports', 'description' => 'Run and review import jobs.'],
        ];
    }

    public function roles(): array
    {
        return [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Full control across the back office.',
                'permissions' => collect($this->permissions())->pluck('slug')->all(),
            ],
            'operations_manager' => [
                'name' => 'Operations Manager',
                'description' => 'Handles catalog, orders, delivery, inventory, and customers.',
                'permissions' => [
                    'dashboard.view',
                    'catalog.manage',
                    'orders.view',
                    'orders.manage',
                    'payments.view',
                    'delivery.view',
                    'delivery.manage',
                    'customers.manage',
                    'inventory.manage',
                    'promotions.manage',
                    'notifications.view',
                    'settings.manage',
                    'imports.manage',
                ],
            ],
            'support_agent' => [
                'name' => 'Support Agent',
                'description' => 'Can review orders, customers, notifications, and delivery progress.',
                'permissions' => [
                    'dashboard.view',
                    'orders.view',
                    'customers.manage',
                    'delivery.view',
                    'notifications.view',
                ],
            ],
            'finance_manager' => [
                'name' => 'Finance Manager',
                'description' => 'Focuses on payments, refunds, and revenue operations.',
                'permissions' => [
                    'dashboard.view',
                    'orders.view',
                    'payments.view',
                    'payments.manage',
                    'payments.settings',
                    'notifications.view',
                ],
            ],
        ];
    }

    public function syncDefaults(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($this->permissions() as $permissionDefinition) {
            Permission::query()->updateOrCreate(
                ['slug' => $permissionDefinition['slug']],
                [
                    'name' => $permissionDefinition['name'],
                    'group' => $permissionDefinition['group'],
                    'description' => $permissionDefinition['description'],
                ]
            );
        }

        foreach ($this->roles() as $slug => $roleDefinition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleDefinition['name'],
                    'description' => $roleDefinition['description'],
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', $roleDefinition['permissions'])
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    public function groupedPermissions(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }
}
