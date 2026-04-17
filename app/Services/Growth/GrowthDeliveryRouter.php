<?php

namespace App\Services\Growth;

use App\Models\GrowthDelivery;
use App\Services\Growth\Channels\InAppGrowthChannel;
use App\Services\Growth\Channels\SmtpEmailGrowthChannel;
use App\Services\Growth\Contracts\GrowthChannelDriver;
use InvalidArgumentException;

class GrowthDeliveryRouter
{
    public function driverFor(GrowthDelivery $delivery): GrowthChannelDriver
    {
        if ($delivery->channel === 'in_app') {
            return app(InAppGrowthChannel::class);
        }

        if ($delivery->channel === 'email' && ($delivery->provider === 'smtp' || $delivery->provider === null)) {
            return app(SmtpEmailGrowthChannel::class);
        }

        throw new InvalidArgumentException('Unsupported growth delivery channel/provider combination.');
    }
}
