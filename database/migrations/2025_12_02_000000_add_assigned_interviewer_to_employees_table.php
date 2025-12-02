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
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_interviewer_id')->nullable()->after('approved_by');
            
            // Add foreign key constraint to admins table
            $table->foreign('assigned_interviewer_id')
                  ->references('id')
                  ->on('admins')
                  ->onDelete('set null');
            
            $table->index('assigned_interviewer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['assigned_interviewer_id']);
            $table->dropIndex(['assigned_interviewer_id']);
            $table->dropColumn('assigned_interviewer_id');
        });
    }
};
