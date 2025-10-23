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
        Schema::create('performance_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type'); // general, recognition, coaching, correction, development
            $table->string('subject');
            $table->text('message');
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            
            // Tracking
            $table->unsignedBigInteger('created_by'); // Admin who created the interaction
            $table->string('created_by_name'); // Name cached for display
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('created_by');
            $table->index('type');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_interactions');
    }
};

