<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
      Schema::create('product_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')
        ->constrained('products')
        ->onDelete('cascade');
    
    $table->string('locale'); // e.g. 'en', 'ar'
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();

    // Ensure only one translation per locale per product
    $table->unique(['product_id', 'locale']);
    // Ensure no duplicate slugs within the same locale
    $table->unique(['locale', 'slug']);

    $table->timestamps();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
