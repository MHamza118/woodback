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
        Schema::table('employees', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['approved_by']);
            
            // Add new foreign key constraint referencing admins table
            $table->foreign('approved_by')->references('id')->on('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop the admins foreign key
            $table->dropForeign(['approved_by']);
            
            // Restore the old foreign key constraint referencing users table
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
