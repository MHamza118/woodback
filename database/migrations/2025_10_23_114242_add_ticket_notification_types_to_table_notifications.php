<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds support for ticket-related notification types.
     * The notification types are defined in the TableNotification model.
     */
    public function up(): void
    {
        // Add ticket notification types to the type enum column
        \DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM(
            'new_order', 
            'order_updated', 
            'order_ready', 
            'order_delivered', 
            'table_changed',
            'NEW_SIGNUP',
            'ONBOARDING_COMPLETE',
            'shoutout_received',
            'reward_received',
            'badge_received',
            'training_assigned',
            'training_completed',
            'time_off_request',
            'new_ticket',
            'ticket_status_update',
            'ticket_response',
            'ticket_archived'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove ticket notification types from the type enum column
        \DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM(
            'new_order', 
            'order_updated', 
            'order_ready', 
            'order_delivered', 
            'table_changed',
            'NEW_SIGNUP',
            'ONBOARDING_COMPLETE',
            'shoutout_received',
            'reward_received',
            'badge_received',
            'training_assigned',
            'training_completed',
            'time_off_request'
        )");
    }
};
