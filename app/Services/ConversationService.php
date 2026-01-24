<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use App\Models\Employee;
use App\Traits\ChatHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ConversationService
 * 
 * Handles all conversation-related operations:
 * - Retrieving conversations
 * - Creating group conversations
 * - Creating/retrieving private conversations
 * - Managing participants
 * - Marking conversations as read
 */
class ConversationService
{
    use ChatHelper;

    /**
     * Get all conversations for a user
     * 
     * @param string $userId The user ID
     * @param string $userType The user type ('admin' or 'employee')
     * @return array Array of conversation data
     */
    public function getConversations(string $userId, string $userType): array
    {
        // Use cache for 30 seconds to reduce database queries
        $cacheKey = "conversations_user_{$userId}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($userId) {
            // Optimized query with eager loading
            $conversations = Conversation::join('conversation_participants', 'conversations.id', '=', 'conversation_participants.conversation_id')
                ->where('conversation_participants.participant_id', $userId)
                ->select('conversations.*')
                ->distinct()
                ->with([
                    'participants' => function ($query) {
                        $query->select('id', 'conversation_id', 'participant_id', 'participant_type', 'last_read_at')
                            ->with('employee:id,email,first_name,last_name,profile_data,profile_image');
                    }
                ])
                ->orderBy('conversations.updated_at', 'desc')
                ->get();

            return $conversations->map(function ($conversation) use ($userId) {
                return $this->formatConversationData($conversation, $userId);
            })->toArray();
        });
    }

    /**
     * Format conversation data for API response
     * 
     * @param Conversation $conversation The conversation object
     * @param string $userId The current user ID
     * @return array Formatted conversation data
     */
    private function formatConversationData(Conversation $conversation, string $userId): array
    {
        // Get last message based on conversation type (use cache for frequently accessed data)
        $lastMessage = \Illuminate\Support\Facades\Cache::remember(
            "last_message_conv_{$conversation->id}",
            60,
            function () use ($conversation) {
                return $this->getLastMessage($conversation);
            }
        );

        // Get unread count (use cache for 10 seconds)
        $unreadCount = \Illuminate\Support\Facades\Cache::remember(
            "unread_count_user_{$userId}_conv_{$conversation->id}",
            10,
            function () use ($conversation, $userId) {
                return $conversation->getUnreadCount($userId);
            }
        );

        // Get other participant info for private conversations
        $otherParticipantProfileImage = null;
        if ($conversation->type === 'private') {
            $otherParticipant = $conversation->participants
                ->where('participant_id', '!=', $userId)
                ->first();
            
            if ($otherParticipant) {
                if ($otherParticipant->participant_type === 'admin') {
                    $conversation->name = 'Management';
                } elseif ($otherParticipant->employee) {
                    $profileData = $otherParticipant->employee->profile_data ?? [];
                    $firstName = $otherParticipant->employee->first_name ?? ($profileData['firstName'] ?? 'Employee');
                    $lastName = $otherParticipant->employee->last_name ?? ($profileData['lastName'] ?? '');
                    $conversation->name = $firstName . ' ' . $lastName;
                    
                    // Get profile image URL (cache for 1 hour)
                    if ($otherParticipant->employee->profile_image) {
                        $otherParticipantProfileImage = \Illuminate\Support\Facades\Cache::remember(
                            "profile_image_{$otherParticipant->employee->id}",
                            3600,
                            function () use ($otherParticipant) {
                                return \Illuminate\Support\Facades\Storage::disk('public')->url($otherParticipant->employee->profile_image);
                            }
                        );
                    }
                }
            }
        }

        return [
            'id' => $conversation->id,
            'name' => $conversation->name,
            'type' => $conversation->type,
            'lastMessage' => $lastMessage ? [
                'content' => $lastMessage->content,
                'timestamp' => $lastMessage->created_at,
                'sender_name' => $lastMessage->sender_name,
                'sender_id' => $lastMessage->sender_id
            ] : null,
            'unreadCount' => $unreadCount,
            'members' => $conversation->participants->pluck('participant_id')->toArray(),
            'otherParticipantProfileImage' => $otherParticipantProfileImage
        ];
    }

    /**
     * Get the last message in a conversation
     * 
     * @param Conversation $conversation The conversation object
     * @return mixed The last message or null
     */
    private function getLastMessage(Conversation $conversation)
    {
        if ($conversation->type === 'group') {
            return GroupMessage::where('conversation_id', $conversation->id)
                ->select('id', 'conversation_id', 'content', 'sender_name', 'sender_id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->first();
        } elseif ($conversation->type === 'private') {
            return PrivateMessage::where('conversation_id', $conversation->id)
                ->select('id', 'conversation_id', 'content', 'sender_name', 'sender_id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->first();
        }
        
        return null;
    }

    /**
     * Create a new group conversation
     * 
     * @param string $name The group name
     * @param array $employeeIds Array of employee IDs to add
     * @param string $createdBy The admin ID creating the group
     * @return array The created conversation data
     * @throws \Exception
     */
    public function createGroupConversation(string $name, array $employeeIds, string $createdBy): array
    {
        try {
            DB::beginTransaction();

            // Create the conversation
            $conversation = Conversation::create([
                'name' => $name,
                'type' => 'group',
                'created_by' => $createdBy
            ]);

            // Add admin as participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'participant_id' => $createdBy,
                'participant_type' => 'admin',
                'joined_at' => now()
            ]);

            // Add employees as participants
            foreach ($employeeIds as $employeeId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => (string)$employeeId,
                    'participant_type' => 'employee',
                    'joined_at' => now()
                ]);
            }

            DB::commit();

            // Invalidate conversations cache for all participants
            $this->invalidateUserConversationsCache($createdBy);
            foreach ($employeeIds as $employeeId) {
                $this->invalidateUserConversationsCache((string)$employeeId);
            }

            return [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'type' => $conversation->type,
                'members' => array_merge([$createdBy], $employeeIds)
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get or create a private conversation between two users
     * 
     * @param string $userId The current user ID
     * @param string $userType The current user type
     * @param string $participantId The other participant ID (can be 'admin' or numeric employee ID)
     * @return array The conversation data
     * @throws \Exception
     */
    public function getOrCreatePrivateConversation(string $userId, string $userType, string $participantId): array
    {
        try {
            // Convert 'admin' string to actual admin ID
            $actualParticipantId = $participantId;
            $participantType = 'employee';
            
            if ($participantId === 'admin') {
                // Get the first active admin's actual numeric ID
                // Try multiple status values to be safe
                $admin = \App\Models\Admin::where('status', 'active')
                    ->orWhere('status', 'ACTIVE')
                    ->first();
                
                // If no active admin found, get any admin
                if (!$admin) {
                    $admin = \App\Models\Admin::first();
                }
                
                if (!$admin) {
                    throw new \Exception('No admin found');
                }
                $actualParticipantId = (string)$admin->id;
                $participantType = 'admin';
            } else {
                // For non-admin participants, validate it's a valid employee
                $employee = Employee::find((int)$participantId);
                if (!$employee) {
                    throw new \Exception('Invalid participant');
                }
                $actualParticipantId = (string)$participantId;
                $participantType = 'employee';
            }

            // Check if private conversation already exists
            $conversation = Conversation::where('type', 'private')
                ->whereHas('participants', function ($query) use ($userId) {
                    $query->where('participant_id', $userId);
                })
                ->whereHas('participants', function ($query) use ($actualParticipantId) {
                    $query->where('participant_id', $actualParticipantId);
                })
                ->first();

            if (!$conversation) {
                DB::beginTransaction();

                // Create new private conversation
                $conversation = Conversation::create([
                    'name' => 'Private Chat',
                    'type' => 'private',
                    'created_by' => $userId
                ]);

                // Add both participants
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $userId,
                    'participant_type' => $userType,
                    'joined_at' => now()
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $actualParticipantId,
                    'participant_type' => $participantType,
                    'joined_at' => now()
                ]);

                DB::commit();

                // Invalidate conversations cache for both participants
                $this->invalidateUserConversationsCache($userId);
                $this->invalidateUserConversationsCache($actualParticipantId);
            }

            return [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'type' => $conversation->type
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add participants to a group conversation
     * 
     * @param string $conversationId The conversation ID
     * @param array $employeeIds Array of employee IDs to add
     * @return void
     * @throws \Exception
     */
    public function addParticipantsToGroup(string $conversationId, array $employeeIds): void
    {
        try {
            $conversation = Conversation::findOrFail($conversationId);

            if ($conversation->type !== 'group') {
                throw new \Exception('Can only add participants to group conversations');
            }

            DB::beginTransaction();

            foreach ($employeeIds as $employeeId) {
                // Check if employee is already a participant
                $exists = ConversationParticipant::where('conversation_id', $conversationId)
                    ->where('participant_id', $employeeId)
                    ->exists();

                if (!$exists) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversationId,
                        'participant_id' => (string)$employeeId,
                        'participant_type' => 'employee',
                        'joined_at' => now()
                    ]);
                }
            }

            DB::commit();

            // Invalidate cache for all participants
            $this->invalidateConversationCache($conversationId);
            foreach ($employeeIds as $employeeId) {
                $this->invalidateUserConversationsCache((string)$employeeId);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark conversation as read for a user
     * 
     * @param string $conversationId The conversation ID
     * @param string $userId The user ID
     * @param string $userType The user type
     * @return void
     * @throws \Exception
     */
    public function markConversationAsRead(string $conversationId, string $userId, string $userType): void
    {
        try {
            $conversation = Conversation::findOrFail($conversationId);
            
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                throw new \Exception('You are not a participant in this conversation');
            }

            DB::beginTransaction();

            // Update participant's last read time
            $participant->update(['last_read_at' => now()]);

            // Mark individual messages as read based on conversation type
            if ($conversation->type === 'private') {
                // For private messages, mark all messages in this conversation as read for this user
                // Messages are read by the current user if they are NOT the sender
                PrivateMessage::where('conversation_id', $conversationId)
                    ->where(function($query) use ($userId, $userType) {
                        $query->where('sender_id', '!=', $userId)
                              ->orWhere('sender_type', '!=', $userType);
                    })
                    ->whereNull('read_at')
                    ->update([
                        'is_read' => 1,
                        'read_at' => now()
                    ]);
            }
            // For group messages, we don't mark individual messages as read
            // The last_read_at in conversation_participants is sufficient

            DB::commit();

            // Invalidate cache for this user's conversations and unread counts
            $this->invalidateUserConversationsCache($userId);
            $this->invalidateUnreadCountCache($userId, $conversationId);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Verify user is a participant in a conversation
     * 
     * @param string $conversationId The conversation ID
     * @param string $userId The user ID
     * @return bool True if user is a participant
     */
    public function isUserParticipant(string $conversationId, string $userId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('participant_id', $userId)
            ->exists();
    }

    /**
     * Get conversation by ID
     * 
     * @param string $conversationId The conversation ID
     * @return Conversation|null The conversation or null if not found
     */
    public function getConversationById(string $conversationId): ?Conversation
    {
        return Conversation::find($conversationId);
    }

    /**
     * Invalidate cache for a user's conversations
     * 
     * @param string $userId The user ID
     * @return void
     */
    public function invalidateUserConversationsCache(string $userId): void
    {
        \Illuminate\Support\Facades\Cache::forget("conversations_user_{$userId}");
    }

    /**
     * Invalidate cache for a conversation
     * 
     * @param string $conversationId The conversation ID
     * @return void
     */
    public function invalidateConversationCache(string $conversationId): void
    {
        \Illuminate\Support\Facades\Cache::forget("last_message_conv_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("conv_messages_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("conv_participants_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("latest_msg_conv_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("msg_count_conv_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("participants_conv_{$conversationId}");
    }

    /**
     * Invalidate unread count cache for a user in a conversation
     * 
     * @param string $userId The user ID
     * @param string $conversationId The conversation ID
     * @return void
     */
    public function invalidateUnreadCountCache(string $userId, string $conversationId): void
    {
        \Illuminate\Support\Facades\Cache::forget("unread_count_user_{$userId}_conv_{$conversationId}");
    }
}
