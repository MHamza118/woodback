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
        Schema::create('employee_clock_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->onDelete('cascade');
            $table->boolean('is_currently_clocked')->default(false);
            $table->foreignId('current_time_entry_id')->nullable()->constrained('time_entries')->onDelete('set null');
            $table->time('last_clock_in')->nullable();
            $table->time('last_clock_out')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('is_currently_clocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_clock_statuses');
    }
};
