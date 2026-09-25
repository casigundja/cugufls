<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'segment_id', 'branch_id']);
            $table->index(['user_id', 'segment_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_accesses');
    }
};
