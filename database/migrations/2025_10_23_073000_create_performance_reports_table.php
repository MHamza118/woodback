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
        Schema::create('performance_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type')->default('Performance Review'); // Performance Review, 90-Day Review, Annual Review, Disciplinary Action, Recognition
            $table->string('review_period')->default('monthly'); // weekly, monthly, quarterly, annual
            
            // Individual ratings (1-5 stars)
            $table->decimal('punctuality', 2, 1)->default(3.0);
            $table->decimal('work_quality', 2, 1)->default(3.0);
            $table->decimal('teamwork', 2, 1)->default(3.0);
            $table->decimal('communication', 2, 1)->default(3.0);
            $table->decimal('customer_service', 2, 1)->default(3.0);
            $table->decimal('initiative', 2, 1)->default(3.0);
            
            // Overall rating (calculated from individual ratings)
            $table->decimal('overall_rating', 2, 1)->default(3.0);
            
            // Text feedback
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('goals')->nullable();
            $table->text('notes')->nullable();
            
            // Tracking
            $table->unsignedBigInteger('created_by'); // Admin who created the report
            $table->string('created_by_name'); // Name cached for display
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('created_by');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reports');
    }
};

