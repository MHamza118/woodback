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
        // Drop if exists (from failed migration)
        Schema::dropIfExists('team_lead_assignments');
        
        Schema::create('team_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('assigned_date'); // Specific date for team lead
            $table->string('department'); // BOH, FOH, etc.
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable(); // Which admin assigned this
            $table->timestamps();

            // Indexes for common queries
            $table->index('employee_id');
            $table->index('assigned_date');
            $table->index('department');
            $table->index(['assigned_date', 'department']);
            
            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('assigned_by_admin_id')->references('id')->on('admins')->onDelete('set null');
            
            // Unique constraint: one employee can't be assigned as team lead twice on same date/department
            // But multiple employees CAN be team leads on same date/department
            $table->unique(['employee_id', 'assigned_date', 'department'], 'team_lead_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_lead_assignments');
    }
};
