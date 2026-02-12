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
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('published')->default(false)->after('status');
            $table->timestamp('published_at')->nullable()->after('published');
            $table->unsignedBigInteger('published_by')->nullable()->after('published_at');
            
            $table->index('published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['published', 'published_at', 'published_by']);
        });
    }
};
