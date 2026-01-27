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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('department'); // BOH, FOH, etc.
            $table->string('day_of_week'); // Monday, Tuesday, etc.
            $table->date('date'); // Specific date
            $table->time('start_time');
            $table->time('end_time');
            $table->string('role'); // Floor Service, Bartender, etc.
            $table->string('shift_type')->nullable(); // F, E, G, etc.
            $table->text('requirements')->nullable(); // Lollipops Focus & As Needed, etc.
            $table->date('week_start'); // Start of the week
            $table->date('week_end'); // End of the week
            $table->string('status')->default('active'); // active, cancelled, etc.
            $table->timestamps();

            // Indexes for common queries
            $table->index('employee_id');
            $table->index('department');
            $table->index('date');
            $table->index(['week_start', 'week_end']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
