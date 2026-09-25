<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->string('operation_id');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['import_batch_id', 'row_number']);
            $table->unique(['operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
