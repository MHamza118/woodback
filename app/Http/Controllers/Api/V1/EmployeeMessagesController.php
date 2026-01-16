<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\PrivateMessage;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeMessagesController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all private conversations between employees (admin only)
     */
    public function getEmployeeConversations(Request $request): JsonResponse
    {
        try {
            // Get all private conversations where both participants are employees
            $conversations = Conversation::where('type', 'private')
                ->with(['participants' => function ($query) {
                    $query->where('participant_type', 'employee');
                }])
                ->get()
                ->filter(function ($conv) {
                    // Only include conversations where both participants are employees
                    return $conv->participants->count() === 2 &&
                           $conv->participants->every(function ($p) {
                               return $p->participant_type === 'employee';
                           });
                })
                ->map(function ($conversation) {
                    // Get the latest message
                    $latestMessage = PrivateMessage::where('conversation_id', $conversation->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    // Get participant names and profile images
                    $participants = $conversation->participants->map(function ($p) {
                        $employee = \App\Models\Employee::find($p->participant_id);
                        $profileImageUrl = null;
                        if ($employee && $employee->profile_image) {
                            $profileImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($employee->profile_image);
                        }
                        return [
                            'id' => $p->participant_id,
                            'name' => $employee ? $employee->first_name . ' ' . $employee->last_name : 'Unknown',
                            'profile_image' => $profileImageUrl
                        ];
                    });

                    return [
                        'id' => $conversation->id,
                        'participants' => $participants,
                        'participantNames' => $participants->pluck('name')->join(' & '),
                        'lastMessage' => $latestMessage ? substr($latestMessage->content, 0, 100) : 'No messages',
                        'lastMessageTime' => $latestMessage ? $latestMessage->created_at->toISOString() : null,
                        'messageCount' => PrivateMessage::where('conversation_id', $conversation->id)->count(),
                        'createdAt' => $conversation->created_at->toISOString()
                    ];
                })
                ->sortByDesc('lastMessageTime')
                ->values();

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
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return $this->errorResponse('Conversation not found', 404);
            }

            if ($conversation->type !== 'private') {
                return $this->errorResponse('Invalid conversation type', 400);
            }

            // Verify both participants are employees
            $participants = ConversationParticipant::where('conversation_id', $conversationId)->get();
            if ($participants->count() !== 2 || $participants->where('participant_type', '!=', 'employee')->count() > 0) {
                return $this->errorResponse('This is not an employee-to-employee conversation', 400);
            }

            // Get all messages
            $messages = PrivateMessage::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    $sender = \App\Models\Employee::find($message->sender_id);
                    $profileImageUrl = null;
                    if ($sender && $sender->profile_image) {
                        $profileImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($sender->profile_image);
                    }
                    return [
                        'id' => $message->id,
                        'senderId' => $message->sender_id,
                        'senderName' => $sender ? $sender->first_name . ' ' . $sender->last_name : 'Unknown',
                        'senderProfileImage' => $profileImageUrl,
                        'content' => $message->content,
                        'attachments' => $message->attachments,
                        'hasAttachments' => $message->has_attachments,
                        'timestamp' => $message->created_at->toISOString()
                    ];
                });

            // Get participant info
            $participantInfo = $participants->map(function ($p) {
                $employee = \App\Models\Employee::find($p->participant_id);
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
            });

            return $this->successResponse([
                'conversationId' => $conversation->id,
                'participants' => $participantInfo,
                'messages' => $messages,
                'messageCount' => $messages->count(),
                'createdAt' => $conversation->created_at->toISOString()
            ], 'Conversation messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve conversation messages: ' . $e->getMessage(), 500);
        }
    }
}
