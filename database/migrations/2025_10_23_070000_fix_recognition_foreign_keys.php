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
        // Fix employee_shoutouts table
        Schema::table('employee_shoutouts', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['recognized_by']);
        });
        
        Schema::table('employee_shoutouts', function (Blueprint $table) {
            // Add new foreign key pointing to admins table
            $table->foreign('recognized_by')->references('id')->on('admins')->onDelete('cascade');
        });

        // Fix employee_rewards table
        Schema::table('employee_rewards', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['given_by']);
        });
        
        Schema::table('employee_rewards', function (Blueprint $table) {
            // Add new foreign key pointing to admins table
            $table->foreign('given_by')->references('id')->on('admins')->onDelete('cascade');
        });

        // Fix employee_badges table
        Schema::table('employee_badges', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['awarded_by']);
        });
        
        Schema::table('employee_badges', function (Blueprint $table) {
            // Add new foreign key pointing to admins table
            $table->foreign('awarded_by')->references('id')->on('admins')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes - point back to users table
        
        Schema::table('employee_shoutouts', function (Blueprint $table) {
            $table->dropForeign(['recognized_by']);
        });
        
        Schema::table('employee_shoutouts', function (Blueprint $table) {
            $table->foreign('recognized_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->dropForeign(['given_by']);
        });
        
        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->foreign('given_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('employee_badges', function (Blueprint $table) {
            $table->dropForeign(['awarded_by']);
        });
        
        Schema::table('employee_badges', function (Blueprint $table) {
            $table->foreign('awarded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
