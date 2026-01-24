<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to conversations table
        Schema::table('conversations', function (Blueprint $table) {
            if (!$this->indexExists('conversations', 'conversations_type_index')) {
                $table->index('type');
            }
            if (!$this->indexExists('conversations', 'conversations_created_by_index')) {
                $table->index('created_by');
            }
            if (!$this->indexExists('conversations', 'conversations_updated_at_index')) {
                $table->index('updated_at');
            }
        });

        // Add indexes to conversation_participants table
        Schema::table('conversation_participants', function (Blueprint $table) {
            if (!$this->indexExists('conversation_participants', 'conversation_participants_conversation_id_participant_id_index')) {
                $table->index(['conversation_id', 'participant_id']);
            }
            if (!$this->indexExists('conversation_participants', 'conversation_participants_participant_id_index')) {
                $table->index('participant_id');
            }
            if (!$this->indexExists('conversation_participants', 'conversation_participants_participant_type_index')) {
                $table->index('participant_type');
            }
            if (!$this->indexExists('conversation_participants', 'conversation_participants_last_read_at_index')) {
                $table->index('last_read_at');
            }
        });

        // Add indexes to private_messages table
        Schema::table('private_messages', function (Blueprint $table) {
            if (!$this->indexExists('private_messages', 'private_messages_conversation_id_created_at_index')) {
                $table->index(['conversation_id', 'created_at']);
            }
            if (!$this->indexExists('private_messages', 'private_messages_sender_id_index')) {
                $table->index('sender_id');
            }
            if (!$this->indexExists('private_messages', 'private_messages_is_read_index')) {
                $table->index('is_read');
            }
        });

        // Add indexes to group_messages table
        Schema::table('group_messages', function (Blueprint $table) {
            if (!$this->indexExists('group_messages', 'group_messages_conversation_id_created_at_index')) {
                $table->index(['conversation_id', 'created_at']);
            }
            if (!$this->indexExists('group_messages', 'group_messages_sender_id_index')) {
                $table->index('sender_id');
            }
        });

        // Add indexes to table_notifications table
        Schema::table('table_notifications', function (Blueprint $table) {
            if (!$this->indexExists('table_notifications', 'table_notifications_recipient_type_recipient_id_index')) {
                $table->index(['recipient_type', 'recipient_id']);
            }
            if (!$this->indexExists('table_notifications', 'table_notifications_type_index')) {
                $table->index('type');
            }
            if (!$this->indexExists('table_notifications', 'table_notifications_is_read_index')) {
                $table->index('is_read');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_at']);
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'participant_id']);
            $table->dropIndex(['participant_id']);
            $table->dropIndex(['participant_type']);
            $table->dropIndex(['last_read_at']);
        });

        Schema::table('private_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropIndex(['sender_id']);
            $table->dropIndex(['is_read']);
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropIndex(['sender_id']);
        });

        Schema::table('table_notifications', function (Blueprint $table) {
            $table->dropIndex(['recipient_type', 'recipient_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['is_read']);
        });
    }
};
