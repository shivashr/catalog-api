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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('brand')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('model_number')->nullable();
            $table->enum('condition', ['new', 'used', 'refurbished'])->default('new');
            $table->longText('description');
            $table->json('tags')->nullable();
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->decimal('mrp_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_alert')->default(5);
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('shipping_class')->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->enum('status', ['active', 'draft', 'inactive'])->default('draft');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('category_id');
            $table->index('slug');
            $table->index('sku');
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
