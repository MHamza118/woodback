<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MessageService;
use App\Services\ConversationService;
use App\Services\ChatService;
use App\Traits\ApiResponseTrait;
use App\Traits\ChatHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    use ApiResponseTrait, ChatHelper;

    protected $messageService;
    protected $conversationService;
    protected $chatService;

    public function __construct(MessageService $messageService, ConversationService $conversationService, ChatService $chatService)
    {
        $this->messageService = $messageService;
        $this->conversationService = $conversationService;
        $this->chatService = $chatService;
    }

    /**
     * Get all messages for a conversation (routes to appropriate message type)
     */
    public function index(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->getMessages($conversationId, $userId, $userType);

            return $this->successResponse($messageData, 'Messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a message to a conversation (routes to appropriate message type)
     */
    public function store(Request $request, $conversationId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required_without:attachments|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->sendMessage(
                $conversationId,
                $request->content,
                $request->attachments,
                $request->has_attachments ?? false,
                $userId,
                $userType,
                $user
            );

            // Invalidate admin chat cache if this is a private conversation between employees
            $this->chatService->invalidateEmployeeConversationsCache();
            $this->chatService->invalidateConversationCache($conversationId);

            return $this->successResponse($messageData, 'Message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a group message
     */
    public function sendGroupMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'content' => 'required_without:attachments|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->sendGroupMessage(
                $request->conversation_id,
                $request->content,
                $request->attachments,
                $request->has_attachments ?? false,
                $userId,
                $userType,
                $user
            );

            // Invalidate admin chat cache
            $this->chatService->invalidateEmployeeConversationsCache();
            $this->chatService->invalidateConversationCache($request->conversation_id);

            return $this->successResponse($messageData, 'Group message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send group message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a private message (creates conversation if doesn't exist)
     */
    public function sendPrivateMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'recipient_id' => 'required|string',
                'content' => 'required_without:attachments|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->sendPrivateMessage(
                $request->recipient_id,
                $request->content,
                $request->attachments,
                $request->has_attachments ?? false,
                $userId,
                $userType,
                $user,
                $this->conversationService
            );

            // Invalidate admin chat cache if both participants are employees
            $this->chatService->invalidateEmployeeConversationsCache();
            $this->chatService->invalidateConversationCache($messageData['conversationId']);

            return $this->successResponse($messageData, 'Private message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send private message: ' . $e->getMessage(), 500);
        }
    }
}
