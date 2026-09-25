<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_user')) {
            return;
        }

        $superAdminId = DB::table('roles')
            ->where('slug', 'super_admin')
            ->value('id');

        if (! $superAdminId) {
            $superAdminId = DB::table('roles')->insertGetId([
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full control across the back office.',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('permission_role') && Schema::hasTable('permissions')) {
            $now = now();

            DB::table('permissions')
                ->orderBy('id')
                ->pluck('id')
                ->each(function ($permissionId) use ($superAdminId, $now) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $superAdminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }

        $legacyAdminIds = DB::table('users')
            ->where('role_as', 1)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('role_user')
                    ->whereColumn('role_user.user_id', 'users.id');
            })
            ->pluck('id');

        $now = now();

        foreach ($legacyAdminIds as $userId) {
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $superAdminId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: removing an explicit role assignment during
        // rollback could lock legitimate administrators out of the back office.
    }
};
