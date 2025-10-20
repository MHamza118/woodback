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
        // First, drop the foreign key constraint on order_number
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->dropForeign(['order_number']);
        });

        // Make order_number nullable and modify type enum
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->string('order_number')->nullable()->change();
        });

        // Add new notification types using raw SQL
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed', 'NEW_SIGNUP', 'ONBOARDING_COMPLETE') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the foreign key and make order_number required again
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->string('order_number')->nullable(false)->change();
            $table->foreign('order_number')->references('order_number')->on('table_orders')->onDelete('cascade');
        });

        // Restore original enum values
        DB::statement("ALTER TABLE table_notifications MODIFY COLUMN type ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed') NOT NULL");
    }
};
