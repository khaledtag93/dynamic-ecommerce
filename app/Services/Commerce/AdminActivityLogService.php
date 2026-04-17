<?php

namespace App\Services\Commerce;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AdminActivityLogService
{
    /**
     * @param  array<string,mixed>  $meta
     */
    public function log(
        string $type,
        string $action,
        string $description,
        ?int $adminUserId = null,
        ?Model $subject = null,
        array $meta = [],
    ): ?AdminActivityLog {
        if (! Schema::hasTable('admin_activity_logs')) {
            return null;
        }

        return AdminActivityLog::query()->create([
            'admin_user_id' => $adminUserId,
            'type' => $type,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
        ]);
    }
}
