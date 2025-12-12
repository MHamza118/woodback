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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // We can't easily revert this if there are tickets with null employee_id
            // So we'll just leave it nullable in down() or throw an error if needed
            // But strictly speaking, to revert "nullable", we'd change it back:
             $table->foreignId('employee_id')->nullable(false)->change();
        });
    }
};
