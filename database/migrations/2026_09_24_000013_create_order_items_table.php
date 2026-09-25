<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('line_total', 15, 2)->nullable();
            $table->json('customization')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
