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
        Schema::create('product_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('warranty_duration')->nullable();
            $table->enum('warranty_unit', ['days', 'months', 'years'])->nullable();
            $table->enum('warranty_by', ['seller', 'manufacturer', 'no_warranty'])->nullable();
            $table->string('return_window')->nullable();
            $table->enum('return_type', ['return_refund', 'exchange_only', 'no_return'])->nullable();
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_warranties');
    }
};
