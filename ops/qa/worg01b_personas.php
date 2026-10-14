<?php

/**
 * W-ORG-01b QAS-only, synthetic Workforce persona fixture manager.
 * Modes: --preflight, --verify-auth, --seed, --cleanup-dry-run, --cleanup.
 * Cleanup refuses untracked related rows and reconciles exact-ID deletions.
 * Never execute against production. Keep the generated JSON manifest private.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
if (! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
    fwrite(STDERR, 'BLOCKED: QAS fixture runner must reside under the Laravel app root'.PHP_EOL);
    exit(2);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmployeeAttendanceSession;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeLeaveType;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function qaStop(string $reason): never
{
    fwrite(STDERR, "BLOCKED: ".$reason.PHP_EOL);
    exit(2);
}

$mode = $argv[1] ?? '--preflight';
if (! in_array($mode, ['--preflight', '--verify-auth', '--seed', '--cleanup-dry-run', '--cleanup'], true)) {
    qaStop('unknown mode');
}

$expectedDb = 'u637857322_v42_rehearsal';
$target = 'https://v42.tag-marketplace.com';
if (! str_contains($root, 'laravel_app_v42_rehearsal')
    || ! app()->environment('staging')
    || config('app.debug')
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== $expectedDb
    || (DB::selectOne('SELECT DATABASE() AS db')->db ?? null) !== $expectedDb
) {
    qaStop('application location/environment/live database mismatch');
}

$roles = [
    'alpha_manager' => 'team_supervisor',
    'alpha_lead' => 'team_supervisor',
    'alpha_worker' => 'cashier',
    'beta_manager' => 'team_supervisor',
    'beta_worker' => 'cashier',
    'operations_manager' => 'operations_manager',
    'finance_manager' => 'finance_manager',
];
$codes = [
    'alpha_manager' => 'QA-ALPHA-MANAGER',
    'alpha_lead' => 'QA-ALPHA-LEAD',
    'alpha_worker' => 'QA-ALPHA-WORKER',
    'beta_manager' => 'QA-BETA-MANAGER',
    'beta_worker' => 'QA-BETA-WORKER',
    'operations_manager' => 'QA-OPS-MANAGER',
    'finance_manager' => 'QA-FINANCE-MANAGER',
];
$neededTables = [
    'users', 'roles', 'permissions', 'permission_role', 'role_user',
    'employee_profiles', 'employee_attendance_sessions',
    'employee_leave_types', 'employee_leave_requests',
];
foreach ($neededTables as $table) {
    if (! Schema::hasTable($table)) {
        qaStop('missing required database table: '.$table);
    }
}

$privateDir = storage_path('app/private');
$manifestPath = $privateDir.'/worg01b-personas-manifest.json';
$pendingManifestPath = $manifestPath.'.pending';
$roleIds = Role::query()->whereIn('slug', array_unique(array_values($roles)))->pluck('id', 'slug')->all();
foreach (array_unique(array_values($roles)) as $role) {
    if (! isset($roleIds[$role])) {
        qaStop('required role not present: '.$role);
    }
}

$requiredPermissions = [
    'team_supervisor' => ['workforce.view', 'workforce.manage', 'workforce.clock'],
    'cashier' => ['workforce.clock'],
    'operations_manager' => ['workforce.view', 'workforce.manage'],
    'finance_manager' => ['workforce.payroll.view', 'workforce.payroll.manage'],
];
$forbiddenPermissions = [
    'team_supervisor' => ['workforce.payroll.view', 'workforce.payroll.manage'],
    'cashier' => ['workforce.view', 'workforce.payroll.manage'],
    'operations_manager' => ['workforce.payroll.view', 'workforce.payroll.manage'],
    'finance_manager' => ['workforce.view', 'workforce.manage'],
];
foreach ($requiredPermissions as $slug => $required) {
    $assigned = Role::query()->where('slug', $slug)->firstOrFail()
        ->permissions()->pluck('slug')->all();
    foreach ($required as $permission) {
        if (! in_array($permission, $assigned, true)) {
            qaStop('role permission missing: '.$slug.' / '.$permission);
        }
    }
    foreach ($forbiddenPermissions[$slug] as $permission) {
        if (in_array($permission, $assigned, true)) {
            qaStop('role contains forbidden permission: '.$slug.' / '.$permission);
        }
    }
}

$emails = [];
foreach ($roles as $key => $slug) {
    $emails[$key] = $key.'.worg01b@flowra-qa.invalid';
}

if ($mode === '--preflight') {
    $duplicates = User::query()->whereIn('email', array_values($emails))->count()
        + EmployeeProfile::query()->whereIn('employee_code', array_values($codes))->count();
    echo 'QAS_ENV_AND_LIVE_DB=PASS'.PHP_EOL;
    echo 'ROLE_PERMISSIONS=PASS'.PHP_EOL;
    echo 'EXPECTED_PERSONA_COUNT=7'.PHP_EOL;
    echo 'FIXTURE_MANIFEST_PRESENT='.(is_file($manifestPath) ? 'YES' : 'NO').PHP_EOL;
    echo 'EXISTING_SYNTHETIC_DUPLICATES='.$duplicates.PHP_EOL;
    exit(0);
}

if ($mode === '--verify-auth') {
    if (! is_file($manifestPath)) {
        qaStop('authentication check manifest missing');
    }
    $known = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (($known['environment'] ?? '') !== 'qas'
        || ($known['fixture_namespace'] ?? '') !== 'QA-WORG01B') {
        qaStop('authentication manifest is not QAS-only');
    }
    $valid = 0;
    foreach ($roles as $key => $role) {
        $item = $known['personas'][$key] ?? null;
        if (! $item || ($item['email'] ?? null) !== $emails[$key]) {
            qaStop('synthetic persona identity mismatch');
        }
        $user = User::query()->where('email', $emails[$key])->first();
        if (! $user || (int) $user->role_as !== 1
            || ! $user->hasRole($role)
            || ! \Illuminate\Support\Facades\Hash::check($item['password'], $user->password)) {
            qaStop('synthetic login credential or role mismatch for '.$key);
        }
        $valid++;
    }
    echo 'QA_CREDENTIAL_HASHES_VERIFIED='.$valid.PHP_EOL;
    echo 'QA_USER_ROLES_VERIFIED='.$valid.PHP_EOL;
    echo 'NO_PLAINTEXT_SECRETS_PRINTED=YES'.PHP_EOL;
    exit(0);
}

if ($mode === '--seed') {
    if (is_file($pendingManifestPath)) {
        qaStop('pending fixture escrow exists; verify database and recover it before another seed');
    }
    if (is_file($manifestPath)) {
        qaStop('fixture manifest already exists; cleanup required before another seed');
    }
    if (User::query()->whereIn('email', array_values($emails))->exists()
        || EmployeeProfile::query()->whereIn('employee_code', array_values($codes))->exists()
        || EmployeeLeaveType::query()->where('code', 'QA-WORG01B')->exists()) {
        qaStop('colliding test fixture already exists');
    }

    if (! is_dir($privateDir) && ! mkdir($privateDir, 0700, true) && ! is_dir($privateDir)) {
        qaStop('cannot create private manifest directory');
    }
    @chmod($privateDir, 0700);

    $manifest = [
        'environment' => 'qas',
        'base_url' => $target,
        'fixture_namespace' => 'QA-WORG01B',
        'qa_data_policy' => 'disposable-qas',
        'owner_accepts_qa_data_loss' => true,
        'qas_app_database_confirmed' => true,
        'backup_verified' => false,
        'isolated_restore_verified' => false,
        'created_at' => gmdate('c'),
        'personas' => [],
        'created_row_ids' => [],
    ];
    try {
        DB::transaction(function () use (&$manifest, $roles, $codes, $emails, $roleIds, $pendingManifestPath): void {
            $profiles = [];
            $userIds = [];
            foreach ($roles as $key => $slug) {
                $password = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user = User::create([
                    'name' => $codes[$key],
                    'email' => $emails[$key],
                    'password' => $password,
                    'role_as' => 1,
                ]);
                $user->email_verified_at = now();
                $user->save();
                $user->roles()->attach($roleIds[$slug]);
                $profile = EmployeeProfile::create([
                    'user_id' => $user->id,
                    'employee_code' => $codes[$key],
                    'department' => str_starts_with($key, 'alpha_') ? 'QA Alpha'
                        : (str_starts_with($key, 'beta_') ? 'QA Beta' : 'QA Shared'),
                    'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
                    'status' => EmployeeProfile::STATUS_ACTIVE,
                ]);
                $profiles[$key] = $profile;
                $userIds[] = $user->id;
                $manifest['personas'][$key] = [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'employee_code' => $codes[$key],
                    'email' => $emails[$key],
                    'password' => $password,
                ];
            }
            foreach ([
                'alpha_lead' => 'alpha_manager',
                'alpha_worker' => 'alpha_lead',
                'beta_worker' => 'beta_manager',
            ] as $child => $parent) {
                $profiles[$child]->manager_employee_profile_id = $profiles[$parent]->id;
                $profiles[$child]->save();
            }

            $sessions = [];
            foreach (['alpha_worker', 'beta_worker'] as $key) {
                $row = EmployeeAttendanceSession::create([
                    'employee_profile_id' => $profiles[$key]->id,
                    'clock_in_at' => now()->subHours(2),
                    'clock_out_at' => now()->subHour(),
                    'source' => 'admin',
                ]);
                $sessions[] = $row->id;
            }
            $leaveType = EmployeeLeaveType::create([
                'code' => 'QA-WORG01B',
                'name' => 'Synthetic QA leave',
                'is_active' => true,
                'is_paid' => true,
                'default_annual_entitlement_days' => 20,
            ]);
            $leaveIds = [];
            foreach (['alpha_manager', 'beta_worker'] as $key) {
                $leave = EmployeeLeaveRequest::create([
                    'employee_profile_id' => $profiles[$key]->id,
                    'employee_leave_type_id' => $leaveType->id,
                    'starts_on' => now()->addMonth()->startOfDay()->toDateString(),
                    'ends_on' => now()->addMonth()->startOfDay()->toDateString(),
                    'requested_days' => 1,
                    'status' => EmployeeLeaveRequest::STATUS_PENDING,
                ]);
                $leaveIds[] = $leave->id;
            }
            $manifest['created_row_ids'] = [
                'users' => $userIds,
                'employee_profiles' => array_map(fn ($profile) => $profile->id, array_values($profiles)),
                'employee_attendance_sessions' => $sessions,
                'employee_leave_requests' => $leaveIds,
                'employee_leave_types' => [$leaveType->id],
            ];

            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($pendingManifestPath, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('cannot write private fixture escrow');
            }
            if (! chmod($pendingManifestPath, 0600)) {
                throw new RuntimeException('private fixture escrow permissions could not be restricted');
            }
        }, 1);
    } catch (Throwable $exception) {
        if (is_file($pendingManifestPath)) {
            @unlink($pendingManifestPath);
        }
        qaStop('seed transaction failed; inspect private server logs, do not repeat seed blindly');
    }

    // If final rename fails after SQL COMMIT, preserve .pending for manual recovery.
    if (! rename($pendingManifestPath, $manifestPath)) {
        qaStop('database committed but fixture escrow is pending; recover before proceeding');
    }
    echo 'QA_PERSONAS_CREATED=7'.PHP_EOL;
    echo 'QA_ATTENDANCE_CREATED=2'.PHP_EOL;
    echo 'QA_LEAVE_REQUESTS_CREATED=2'.PHP_EOL;
    echo 'QA_PRIVATE_MANIFEST_READY=YES'.PHP_EOL;
    exit(0);
}

/**
 * Fail-closed impact assessment: all ID-bound rows must be accounted for,
 * including FK references created later by browser tests.
 * Emits aggregate counts only; never dumps user data or credentials.
 */
