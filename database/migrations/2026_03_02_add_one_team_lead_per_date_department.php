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
        // Add unique constraint to ensure only ONE team lead per date/department
        // This prevents multiple employees from being team leads on the same date/department
        Schema::table('team_lead_assignments', function (Blueprint $table) {
            $table->unique(['assigned_date', 'department'], 'one_team_lead_per_date_department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_lead_assignments', function (Blueprint $table) {
            $table->dropUnique('one_team_lead_per_date_department');
        });
    }
};
