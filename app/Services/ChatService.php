<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\PrivateMessage;
use App\Models\Employee;
use App\Traits\ChatHelper;
use Illuminate\Support\Facades\Storage;

/**
 * ChatService
 * 
 * Orchestrates chat operations for admin monitoring:
 * - Getting employee-to-employee conversations
 * - Getting conversation messages with participant info
 * - Formatting chat data for admin views
 */
class ChatService
{
    use ChatHelper;

    /**
     * Get all private conversations between employees (admin only)
     * 
     * @return array Array of employee conversations
     */
    public function getEmployeeConversations(): array
    {
        // Cache for 30 seconds
        return \Illuminate\Support\Facades\Cache::remember('employee_conversations', 30, function () {
            // Optimized query with proper joins and selects
            $conversations = Conversation::where('type', 'private')
                ->select('conversations.id', 'conversations.name', 'conversations.type', 'conversations.created_at')
                ->with([
                    'participants' => function ($query) {
                        $query->select('id', 'conversation_id', 'participant_id', 'participant_type')
                            ->where('participant_type', 'employee');
                    }
                ])
                ->get()
                ->filter(function ($conv) {
                    // Only include conversations where both participants are employees
                    return $conv->participants->count() === 2 &&
                           $conv->participants->every(function ($p) {
                               return $p->participant_type === 'employee';
                           });
                })
                ->map(function ($conversation) {
                    return $this->formatEmployeeConversation($conversation);
                })
                ->sortByDesc('lastMessageTime')
                ->values()
                ->toArray();

            return $conversations;
        });
    }

