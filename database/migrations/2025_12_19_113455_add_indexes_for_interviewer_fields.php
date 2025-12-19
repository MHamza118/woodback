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
            // Add indexes for frequently queried interviewer fields
            $table->index('is_interviewer');
            $table->index('interview_access');
            $table->index(['status', 'is_interviewer']); // Composite index for employee interviewers query
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_interviewer']);
            $table->dropIndex(['interview_access']);
            $table->dropIndex(['status', 'is_interviewer']);
        });
    }
};
