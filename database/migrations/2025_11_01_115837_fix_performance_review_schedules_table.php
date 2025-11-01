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
        // Drop the table if it exists (to fix the partial creation issue)
        Schema::dropIfExists('performance_review_schedules');
        
        // Recreate it properly with correct index names
        Schema::create('performance_review_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('first_shift_date');
            $table->enum('review_type', ['one_week', 'one_month', 'quarterly'])->default('quarterly');
            $table->date('scheduled_date');
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('performance_report_id')->nullable()->constrained('performance_reports')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for performance with shorter names
            $table->index('employee_id', 'perf_review_emp_idx');
            $table->index('scheduled_date', 'perf_review_sched_date_idx');
            $table->index('completed', 'perf_review_completed_idx');
            $table->index(['employee_id', 'review_type', 'completed'], 'perf_review_emp_type_comp_idx');
            
            // Unique constraint to prevent duplicate schedules
            $table->unique(['employee_id', 'review_type', 'scheduled_date'], 'perf_review_unique_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_review_schedules');
    }
};
