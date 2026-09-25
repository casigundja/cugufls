<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('transfer_item_id')->nullable()->constrained('stock_transfer_items')->restrictOnDelete();
            $table->string('operation_id');
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->decimal('previous_quantity', 15, 3);
            $table->decimal('new_quantity', 15, 3);
            $table->text('reason');
            $table->string('reference')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['operation_id']);
            $table->unique(['transfer_item_id', 'type']);
            $table->index(['product_id', 'branch_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
