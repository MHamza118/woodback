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
        Schema::create('onboarding_page_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('onboarding_page_id')->constrained('onboarding_pages')->onDelete('cascade');
            $table->integer('attempt_number')->default(1);
            $table->integer('score');
            $table->json('answers');
            $table->boolean('passed');
            $table->timestamp('completed_at');
            $table->timestamps();
            
            $table->index(['employee_id', 'onboarding_page_id'], 'optr_emp_page_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_page_test_results');
    }
};
