<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $table->tinyInteger('rating'); // 1-5
            $table->string('title');
            $table->text('comment');
            $table->text('pros')->nullable();
            $table->text('cons')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->json('images')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'product_id']);
            $table->index('rating');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_reviews');
    }
};