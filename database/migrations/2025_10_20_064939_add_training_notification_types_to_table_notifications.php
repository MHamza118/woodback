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
        // Add new training notification types to the enum using raw SQL
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed', 'NEW_SIGNUP', 'ONBOARDING_COMPLETE', 'shoutout_received', 'reward_received', 'badge_received', 'training_assigned', 'training_completed') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original enum values (remove training notification types)
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed', 'NEW_SIGNUP', 'ONBOARDING_COMPLETE', 'shoutout_received', 'reward_received', 'badge_received') NOT NULL");
    }
};
