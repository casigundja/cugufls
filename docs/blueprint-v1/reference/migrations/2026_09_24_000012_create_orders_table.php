<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_id');
            $table->foreignId('segment_id')->constrained('segments')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->string('source');
            $table->string('currency');
            $table->decimal('total', 15, 2)->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('request_hash')->nullable();
            $table->timestamps();
            $table->unique(['public_id']);
            $table->unique(['idempotency_key']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['segment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
