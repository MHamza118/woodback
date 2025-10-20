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
        Schema::create('reward_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['points', 'gift_card', 'benefit']);
            $table->integer('value');
            $table->text('description');
            $table->string('icon', 10)->default('🎁');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_types');
    }
};