function qaCleanupImpact(array $ids, string $expectedDb): array
{
    $blocks = [];
    $counts = [];
    foreach (['users', 'employee_profiles', 'employee_attendance_sessions',
        'employee_leave_requests', 'employee_leave_types'] as $table) {
        $tracked = array_values(array_unique(array_map('intval', $ids[$table] ?? [])));
        if (! $tracked || in_array(0, $tracked, true)) {
            qaStop('cleanup manifest missing valid tracked IDs for '.$table);
        }
        $actual = DB::table($table)->whereIn('id', $tracked)->count();
        $counts[$table] = ['tracked' => count($tracked), 'found' => $actual];
        if ($actual !== count($tracked)) {
            $blocks[] = 'tracked row count mismatch in '.$table;
        }
    }

    // FK inventory catches unexpected children without assuming which Workforce
    // features will be exercised during authenticated role acceptance.
    $refs = DB::table('information_schema.KEY_COLUMN_USAGE')
        ->where('REFERENCED_TABLE_SCHEMA', $expectedDb)
        ->whereIn('REFERENCED_TABLE_NAME', array_keys($counts))
        ->get(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME']);
    foreach ($refs as $ref) {
        $table = (string) $ref->TABLE_NAME;
        $column = (string) $ref->COLUMN_NAME;
        $target = (string) $ref->REFERENCED_TABLE_NAME;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            $blocks[] = 'referencing schema missing for '.$table;
            continue;
        }
        $related = DB::table($table)->whereIn($column, $ids[$target]);
        if ($table === 'role_user' && $column === 'user_id') {
            $total = (clone $related)->count();
            $counts['role_user_memberships'] = $total;
            if ($total !== count($ids['users'])) {
                $blocks[] = 'unexpected QA role membership count';
            }
            continue;
        }
        // Rows explicitly recorded in the fixture manifest are expected;
        // untracked references to fixture IDs must never be silently deleted.
        if (isset($ids[$table]) && Schema::hasColumn($table, 'id')) {
            $related->whereNotIn('id', $ids[$table]);
        }
        $extra = $related->count();
        if ($extra > 0) {
            $counts['untracked_'.$table.'_'.$column] = $extra;
            $blocks[] = 'untracked dependent rows in '.$table.' / '.$column;
        }
    }
    // Eloquent sessions may use a user_id without a declared FK.
    if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
        $remainingSessions = DB::table('sessions')->whereIn('user_id', $ids['users'])->count();
        $counts['qa_sessions'] = $remainingSessions;
        if ($remainingSessions > 0) {
            $blocks[] = 'QA sessions must expire or be cleared through approved session management';
        }
    }
    return ['status' => $blocks === [] ? 'READY' : 'BLOCKED', 'counts' => $counts, 'blocks' => $blocks];
}

