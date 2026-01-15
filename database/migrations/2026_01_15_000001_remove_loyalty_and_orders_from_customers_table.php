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
        Schema::table('customers', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['loyalty_points']);
            
            // Drop columns
            $table->dropColumn([
                'loyalty_points',
                'total_orders',
                'total_spent'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('loyalty_points')->default(0)->after('home_location');
            $table->integer('total_orders')->default(0)->after('loyalty_points');
            $table->decimal('total_spent', 10, 2)->default(0.00)->after('total_orders');
            $table->index(['loyalty_points']);
        });
    }
};
