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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null')->after('employee_id');
            $table->boolean('created_by_admin')->default(false)->after('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['admin_id']);
            $table->dropColumn(['admin_id', 'created_by_admin']);
        });
    }
};
