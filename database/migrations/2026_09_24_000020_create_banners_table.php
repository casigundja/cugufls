<?php

// Blueprint v1: integrar apenas na fase correspondente, após revisão.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image');
            $table->string('alt');
            $table->string('button_label')->nullable();
            $table->string('target_url')->nullable();
            $table->string('placement');
            $table->boolean('active')->default(false);
            $table->unsignedInteger('sort_order');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['placement', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
