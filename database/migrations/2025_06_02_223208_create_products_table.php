<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
public function up()
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();

        // Basic fields:
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();

        // Pricing (new structure):
        $table->decimal('base_price', 10, 2);       // Original price
        $table->decimal('sale_price', 10, 2)->nullable(); // Discounted price

        // Stock:
        $table->integer('quantity')->default(0);

        // Dates for availability:
        $table->timestamp('available_from')->nullable();
        $table->timestamp('expires_at')->nullable();

        // Link to category:
        $table->unsignedBigInteger('category_id');
        $table->foreign('category_id')
              ->references('id')->on('categories')
              ->onDelete('cascade');

        // Single image (optional):
        $table->string('image')->nullable();

        // Status: 0 = visible, 1 = hidden
        $table->boolean('status')->default(false);

        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('products');
}
}