    /**
     * Format employee conversation data
     * 
     * @param Conversation $conversation The conversation object
     * @return array Formatted conversation data
     */
    private function formatEmployeeConversation(Conversation $conversation): array
    {
        // Get the latest message (cache for 60 seconds)
        $latestMessage = \Illuminate\Support\Facades\Cache::remember(
            "latest_msg_conv_{$conversation->id}",
            60,
            function () use ($conversation) {
                return PrivateMessage::where('conversation_id', $conversation->id)
                    ->select('id', 'content', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                    ->first();
            }
        );

        // Get message count (cache for 60 seconds)
        $messageCount = \Illuminate\Support\Facades\Cache::remember(
            "msg_count_conv_{$conversation->id}",
            60,
            function () use ($conversation) {
                return PrivateMessage::where('conversation_id', $conversation->id)->count();
            }
        );

        // Get participant names and profile images (cache for 1 hour)
        $participants = \Illuminate\Support\Facades\Cache::remember(
            "participants_conv_{$conversation->id}",
            3600,
            function () use ($conversation) {
                return $conversation->participants->map(function ($p) {
                    $employee = Employee::select('id', 'first_name', 'last_name', 'profile_image')->find($p->participant_id);
                    $profileImageUrl = null;
                    if ($employee && $employee->profile_image) {
                        $profileImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($employee->profile_image);
                    }
                    return [
                        'id' => $p->participant_id,
                        'name' => $employee ? $employee->first_name . ' ' . $employee->last_name : 'Unknown',
                        'profile_image' => $profileImageUrl
                    ];
                })->toArray();
            }
        );

        return [
            'id' => $conversation->id,
            'participants' => $participants,
            'participantNames' => collect($participants)->pluck('name')->join(' & '),
            'lastMessage' => $latestMessage ? substr($latestMessage->content, 0, 100) : 'No messages',
            'lastMessageTime' => $latestMessage ? $latestMessage->created_at->toISOString() : null,
            'messageCount' => $messageCount,
            'createdAt' => $conversation->created_at->toISOString()
        ];
    }

    /**
     * Get messages for a specific employee conversation
     * 
     * @param string $conversationId The conversation ID
     * @return array Conversation data with messages and participants
     * @throws \Exception
     */
    public function getConversationMessages(string $conversationId): array
    {
        // Cache for 10 seconds
        return \Illuminate\Support\Facades\Cache::remember("conv_messages_{$conversationId}", 10, function () use ($conversationId) {
            $conversation = Conversation::select('id', 'type', 'created_at')->find($conversationId);

            if (!$conversation) {
                throw new \Exception('Conversation not found');
            }

            if ($conversation->type !== 'private') {
                throw new \Exception('Invalid conversation type');
            }

            // Verify both participants are employees
            $participants = ConversationParticipant::where('conversation_id', $conversationId)
                ->select('id', 'conversation_id', 'participant_id', 'participant_type')
                ->get();
            
            if ($participants->count() !== 2 || $participants->where('participant_type', '!=', 'employee')->count() > 0) {
                throw new \Exception('This is not an employee-to-employee conversation');
            }

            // Get all messages with optimized select
            $messages = PrivateMessage::where('conversation_id', $conversationId)
                ->select('id', 'conversation_id', 'sender_id', 'content', 'attachments', 'has_attachments', 'created_at')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return $this->formatConversationMessage($message);
                });

            // Get participant info (cache for 1 hour)
            $participantInfo = \Illuminate\Support\Facades\Cache::remember(
                "conv_participants_{$conversationId}",
                3600,
                function () use ($participants) {
                    return $participants->map(function ($p) {
                        $employee = Employee::select('id', 'first_name', 'last_name', 'email', 'profile_image')->find($p->participant_id);
                        $profileImageUrl = null;
                        if ($employee && $employee->profile_image) {
                            $profileImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($employee->profile_image);
                        }
                        return [
                            'id' => $p->participant_id,
                            'name' => $employee ? $employee->first_name . ' ' . $employee->last_name : 'Unknown',
                            'email' => $employee ? $employee->email : 'N/A',
                            'profile_image' => $profileImageUrl
                        ];
                    })->toArray();
                }
            );

            return [
                'conversationId' => $conversation->id,
                'participants' => $participantInfo,
                'messages' => $messages->toArray(),
                'messageCount' => $messages->count(),
                'createdAt' => $conversation->created_at->toISOString()
            ];
        });
    }

    /**
     * Format a conversation message with sender info
     * 
     * @param PrivateMessage $message The message object
     * @return array Formatted message data
     */
    private function formatConversationMessage(PrivateMessage $message): array
    {
        // Cache sender info for 1 hour
        $senderInfo = \Illuminate\Support\Facades\Cache::remember(
            "sender_info_{$message->sender_id}",
            3600,
            function () use ($message) {
                $sender = Employee::select('id', 'first_name', 'last_name', 'profile_image')->find($message->sender_id);
                $profileImageUrl = null;
                if ($sender && $sender->profile_image) {
                    $profileImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($sender->profile_image);
                }
                return [
                    'name' => $sender ? $sender->first_name . ' ' . $sender->last_name : 'Unknown',
                    'profile_image' => $profileImageUrl
                ];
            }
        );
        
        return [
            'id' => $message->id,
            'senderId' => $message->sender_id,
            'senderName' => $senderInfo['name'],
            'senderProfileImage' => $senderInfo['profile_image'],
            'content' => $message->content,
            'attachments' => $message->attachments,
            'hasAttachments' => $message->has_attachments,
            'timestamp' => $message->created_at->toISOString()
        ];
    }

    /**
     * Invalidate cache for employee conversations
     * 
     * @return void
     */
    public function invalidateEmployeeConversationsCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('employee_conversations');
    }

    /**
     * Invalidate cache for a specific conversation
     * 
     * @param string $conversationId The conversation ID
     * @return void
     */
    public function invalidateConversationCache(string $conversationId): void
    {
        \Illuminate\Support\Facades\Cache::forget("conv_messages_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("latest_msg_conv_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("msg_count_conv_{$conversationId}");
        \Illuminate\Support\Facades\Cache::forget("conv_participants_{$conversationId}");
    }
}
