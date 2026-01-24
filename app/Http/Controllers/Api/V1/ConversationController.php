<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ConversationService;
use App\Services\ChatService;
use App\Traits\ApiResponseTrait;
use App\Traits\ChatHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    use ApiResponseTrait, ChatHelper;

    protected $conversationService;
    protected $chatService;

    public function __construct(ConversationService $conversationService, ChatService $chatService)
    {
        $this->conversationService = $conversationService;
        $this->chatService = $chatService;
    }

    /**
     * Get all conversations for a user (admin or employee)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $conversationData = $this->conversationService->getConversations($userId, $userType);

            return $this->successResponse($conversationData, 'Conversations retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve conversations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new group conversation
     */
    public function createGroup(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'required|integer|exists:employees,id'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            // Only admins can create groups
            if ($userType !== 'admin') {
                return $this->errorResponse('Only administrators can create group conversations', 403);
            }

            $conversationData = $this->conversationService->createGroupConversation(
                $request->name,
                $request->employee_ids,
                $userId
            );

            // Invalidate admin chat cache
            $this->chatService->invalidateEmployeeConversationsCache();

            return $this->successResponse($conversationData, 'Group created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get or create a private conversation between admin and employee
     */
    public function getOrCreatePrivateConversation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'participant_id' => 'required|string'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);
            $participantId = $request->participant_id;

            $conversationData = $this->conversationService->getOrCreatePrivateConversation(
                $userId,
                $userType,
                $participantId
            );

            // Invalidate admin chat cache if both participants are employees
            $this->chatService->invalidateEmployeeConversationsCache();

            return $this->successResponse($conversationData, 'Private conversation retrieved/created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get/create private conversation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add employees to existing group
     */
    public function addParticipants(Request $request, $conversationId): JsonResponse
    {
        try {
            $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'required|integer|exists:employees,id'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);

            // Only admins can add participants
            if ($userType !== 'admin') {
                return $this->errorResponse('Only administrators can add participants', 403);
            }

            $this->conversationService->addParticipantsToGroup($conversationId, $request->employee_ids);

            // Invalidate admin chat cache
            $this->chatService->invalidateEmployeeConversationsCache();
            $this->chatService->invalidateConversationCache($conversationId);

            return $this->successResponse(null, 'Participants added successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add participants: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark messages as read for a user in a conversation
     */
    public function markAsRead(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $this->conversationService->markConversationAsRead($conversationId, $userId, $userType);

            return $this->successResponse(null, 'Messages marked as read');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark messages as read: ' . $e->getMessage(), 500);
        }
    }
}
