<?php

namespace App\Services\Growth\Contracts;

use App\Models\GrowthDelivery;
use App\Models\User;

interface GrowthChannelDriver
{
    /**
     * @return array{status:string,recipient:?string,meta?:array<string,mixed>}
     */
    public function send(GrowthDelivery $delivery, ?User $user, array $payload): array;
}
