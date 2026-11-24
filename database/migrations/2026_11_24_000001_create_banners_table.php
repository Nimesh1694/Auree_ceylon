<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title_en');
            $table->string('title_si')->nullable();
            $table->text('subtitle_en')->nullable();
            $table->text('subtitle_si')->nullable();
            $table->string('cta_text_en')->nullable();
            $table->string('cta_text_si')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('placement', ['hero_slider', 'sub_banner_1', 'sub_banner_2'])->default('hero_slider');
            $table->string('theme_style')->default('festive_rose'); // festive_rose, royal_gold, dark_botanical, ivory_cream
            $table->timestamp('starts_at')->nullable(); // Scheduling: start time
            $table->timestamp('ends_at')->nullable();   // Scheduling: expiry time
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['placement', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

