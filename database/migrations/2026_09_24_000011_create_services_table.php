<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('segments')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('image')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
