<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('courier');
            $table->string('tracking_number');
            $table->enum('status', ['pending', 'created', 'picked', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned', 'cancelled'])->default('pending');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->string('label_url')->nullable();
            $table->json('status_details')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('tracking_number');
            $table->index(['courier', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipments');
    }
};