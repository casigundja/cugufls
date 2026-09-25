<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['phone']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
