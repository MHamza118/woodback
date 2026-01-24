<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MessageService;
use App\Traits\ApiResponseTrait;
use App\Traits\ChatHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupMessageController extends Controller
{
    use ApiResponseTrait, ChatHelper;

    protected $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Get all group messages for a conversation
     */
    public function index(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->getMessages($conversationId, $userId, $userType);

            return $this->successResponse($messageData, 'Group messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve group messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a message to a group conversation
     */
    public function store(Request $request, $conversationId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $messageData = $this->messageService->sendGroupMessage(
                $conversationId,
                $request->content,
                $request->attachments,
                $request->has_attachments ?? false,
                $userId,
                $userType,
                $user
            );

            return $this->successResponse($messageData, 'Group message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send group message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get group message statistics
     */
    public function getGroupStats($conversationId): JsonResponse
    {
        try {
            $stats = $this->messageService->getGroupStats($conversationId);

            return $this->successResponse($stats, 'Group statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get group statistics: ' . $e->getMessage(), 500);
        }
    }
}