if (! is_file($manifestPath)) {
    qaStop('cleanup manifest missing');
}
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['environment'] ?? '') !== 'qas'
    || ($manifest['fixture_namespace'] ?? '') !== 'QA-WORG01B'
    || count($manifest['personas'] ?? []) !== 7) {
    qaStop('cleanup manifest invalid');
}
foreach ($codes as $key => $code) {
    $p = $manifest['personas'][$key] ?? null;
    if (! $p || $p['employee_code'] !== $code || $p['email'] !== $emails[$key]) {
        qaStop('cleanup manifest record does not match expected QA identity');
    }
    $profile = EmployeeProfile::query()->find($p['profile_id']);
    $user = User::query()->find($p['user_id']);
    if (! $profile || ! $user || $profile->employee_code !== $code
        || $profile->user_id !== $user->id || $user->email !== $emails[$key]) {
        qaStop('cleanup aborted: fixture identity mismatch');
    }
}
$ids = $manifest['created_row_ids'];
$impact = qaCleanupImpact($ids, $expectedDb);
echo 'QA_CLEANUP_IMPACT='.json_encode($impact, JSON_THROW_ON_ERROR).PHP_EOL;
if ($mode === '--cleanup-dry-run') {
    exit($impact['status'] === 'READY' ? 0 : 2);
}
if ($impact['status'] !== 'READY') {
    qaStop('cleanup stopped due to untracked dependencies or manifest drift');
}
$baseline = [];
foreach (array_keys($impact['counts']) as $table) {
    if (in_array($table, ['users', 'employee_profiles', 'employee_attendance_sessions',
        'employee_leave_requests', 'employee_leave_types'], true)) {
        $baseline[$table] = DB::table($table)->count();
    }
}
$baseline['role_user'] = DB::table('role_user')->count();
DB::transaction(function () use ($ids, $baseline): void {
    EmployeeLeaveRequest::query()->whereIn('id', $ids['employee_leave_requests'])->delete();
    EmployeeAttendanceSession::query()->whereIn('id', $ids['employee_attendance_sessions'])->delete();
    EmployeeLeaveType::query()->whereIn('id', $ids['employee_leave_types'])->delete();
    EmployeeProfile::query()->whereIn('id', $ids['employee_profiles'])
        ->update(['manager_employee_profile_id' => null]);
    EmployeeProfile::query()->whereIn('id', $ids['employee_profiles'])->delete();
    DB::table('role_user')->whereIn('user_id', $ids['users'])->delete();
    User::query()->whereIn('id', $ids['users'])->delete();
    foreach ($baseline as $table => $before) {
        $deleted = $table === 'role_user' ? count($ids['users']) : count($ids[$table]);
        if (DB::table($table)->count() !== $before - $deleted) {
            throw new RuntimeException('cleanup row count reconciliation failed for '.$table);
        }
    }
}, 1);
// Remove the private manifest only after the DB transaction committed successfully.
if (! unlink($manifestPath)) {
    qaStop('cleanup committed but private manifest could not be removed; manual reconciliation required');
}
echo 'QA_CLEANUP_COMPLETE=YES'.PHP_EOL;
echo 'QA_PERSONAS_REMAINING='.User::query()->whereIn('email', array_values($emails))->count().PHP_EOL;
echo 'QA_PROFILES_REMAINING='.EmployeeProfile::query()->whereIn('employee_code', array_values($codes))->count().PHP_EOL;
