<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('segment_id')->constrained('segments')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'segment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_segment');
    }
};
