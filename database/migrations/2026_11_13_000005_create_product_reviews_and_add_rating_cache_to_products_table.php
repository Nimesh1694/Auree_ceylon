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
        // 1. Product reviews table
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 150);
            $table->text('comment');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status'], 'idx_pr_product_status');
            $table->index('order_id', 'idx_pr_order');
            $table->unique(['customer_id', 'product_id'], 'uk_pr_customer_product');
        });

        // 2. Extend products table with rating cache and reviews counter
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_cache', 3, 2)->default(0.00)->after('cost_price');
            $table->unsignedInteger('reviews_count')->default(0)->after('rating_cache');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_cache', 'reviews_count']);
        });
    }
};

