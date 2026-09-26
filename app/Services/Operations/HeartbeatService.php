<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;

class HeartbeatService
{
    public function beat(string $name, array $context = []): void
    {
        $now = now();

        DB::table('operations_heartbeats')->updateOrInsert(
            ['name' => $name],
            [
                'last_seen_at' => $now,
                'context' => json_encode($context, JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function get(string $name): ?object
    {
        return DB::table('operations_heartbeats')
            ->where('name', $name)
            ->first();
    }
}
