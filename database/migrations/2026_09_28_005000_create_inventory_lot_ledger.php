<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('source_inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_receipt_id')->nullable()->constrained('purchase_receipts')->nullOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->string('lot_code')->unique();
            $table->string('source_type');
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('quantity_on_hand');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->date('expiration_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(
                ['product_id', 'product_variant_id', 'quantity_on_hand', 'expiration_date'],
                'inventory_lots_target_available_expiry_idx'
            );
        });

        Schema::create('inventory_lot_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('order_stock_reservation_id')->nullable()->constrained('order_stock_reservations')->nullOnDelete();
            $table->integer('quantity_change');
            $table->unsignedInteger('balance_after');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['inventory_lot_id', 'inventory_movement_id'], 'inventory_lot_movement_unique');
            $table->index(['order_item_id', 'inventory_lot_id'], 'inventory_lot_movement_order_item_idx');
            $table->index(['order_stock_reservation_id', 'inventory_lot_id'], 'inventory_lot_movement_reservation_idx');
        });

        $now = now();

        DB::table('products')
            ->where('has_variants', false)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($now) {
                $rows = [];

                foreach ($products as $product) {
                    $rows[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'lot_code' => 'LEGACY-P-'.$product->id,
                        'source_type' => 'legacy_balance',
                        'initial_quantity' => (int) $product->quantity,
                        'quantity_on_hand' => (int) $product->quantity,
                        'unit_cost' => $product->inventory_cost_price ?? $product->cost_price ?? 0,
                        'expiration_date' => $product->expiration_date,
                        'received_at' => $now,
                        'meta' => json_encode(['backfilled' => true]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('inventory_lots')->insert($rows);
                }
            });

        DB::table('product_variants')
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($variants) use ($now) {
                $rows = [];

                foreach ($variants as $variant) {
                    $rows[] = [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'lot_code' => 'LEGACY-V-'.$variant->id,
                        'source_type' => 'legacy_balance',
                        'initial_quantity' => (int) $variant->stock,
                        'quantity_on_hand' => (int) $variant->stock,
                        'unit_cost' => $variant->inventory_cost_price ?? $variant->cost_price ?? 0,
                        'expiration_date' => $variant->expiration_date,
                        'received_at' => $now,
                        'meta' => json_encode(['backfilled' => true]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('inventory_lots')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lot_movements');
        Schema::dropIfExists('inventory_lots');
    }
};
