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
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->boolean('has_test')->default(false)->after('active');
            $table->json('test_questions')->nullable()->after('has_test');
            $table->integer('passing_score')->default(80)->after('test_questions');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->dropColumn(['has_test', 'test_questions', 'passing_score']);
        });
    }
};
