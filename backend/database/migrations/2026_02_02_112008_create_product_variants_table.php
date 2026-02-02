<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('name'); // e.g., "Color", "Size"
            $table->string('value'); // e.g., "Red", "Large"
            $table->string('sku')->unique()->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'name']);
            $table->unique(['product_id', 'name', 'value']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_variants');
    }
};