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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->enum('role', ['owner', 'admin', 'staff', 'customer'])->default('customer')->after('password');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('role');

            $table->index('tenant_id');
            $table->index('role');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);

            $table->dropColumn(['tenant_id', 'role', 'status']);
        });
    }
};
