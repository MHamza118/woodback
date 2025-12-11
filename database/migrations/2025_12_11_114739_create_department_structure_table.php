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
        Schema::create('department_structure', function (Blueprint $table) {
            $table->id();
            $table->string('department_id'); // FOH, BOH
            $table->string('area_id');
            $table->string('area_name');
            $table->text('area_description')->nullable();
            $table->json('roles'); // Array of roles for this area
            $table->timestamps();
            
            $table->unique(['department_id', 'area_id']);
            $table->index(['department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_structure');
    }
};