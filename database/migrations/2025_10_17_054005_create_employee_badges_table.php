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
        Schema::create('employee_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('badge_type_id');
            $table->unsignedBigInteger('awarded_by');
            $table->text('reason');
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('badge_type_id')->references('id')->on('badge_types')->onDelete('cascade');
            $table->foreign('awarded_by')->references('id')->on('users')->onDelete('cascade');
            
            // Prevent duplicate badge awards
            $table->unique(['employee_id', 'badge_type_id']);
            
            $table->index('employee_id');
            $table->index('badge_type_id');
            $table->index('awarded_by');
            $table->index('awarded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_badges');
    }
};
