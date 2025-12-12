<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This rolls back the nullable change to employee_id
     * All existing tickets have employee_id values, so no data will be lost
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Revert employee_id back to NOT nullable
            // This is safe because all existing tickets have employee_id values
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
