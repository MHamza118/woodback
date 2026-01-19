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
        Schema::create('feed_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('feed_posts')->onDelete('cascade');
            $table->enum('user_type', ['employee', 'admin']);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            $table->unique(['post_id', 'user_type', 'user_id']);
            $table->index(['user_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_likes');
    }
};
