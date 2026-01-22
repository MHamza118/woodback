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
        Schema::table('availability_requests', function (Blueprint $table) {
            // Add time fields to store availability time ranges per day
            // These are stored in the availability_data JSON, but we can also add them here for easier querying
            // The structure will be: availability_data[day].start_time and availability_data[day].end_time
            // Example: "09:00" to "17:00"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('availability_requests', function (Blueprint $table) {
            //
        });
    }
};
