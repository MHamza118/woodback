<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeMessagesController extends Controller
{
    use ApiResponseTrait;

    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Get all private conversations between employees (admin only)
     */
    public function getEmployeeConversations(Request $request): JsonResponse
    {
        try {
            $conversations = $this->chatService->getEmployeeConversations();

            return $this->successResponse(
                $conversations,
                'Employee conversations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employee conversations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get messages for a specific employee conversation
     */
    public function getConversationMessages(Request $request, $conversationId): JsonResponse
    {
        try {
            $conversationData = $this->chatService->getConversationMessages($conversationId);

            return $this->successResponse($conversationData, 'Conversation messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve conversation messages: ' . $e->getMessage(), 500);
        }
    }
}
