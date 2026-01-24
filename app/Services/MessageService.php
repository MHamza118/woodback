<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use App\Models\TableNotification;
use App\Traits\ChatHelper;
use Illuminate\Support\Facades\Log;

/**
 * MessageService
 * 
 * Handles all message-related operations:
 * - Retrieving messages
 * - Sending messages (group and private)
 * - Creating message notifications
 * - Message formatting
 */
class MessageService
{
    use ChatHelper;

    protected $oneSignalService;
    protected $conversationService;

    public function __construct(OneSignalService $oneSignalService, ConversationService $conversationService)
    {
        $this->oneSignalService = $oneSignalService;
        $this->conversationService = $conversationService;
    }

    /**
     * Get all messages for a conversation
     * 
     * @param string $conversationId The conversation ID
     * @param string $userId The user ID
     * @param string $userType The user type
     * @return array Array of formatted messages
     * @throws \Exception
     */
    public function getMessages(string $conversationId, string $userId, string $userType): array
    {
        // Get conversation to determine type
        $conversation = Conversation::select('id', 'type')->find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversation not found');
        }

        // Check if user is participant in this conversation
        if (!$this->isUserParticipant($conversationId, $userId)) {
            throw new \Exception('You are not a participant in this conversation');
        }

        // Use cache for messages (5 seconds - short TTL for real-time feel)
        $cacheKey = "messages_conv_{$conversationId}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 5, function () use ($conversation, $conversationId) {
            // Route to appropriate message type based on conversation type
            if ($conversation->type === 'group') {
                $messages = GroupMessage::where('conversation_id', $conversationId)
                    ->select('id', 'conversation_id', 'sender_id', 'sender_name', 'sender_type', 'content', 'attachments', 'has_attachments', 'created_at')
                    ->orderBy('created_at', 'asc')
                    ->get();
            } elseif ($conversation->type === 'private') {
                $messages = PrivateMessage::where('conversation_id', $conversationId)
                    ->select('id', 'conversation_id', 'sender_id', 'sender_name', 'sender_type', 'content', 'attachments', 'has_attachments', 'created_at')
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                throw new \Exception('Invalid conversation type');
            }

