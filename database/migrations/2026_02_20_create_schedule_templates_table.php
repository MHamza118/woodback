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
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Template name
            $table->string('department'); // Department this template is for
            $table->string('location')->nullable(); // Location/branch
            $table->text('description')->nullable(); // Template description
            $table->json('shifts_data'); // Complete shift data as JSON
            $table->unsignedBigInteger('created_by')->nullable(); // Admin who created it
            $table->timestamps();

            // Indexes for common queries
            $table->index('department');
            $table->index('location');
            $table->index('created_by');
            $table->unique(['name', 'department', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
