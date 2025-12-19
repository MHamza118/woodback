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
            $table->string('assigned_interviewer_type')->nullable()->after('assigned_interviewer_id');
            $table->unsignedBigInteger('assigned_employee_interviewer_id')->nullable()->after('assigned_interviewer_type');
            
            $table->foreign('assigned_employee_interviewer_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['assigned_employee_interviewer_id']);
            $table->dropColumn(['assigned_interviewer_type', 'assigned_employee_interviewer_id']);
        });
    }
};
