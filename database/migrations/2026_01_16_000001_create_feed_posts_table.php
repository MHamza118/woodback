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
        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->enum('author_type', ['employee', 'admin']);
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->string('image_url')->nullable();
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['author_type', 'author_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_posts');
    }
};
