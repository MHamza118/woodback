<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This fixes NULL employee_id values and reverts to NOT nullable
     */
    public function up(): void
    {
        // First, delete any tickets with NULL employee_id (admin-created tickets that shouldn't exist)
        DB::table('tickets')->whereNull('employee_id')->delete();
        
        // Then revert employee_id back to NOT nullable
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // If needed to rollback, make it nullable again
            $table->foreignId('employee_id')->nullable()->change();
        });
    }
};
