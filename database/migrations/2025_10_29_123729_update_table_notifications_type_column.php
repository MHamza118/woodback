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
        // Change type column from ENUM to VARCHAR to support more notification types
        DB::statement("ALTER TABLE `table_notifications` MODIFY `type` VARCHAR(50) NOT NULL");
        
        // Also make order_number nullable since not all notifications are order-related
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->string('order_number')->nullable()->change();
        });
        
        // Try to drop the foreign key constraint if it exists
        try {
            Schema::table('table_notifications', function (Blueprint $table) {
                $table->dropForeign(['order_number']);
            });
        } catch (\Exception $e) {
            // Foreign key doesn't exist, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to ENUM (only if you want to support rollback)
        DB::statement("ALTER TABLE `table_notifications` MODIFY `type` ENUM('new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed') NOT NULL");
        
        // Make order_number non-nullable again
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->string('order_number')->nullable(false)->change();
        });
        
        // Re-add the foreign key constraint
        Schema::table('table_notifications', function (Blueprint $table) {
            $table->foreign('order_number')->references('order_number')->on('table_orders')->onDelete('cascade');
        });
    }
};
