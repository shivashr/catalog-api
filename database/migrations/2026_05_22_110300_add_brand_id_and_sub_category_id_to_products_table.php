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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->restrictOnDelete();
            $table->foreignId('sub_category_id')->nullable()->after('brand_id')->constrained('sub_categories')->restrictOnDelete();

            $table->index('brand_id');
            $table->index('sub_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['sub_category_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['sub_category_id']);
            $table->dropColumn(['brand_id', 'sub_category_id']);
        });
    }
};
