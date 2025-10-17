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
        Schema::create('employee_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('reward_type_id');
            $table->unsignedBigInteger('given_by');
            $table->text('reason');
            $table->enum('status', ['pending', 'redeemed'])->default('pending');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('reward_type_id')->references('id')->on('reward_types')->onDelete('cascade');
            $table->foreign('given_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index('employee_id');
            $table->index('reward_type_id');
            $table->index('given_by');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_rewards');
    }
};
