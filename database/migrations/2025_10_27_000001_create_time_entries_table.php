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
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('date');
            $table->time('clock_in_time');
            $table->time('clock_out_time')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->json('location_info')->nullable();
            $table->enum('status', ['APPROVED', 'PENDING_APPROVAL', 'DENIED'])->default('APPROVED');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('employee_id');
            $table->index('date');
            $table->index(['employee_id', 'date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
