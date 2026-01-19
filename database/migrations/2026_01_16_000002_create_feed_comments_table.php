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
        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('feed_posts')->onDelete('cascade');
            $table->enum('author_type', ['employee', 'admin']);
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('post_id');
            $table->index(['author_type', 'author_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_comments');
    }
};
