<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to modify the enum column
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY category ENUM('broken-equipment', 'software-issue', 'pos-problem', 'kitchen-equipment', 'facility-issue', 'other', 'event-reservation', 'message', 'customer-need') DEFAULT 'other'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY category ENUM('broken-equipment', 'software-issue', 'pos-problem', 'kitchen-equipment', 'facility-issue', 'other') DEFAULT 'other'");
        }
    }
};
