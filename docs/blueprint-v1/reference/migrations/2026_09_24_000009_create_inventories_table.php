<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('minimum_quantity', 15, 3);
            $table->decimal('maximum_quantity', 15, 3)->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'branch_id']);
            $table->index(['branch_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
