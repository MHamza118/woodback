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
        Schema::table('table_notifications', function (Blueprint $table) {
            // Make order_number nullable for recognition notifications
            $table->string('order_number')->nullable()->change();
        });
        
        // Update the type enum to include recognition types
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed', 'NEW_SIGNUP', 'ONBOARDING_COMPLETE', 'shoutout_received', 'reward_received', 'badge_received') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_notifications', function (Blueprint $table) {
            // Make order_number required again
            $table->string('order_number')->nullable(false)->change();
        });
        
        // Restore original enum values
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed', 'NEW_SIGNUP', 'ONBOARDING_COMPLETE') NOT NULL");
    }
};
