<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->restrictOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
