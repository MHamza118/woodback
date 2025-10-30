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
        Schema::table('employee_onboarding_progress', function (Blueprint $table) {
            $table->enum('test_status', ['not_required', 'pending', 'passed', 'failed'])
                  ->default('not_required')->after('status');
            $table->integer('test_score')->nullable()->after('test_status');
            $table->integer('test_attempts')->default(0)->after('test_score');
        });
    }

    public function down(): void
    {
        Schema::table('employee_onboarding_progress', function (Blueprint $table) {
            $table->dropColumn(['test_status', 'test_score', 'test_attempts']);
        });
    }
};
