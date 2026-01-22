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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time');
            $table->date('end_date');
            $table->time('end_time');
            $table->string('color')->default('#3B82F6'); // Default blue
            $table->enum('repeat_type', ['none', 'daily', 'weekly', 'monthly'])->default('none');
            $table->date('repeat_end_date')->nullable();
            $table->unsignedBigInteger('created_by'); // Admin ID
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('cascade');

            // Indexes
            $table->index(['start_date']);
            $table->index(['end_date']);
            $table->index(['created_by']);
            $table->index(['repeat_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
