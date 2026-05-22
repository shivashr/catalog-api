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
        Schema::create('product_coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_coupon_id')->constrained('product_coupons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('order_reference');
            $table->string('customer_reference')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'order_reference']);
            $table->index(['tenant_id', 'product_coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_coupon_usages');
    }
};

