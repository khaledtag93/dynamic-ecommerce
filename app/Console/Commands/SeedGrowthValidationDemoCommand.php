<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthValidationDemoService;
use Illuminate\Console\Command;

class SeedGrowthValidationDemoCommand extends Command
{
    protected $signature = 'growth:seed-demo {--clear : Remove demo users, orders, events, and growth artifacts} {--keep-growth-logs : Keep existing growth trigger/message/delivery artifacts when reseeding}';

    protected $description = 'Seed a safe growth validation dataset so the growth engine can be tested live.';

    public function handle(GrowthValidationDemoService $service): int
    {
        if ($this->option('clear')) {
            $result = $service->clear(true);

            $this->info(__('Growth validation demo data cleared. Users: :users | Orders: :orders | Events: :events | Products removed: :products', [
                'users' => $result['users'] ?? 0,
                'orders' => $result['orders'] ?? 0,
                'events' => $result['events'] ?? 0,
                'products' => $result['products_removed'] ?? 0,
            ]));

            return self::SUCCESS;
        }

        $result = $service->seed(! $this->option('keep-growth-logs'));

        $this->info(__('Growth validation demo data seeded. Users: :users | Orders: :orders | Events: :events | Demo products: :products', [
            'users' => $result['users'] ?? 0,
            'orders' => $result['orders'] ?? 0,
            'events' => $result['events'] ?? 0,
            'products' => $result['products'] ?? 0,
        ]));

        $this->line(__('Next step: run php artisan growth:run to see live triggers, predictive scores, and delivery simulation.'));

        return self::SUCCESS;
    }
}
