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
        Schema::table('availability_requests', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['approved_by']);
            // We want it to be nullable but NOT constrained to employees
            // The column type is already unsignedBigInteger (via foreignId)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('availability_requests', function (Blueprint $table) {
            $table->foreign('approved_by')->references('id')->on('employees')->onDelete('set null');
        });
    }
};
