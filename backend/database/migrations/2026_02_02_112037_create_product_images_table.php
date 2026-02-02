<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('image_path');
            $table->boolean('is_main')->default(false);
            $table->integer('order')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'is_main']);
            $table->index('order');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
};