            return $messages->map(function ($message) {
                return $this->formatMessageData($message);
            })->toArray();
        });
    }

    /**
     * Send a message to a conversation
     * 
     * @param string $conversationId The conversation ID
     * @param string $content The message content
     * @param array|null $attachments Optional attachments
     * @param bool $hasAttachments Whether message has attachments
     * @param string $userId The sender user ID
     * @param string $userType The sender user type
     * @param mixed $user The authenticated user object
     * @return array The created message data
     * @throws \Exception
     */
    public function sendMessage(
        string $conversationId,
        string $content,
        ?array $attachments,
        bool $hasAttachments,
        string $userId,
        string $userType,
        $user
    ): array {
        Log::info('=== SEND MESSAGE START ===');
        Log::info('Conversation ID: ' . $conversationId);
        Log::info('User ID: ' . $userId . ', User Type: ' . $userType);

        try {
            // Get conversation to determine type
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new \Exception('Conversation not found');
            }

            // Check if user is participant in this conversation
            if (!$this->isUserParticipant($conversationId, $userId)) {
                throw new \Exception('You are not a participant in this conversation');
            }

            // Get sender name
            $senderName = $this->getSenderName($user, $userType);

            Log::info('Conversation type: ' . $conversation->type);

            // Route to appropriate message type based on conversation type
            if ($conversation->type === 'group') {
                $message = GroupMessage::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => $userType,
                    'sender_name' => $senderName,
                    'content' => $content,
                    'attachments' => $attachments,
                    'has_attachments' => $hasAttachments
                ]);
            } elseif ($conversation->type === 'private') {
                $message = PrivateMessage::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => $userType,
                    'sender_name' => $senderName,
                    'content' => $content,
                    'attachments' => $attachments,
                    'has_attachments' => $hasAttachments,
                    'is_read' => false
                ]);
            } else {
                throw new \Exception('Invalid conversation type');
            }

            Log::info('Message created with ID: ' . $message->id);

            // Send OneSignal push notification to other participants
            $this->sendPushNotificationToParticipants($conversation, $message, $userId, $senderName);

            Log::info('About to call createMessageNotifications');

            // Create bell icon notifications for recipients
            $this->createMessageNotifications($conversation, $message, $userId, $senderName, $userType);

            // Invalidate cache for this conversation and all participants
            $this->invalidateConversationCache($conversationId);
            $participants = ConversationParticipant::where('conversation_id', $conversationId)
                ->pluck('participant_id')
                ->toArray();
            foreach ($participants as $participantId) {
                $this->conversationService->invalidateUserConversationsCache($participantId);
            }

            Log::info('=== SEND MESSAGE END ===');

            return $this->formatMessageData($message);
        } catch (\Exception $e) {
            Log::error('Exception in sendMessage: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Send a group message
     * 
     * @param string $conversationId The conversation ID
     * @param string $content The message content
     * @param array|null $attachments Optional attachments
     * @param bool $hasAttachments Whether message has attachments
     * @param string $userId The sender user ID
     * @param string $userType The sender user type
     * @param mixed $user The authenticated user object
     * @return array The created message data
     * @throws \Exception
     */
    public function sendGroupMessage(
        string $conversationId,
        string $content,
        ?array $attachments,
        bool $hasAttachments,
        string $userId,
        string $userType,
        $user
    ): array {
        // Ensure the conversation is a group conversation
        $conversation = Conversation::where('id', $conversationId)
            ->where('type', 'group')
            ->first();

        if (!$conversation) {
            throw new \Exception('Group conversation not found');
        }

        // Check if user is participant in this conversation
        if (!$this->isUserParticipant($conversationId, $userId)) {
            throw new \Exception('You are not a participant in this group');
        }

        return $this->sendMessage(
            $conversationId,
            $content,
            $attachments,
            $hasAttachments,
            $userId,
            $userType,
            $user
        );
    }

    /**
     * Send a private message (creates conversation if doesn't exist)
     * 
     * @param string $recipientId The recipient ID
     * @param string $content The message content
     * @param array|null $attachments Optional attachments
     * @param bool $hasAttachments Whether message has attachments
     * @param string $userId The sender user ID
     * @param string $userType The sender user type
     * @param mixed $user The authenticated user object
     * @param ConversationService $conversationService The conversation service
     * @return array The created message data with conversation ID
     * @throws \Exception
     */
    public function sendPrivateMessage(
        string $recipientId,
        string $content,
        ?array $attachments,
        bool $hasAttachments,
        string $userId,
        string $userType,
        $user,
        ConversationService $conversationService
    ): array {
        // Get or create private conversation
        $conversationData = $conversationService->getOrCreatePrivateConversation(
            $userId,
            $userType,
            $recipientId
        );

        $conversationId = $conversationData['id'];

        // Send the message
        $messageData = $this->sendMessage(
            $conversationId,
            $content,
            $attachments,
            $hasAttachments,
            $userId,
            $userType,
            $user
        );

        // Add conversation ID to response
        $messageData['conversationId'] = $conversationId;

        return $messageData;
    }

    /**
     * Send push notification to conversation participants (except sender)
     * 
     * @param Conversation $conversation The conversation object
     * @param mixed $message The message object
     * @param string $senderId The sender ID
     * @param string $senderName The sender name
     * @return void
     */
    private function sendPushNotificationToParticipants(
        Conversation $conversation,
        $message,
        string $senderId,
        string $senderName
    ): void {
        // OneSignal message notifications disabled - causing production issues
        // Message notifications are handled via polling in the frontend NotificationContext
        return;
    }

    /**
     * Create bell icon notifications for message recipients
     * 
     * @param Conversation $conversation The conversation object
     * @param mixed $message The message object
     * @param string $senderId The sender ID
     * @param string $senderName The sender name
     * @param string $senderType The sender type
     * @return void
     */
    private function createMessageNotifications(
        Conversation $conversation,
        $message,
        string $senderId,
        string $senderName,
        string $senderType
    ): void {
        try {
            Log::info('=== CREATE MESSAGE NOTIFICATIONS START ===');
            Log::info('Conversation ID: ' . $conversation->id);
            Log::info('Sender ID: ' . $senderId);
            Log::info('Sender Type: ' . $senderType);
            
            // Get all participants except the sender
            $participants = ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('participant_id', '!=', $senderId)
                ->get();

            Log::info('Participants count: ' . $participants->count());
            
            // Truncate message content for notification
            $messagePreview = strlen($message->content) > 50 
                ? substr($message->content, 0, 50) . '...' 
                : $message->content;

            // Admin roles that should receive admin notifications
            $adminRoles = ['admin', 'owner', 'manager', 'hiring_manager', 'expo'];

            foreach ($participants as $participant) {
                Log::info('Processing participant: ID=' . $participant->participant_id . ', Type=' . $participant->participant_type);
                
                // Determine recipient type (admin or employee)
                // Check if participant_type is an admin role
                $isAdminRole = in_array($participant->participant_type, $adminRoles);
                $recipientType = $isAdminRole
                    ? TableNotification::RECIPIENT_ADMIN 
                    : TableNotification::RECIPIENT_EMPLOYEE;

                Log::info('Recipient type determined: ' . $recipientType);

                // For admin recipients, use null as recipient_id so it shows for all admins
                // For employee recipients, use the actual employee ID
                $recipientId = $isAdminRole ? null : (int)$participant->participant_id;

                Log::info('Recipient ID set to: ' . ($recipientId ?? 'null'));

                // Create notification
                $notification = TableNotification::create([
                    'type' => TableNotification::TYPE_NEW_MESSAGE,
                    'title' => 'New Message from ' . $senderName,
                    'message' => $messagePreview,
                    'order_number' => null,
                    'recipient_type' => $recipientType,
                    'recipient_id' => $recipientId,
                    'priority' => TableNotification::PRIORITY_MEDIUM,
                    'data' => [
                        'conversation_id' => $conversation->id,
                        'conversation_name' => $conversation->name,
                        'message_id' => $message->id,
                        'sender_id' => $senderId,
                        'sender_name' => $senderName,
                        'sender_type' => $senderType
                    ],
                    'is_read' => false
                ]);
                
                Log::info('Notification created with ID: ' . $notification->id);
            }
            
            Log::info('=== CREATE MESSAGE NOTIFICATIONS END ===');
        } catch (\Exception $e) {
            // Log error but don't fail the message send
            Log::error('Failed to create message notifications: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Verify user is a participant in a conversation
     * 
     * @param string $conversationId The conversation ID
     * @param string $userId The user ID
     * @return bool True if user is a participant
     */
    private function isUserParticipant(string $conversationId, string $userId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('participant_id', $userId)
            ->exists();
    }

    /**
     * Get group message statistics
     * 
     * @param string $conversationId The conversation ID
     * @return array Statistics data
     * @throws \Exception
     */
    public function getGroupStats(string $conversationId): array
    {
        $conversation = Conversation::where('id', $conversationId)
            ->where('type', 'group')
            ->first();

        if (!$conversation) {
            throw new \Exception('Group conversation not found');
        }

        return [
            'total_messages' => GroupMessage::where('conversation_id', $conversationId)->count(),
            'participants_count' => $conversation->participants()->count(),
            'messages_with_attachments' => GroupMessage::where('conversation_id', $conversationId)
                ->withAttachments()->count(),
            'last_activity' => GroupMessage::where('conversation_id', $conversationId)
                ->latest()->first()?->created_at?->toISOString()
        ];
    }

    /**
     * Invalidate cache for a conversation
     * 
     * @param string $conversationId The conversation ID
     * @return void
     */
    private function invalidateConversationCache(string $conversationId): void
    {
        \Illuminate\Support\Facades\Cache::forget("messages_conv_{$conversationId}");
    }
}
