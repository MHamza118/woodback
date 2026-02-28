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
        Schema::create('schedule_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('week_start_timestamp');
            $table->string('department')->nullable();
            $table->json('shifts_data');
            $table->string('action');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('week_start_timestamp');
            $table->index('department');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_snapshots');
    }
};
