<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\EmployeeShoutout;
use App\Models\EmployeeReward;
use App\Models\EmployeeBadge;
use App\Models\RewardType;
use App\Models\BadgeType;
use App\Models\EmployeePerformance;
use App\Models\Employee;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\PrivateMessage;
use Illuminate\Validation\ValidationException;

class EmployeeRecognitionController extends Controller
{
    use ApiResponseTrait;

    // =================== ADMIN ENDPOINTS ===================

    /**
     * Get recognition statistics (Admin)
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_shoutouts' => EmployeeShoutout::count(),
                'total_rewards' => EmployeeReward::count(),
                'total_badges' => EmployeeBadge::count(),
                'pending_rewards' => EmployeeReward::pending()->count(),
                'active_employees' => Employee::approved()->count(),
            ];

            return $this->successResponse($stats, 'Recognition statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get recent recognition activity (Admin)
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            // Get only the most recent one from each type
            $latestShoutout = EmployeeShoutout::with(['employee', 'recognizedBy'])
                ->orderBy('created_at', 'desc')
                ->first();
            
            $latestReward = EmployeeReward::with(['employee', 'rewardType', 'givenBy'])
                ->orderBy('created_at', 'desc')
                ->first();
            
            $latestBadge = EmployeeBadge::with(['employee', 'badgeType', 'awardedBy'])
                ->orderBy('awarded_at', 'desc')
                ->first();

            $activity = [];

            if ($latestShoutout) {
                $activity[] = [
                    'type' => 'shoutout',
                    'id' => $latestShoutout->id,
                    'employee_id' => $latestShoutout->employee_id,
                    'employee_name' => $latestShoutout->employee->full_name,
                    'recognized_by' => $latestShoutout->recognizedBy->name ?? 'Unknown',
                    'category' => $latestShoutout->category,
                    'message' => $latestShoutout->message,
                    'likes' => $latestShoutout->likes,
                    'created_at' => $latestShoutout->created_at->toISOString(),
                ];
            }

            if ($latestReward) {
                $activity[] = [
                    'type' => 'reward',
                    'id' => $latestReward->id,
                    'employee_id' => $latestReward->employee_id,
                    'employee_name' => $latestReward->employee->full_name,
                    'given_by' => $latestReward->givenBy->name ?? 'Unknown',
                    'reward_name' => $latestReward->rewardType->name,
                    'reward_icon' => $latestReward->rewardType->icon,
                    'reason' => $latestReward->reason,
                    'status' => $latestReward->status,
                    'created_at' => $latestReward->created_at->toISOString(),
                ];
            }

            if ($latestBadge) {
                $activity[] = [
                    'type' => 'badge',
                    'id' => $latestBadge->id,
                    'employee_id' => $latestBadge->employee_id,
                    'employee_name' => $latestBadge->employee->full_name,
                    'awarded_by' => $latestBadge->awardedBy->name ?? 'Unknown',
                    'badge' => [
                        'name' => $latestBadge->badgeType->name,
                        'icon' => $latestBadge->badgeType->icon,
                        'color' => $latestBadge->badgeType->color,
                        'description' => $latestBadge->badgeType->description,
                    ],
                    'reason' => $latestBadge->reason,
                    'awarded_at' => $latestBadge->awarded_at->toISOString(),
                    'created_at' => $latestBadge->awarded_at->toISOString(),
                ];
            }

            // Sort by timestamp descending
            usort($activity, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return $this->successResponse([
                'activity' => $activity
            ], 'Recent activity retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve activity: ' . $e->getMessage());
        }
    }

    /**
     * Get top performers (Admin)
     */
    public function getTopPerformers(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            
            $performers = EmployeePerformance::with('employee')
                ->orderBy('total_points', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($performance) {
                    return [
                        'employee_id' => $performance->employee_id,
                        'employee_name' => $performance->employee->full_name,
                        'employee_position' => $performance->employee->position,
                        'total_points' => $performance->total_points,
                        'total_shoutouts' => $performance->total_shoutouts,
                        'total_rewards' => $performance->total_rewards,
                        'total_badges' => $performance->total_badges,
                    ];
                });

            return $this->successResponse([
                'top_performers' => $performers
            ], 'Top performers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve top performers: ' . $e->getMessage());
        }
    }

    // =================== SHOUTOUTS ===================

    /**
     * Create shoutout (Admin)
     */
    public function createShoutout(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'category' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            $shoutout = EmployeeShoutout::create([
                'employee_id' => $request->employee_id,
                'recognized_by' => $request->user()->id,
                'category' => $request->category,
                'message' => $request->message,
                'likes' => 0,
            ]);

            // Update employee performance
            $this->updatePerformance($request->employee_id, 'shoutout');

            // Create notification for employee
            \App\Models\TableNotification::create([
                'type' => \App\Models\TableNotification::TYPE_SHOUTOUT_RECEIVED,
                'title' => 'New Shout-out Received! 🎉',
                'message' => 'You received a shout-out for ' . $request->category . ' from ' . $request->user()->name,
                'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
                'recipient_id' => $request->employee_id,
                'priority' => \App\Models\TableNotification::PRIORITY_MEDIUM,
                'is_read' => false,
                'data' => [
                    'shoutout_id' => $shoutout->id,
                    'category' => $request->category,
                    'recognizer' => $request->user()->name
                ]
            ]);

            $shoutout->load(['employee', 'recognizedBy']);

            // Send shout-out as chat message to employee
            try {
                $this->sendShoutoutChatMessage(
                    $request->employee_id, 
                    $shoutout->employee->full_name,
                    $request->category, 
                    $request->message, 
                    $request->user()->name
                );
            } catch (\Exception $chatError) {
                // Log error but don't fail the shout-out creation
                \Log::error('Failed to send shout-out chat message: ' . $chatError->getMessage());
            }

            return $this->successResponse([
                'shoutout' => [
                    'id' => $shoutout->id,
                    'employee_name' => $shoutout->employee->full_name,
                    'recognized_by' => $shoutout->recognizedBy->name,
                    'category' => $shoutout->category,
                    'message' => $shoutout->message,
                    'created_at' => $shoutout->created_at->toISOString(),
                ]
            ], 'Shoutout created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create shoutout: ' . $e->getMessage());
        }
    }

    /**
     * Get all shoutouts (Admin)
     */
    public function getAllShoutouts(Request $request): JsonResponse
    {
        try {
            $shoutouts = EmployeeShoutout::with(['employee', 'recognizedBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($shoutout) {
                    return [
                        'id' => $shoutout->id,
                        'employee_id' => $shoutout->employee_id,
                        'employee_name' => $shoutout->employee->full_name,
                        'recognized_by' => $shoutout->recognizedBy->name ?? 'Unknown',
                        'category' => $shoutout->category,
                        'message' => $shoutout->message,
                        'likes' => $shoutout->likes,
                        'created_at' => $shoutout->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'shoutouts' => $shoutouts
            ], 'Shoutouts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve shoutouts: ' . $e->getMessage());
        }
    }

    /**
     * Delete shoutout (Admin)
     */
    public function deleteShoutout(Request $request, $id): JsonResponse
    {
        try {
            $shoutout = EmployeeShoutout::find($id);
            
            if (!$shoutout) {
                return $this->notFoundResponse('Shoutout not found');
            }

            $shoutout->delete();

            return $this->successResponse(null, 'Shoutout deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete shoutout: ' . $e->getMessage());
        }
    }

    // =================== REWARDS ===================

    /**
     * Give reward (Admin)
     */
    public function giveReward(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'reward_type_id' => 'required|exists:reward_types,id',
                'reason' => 'required|string',
            ]);

            $reward = EmployeeReward::create([
                'employee_id' => $request->employee_id,
                'reward_type_id' => $request->reward_type_id,
                'given_by' => $request->user()->id,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            // Update employee performance
            $rewardType = RewardType::find($request->reward_type_id);
            $points = $rewardType->type === 'points' ? $rewardType->value : 0;
            $this->updatePerformance($request->employee_id, 'reward', $points);

            // Create notification for employee
            \App\Models\TableNotification::create([
                'type' => \App\Models\TableNotification::TYPE_REWARD_RECEIVED,
                'title' => 'New Reward Received! 🎁',
                'message' => 'You received a reward: ' . $rewardType->name . ' from ' . $request->user()->name,
                'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
                'recipient_id' => $request->employee_id,
                'priority' => \App\Models\TableNotification::PRIORITY_HIGH,
                'is_read' => false,
                'data' => [
                    'reward_id' => $reward->id,
                    'reward_name' => $rewardType->name,
                    'reward_type' => $rewardType->type,
                    'reward_value' => $rewardType->value,
                    'giver' => $request->user()->name
                ]
            ]);

            $reward->load(['employee', 'rewardType', 'givenBy']);

            return $this->successResponse([
                'reward' => [
                    'id' => $reward->id,
                    'employee_name' => $reward->employee->full_name,
                    'reward_name' => $reward->rewardType->name,
                    'given_by' => $reward->givenBy->name,
                    'reason' => $reward->reason,
                    'status' => $reward->status,
                    'created_at' => $reward->created_at->toISOString(),
                ]
            ], 'Reward given successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to give reward: ' . $e->getMessage());
        }
    }

    /**
     * Get all rewards (Admin)
     */
    public function getAllRewards(Request $request): JsonResponse
    {
        try {
            $rewards = EmployeeReward::with(['employee', 'rewardType', 'givenBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reward) {
                    return [
                        'id' => $reward->id,
                        'employee_id' => $reward->employee_id,
                        'employee_name' => $reward->employee->full_name,
                        'reward_type_id' => $reward->reward_type_id,
                        'reward_name' => $reward->rewardType->name,
                        'reward_icon' => $reward->rewardType->icon,
                        'reward_type' => $reward->rewardType->type,
                        'reward_value' => $reward->rewardType->value,
                        'reward_description' => $reward->rewardType->description,
                        'given_by' => $reward->givenBy->name ?? 'Unknown',
                        'reason' => $reward->reason,
                        'status' => $reward->status,
                        'redeemed_at' => $reward->redeemed_at?->toISOString(),
                        'created_at' => $reward->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'rewards' => $rewards
            ], 'Rewards retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve rewards: ' . $e->getMessage());
        }
    }

    // =================== REWARD TYPES ===================

    /**
     * Get reward types (Admin & Employee)
     */
    public function getRewardTypes(Request $request): JsonResponse
    {
        try {
            $rewardTypes = RewardType::active()->get();

            return $this->successResponse([
                'reward_types' => $rewardTypes
            ], 'Reward types retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve reward types: ' . $e->getMessage());
        }
    }

    /**
     * Create reward type (Admin)
     */
    public function createRewardType(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:points,gift_card,benefit',
                'value' => 'required|integer',
                'description' => 'required|string',
                'icon' => 'nullable|string|max:10',
            ]);

            $rewardType = RewardType::create($request->all());

            return $this->successResponse([
                'reward_type' => $rewardType
            ], 'Reward type created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create reward type: ' . $e->getMessage());
        }
    }

    /**
     * Update reward type (Admin)
     */
    public function updateRewardType(Request $request, $id): JsonResponse
    {
        try {
            $rewardType = RewardType::find($id);
            
            if (!$rewardType) {
                return $this->notFoundResponse('Reward type not found');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|in:points,gift_card,benefit',
                'value' => 'sometimes|integer',
                'description' => 'sometimes|string',
                'icon' => 'nullable|string|max:10',
                'active' => 'sometimes|boolean',
            ]);

            $rewardType->update($request->all());

            return $this->successResponse([
                'reward_type' => $rewardType
            ], 'Reward type updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update reward type: ' . $e->getMessage());
        }
    }

    /**
     * Delete reward type (Admin)
     */
    public function deleteRewardType(Request $request, $id): JsonResponse
    {
        try {
            $rewardType = RewardType::find($id);
            
            if (!$rewardType) {
                return $this->notFoundResponse('Reward type not found');
            }

            // Check if reward type is being used
            if ($rewardType->employeeRewards()->exists()) {
                return $this->errorResponse('Cannot delete reward type that is in use', 400);
            }

            $rewardType->delete();

            return $this->successResponse(null, 'Reward type deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete reward type: ' . $e->getMessage());
        }
    }

    // =================== BADGES ===================

    /**
     * Award badge (Admin)
     */
    public function awardBadge(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'badge_type_id' => 'required|exists:badge_types,id',
                'reason' => 'required|string',
            ]);

            // Check if employee already has this badge
            $existing = EmployeeBadge::where('employee_id', $request->employee_id)
                ->where('badge_type_id', $request->badge_type_id)
                ->first();

            if ($existing) {
                return $this->errorResponse('Employee already has this badge', 400);
            }

            $badge = EmployeeBadge::create([
                'employee_id' => $request->employee_id,
                'badge_type_id' => $request->badge_type_id,
                'awarded_by' => $request->user()->id,
                'reason' => $request->reason,
                'awarded_at' => now(),
            ]);

            // Update employee performance
            $this->updatePerformance($request->employee_id, 'badge');

            $badge->load(['employee', 'badgeType', 'awardedBy']);

            // Create notification for employee
            \App\Models\TableNotification::create([
                'type' => \App\Models\TableNotification::TYPE_BADGE_RECEIVED,
                'title' => 'New Badge Earned! 🏆',
                'message' => 'You earned the "' . $badge->badgeType->name . '" badge from ' . $request->user()->name,
                'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
                'recipient_id' => $request->employee_id,
                'priority' => \App\Models\TableNotification::PRIORITY_HIGH,
                'is_read' => false,
                'data' => [
                    'badge_id' => $badge->id,
                    'badge_name' => $badge->badgeType->name,
                    'badge_icon' => $badge->badgeType->icon,
                    'badge_color' => $badge->badgeType->color,
                    'awarded_by' => $request->user()->name
                ]
            ]);

            return $this->successResponse([
                'badge' => [
                    'id' => $badge->id,
                    'employee_name' => $badge->employee->full_name,
                    'badge' => [
                        'name' => $badge->badgeType->name,
                        'icon' => $badge->badgeType->icon,
                        'color' => $badge->badgeType->color,
                    ],
                    'awarded_by' => $badge->awardedBy->name,
                    'reason' => $badge->reason,
                    'awarded_at' => $badge->awarded_at->toISOString(),
                ]
            ], 'Badge awarded successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to award badge: ' . $e->getMessage());
        }
    }

    /**
     * Get all badge awards (Admin)
     */
    public function getAllBadges(Request $request): JsonResponse
    {
        try {
            $badges = EmployeeBadge::with(['employee', 'badgeType', 'awardedBy'])
                ->orderBy('awarded_at', 'desc')
                ->get()
                ->map(function ($badge) {
                    return [
                        'id' => $badge->id,
                        'employee_id' => $badge->employee_id,
                        'employee_name' => $badge->employee->full_name,
                        'badge' => [
                            'id' => $badge->badgeType->id,
                            'name' => $badge->badgeType->name,
                            'icon' => $badge->badgeType->icon,
                            'color' => $badge->badgeType->color,
                            'description' => $badge->badgeType->description,
                            'criteria' => $badge->badgeType->criteria,
                        ],
                        'awarded_by' => $badge->awardedBy->name ?? 'Unknown',
                        'reason' => $badge->reason,
                        'awarded_at' => $badge->awarded_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'badges' => $badges
            ], 'Badge awards retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve badge awards: ' . $e->getMessage());
        }
    }

    // =================== BADGE TYPES ===================

    /**
     * Get badge types (Admin & Employee)
     */
    public function getBadgeTypes(Request $request): JsonResponse
    {
        try {
            $badgeTypes = BadgeType::active()->get();

            return $this->successResponse([
                'badge_types' => $badgeTypes
            ], 'Badge types retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve badge types: ' . $e->getMessage());
        }
    }

    /**
     * Create badge type (Admin)
     */
    public function createBadgeType(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'icon' => 'nullable|string|max:10',
                'color' => 'nullable|string|max:20',
                'criteria' => 'required|string',
            ]);

            $badgeType = BadgeType::create($request->all());

            return $this->successResponse([
                'badge_type' => $badgeType
            ], 'Badge type created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create badge type: ' . $e->getMessage());
        }
    }

    /**
     * Update badge type (Admin)
     */
    public function updateBadgeType(Request $request, $id): JsonResponse
    {
        try {
            $badgeType = BadgeType::find($id);
            
            if (!$badgeType) {
                return $this->notFoundResponse('Badge type not found');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'icon' => 'nullable|string|max:10',
                'color' => 'nullable|string|max:20',
                'criteria' => 'sometimes|string',
                'active' => 'sometimes|boolean',
            ]);

            $badgeType->update($request->all());

            return $this->successResponse([
                'badge_type' => $badgeType
            ], 'Badge type updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update badge type: ' . $e->getMessage());
        }
    }

    /**
     * Delete badge type (Admin)
     */
    public function deleteBadgeType(Request $request, $id): JsonResponse
    {
        try {
            $badgeType = BadgeType::find($id);
            
            if (!$badgeType) {
                return $this->notFoundResponse('Badge type not found');
            }

            // Check if badge type is being used
            if ($badgeType->employeeBadges()->exists()) {
                return $this->errorResponse('Cannot delete badge type that is in use', 400);
            }

            $badgeType->delete();

            return $this->successResponse(null, 'Badge type deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete badge type: ' . $e->getMessage());
        }
    }

    // =================== EMPLOYEE ENDPOINTS ===================

    /**
     * Get employee's own shoutouts (Employee)
     */
    public function getMyShoutouts(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->user()->id;
            
            $shoutouts = EmployeeShoutout::with('recognizedBy')
                ->byEmployee($employeeId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($shoutout) {
                    return [
                        'id' => $shoutout->id,
                        'recognized_by' => $shoutout->recognizedBy->name ?? 'Unknown',
                        'category' => $shoutout->category,
                        'message' => $shoutout->message,
                        'likes' => $shoutout->likes,
                        'created_at' => $shoutout->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'shoutouts' => $shoutouts
            ], 'Shoutouts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve shoutouts: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's own rewards (Employee)
     */
    public function getMyRewards(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->user()->id;
            
            $rewards = EmployeeReward::with(['rewardType', 'givenBy'])
                ->byEmployee($employeeId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reward) {
                    return [
                        'id' => $reward->id,
                        'reward_type_id' => $reward->reward_type_id,
                        'name' => $reward->rewardType->name,
                        'type' => $reward->rewardType->type,
                        'value' => $reward->rewardType->value,
                        'description' => $reward->rewardType->description,
                        'icon' => $reward->rewardType->icon,
                        'given_by' => $reward->givenBy->name ?? 'Unknown',
                        'reason' => $reward->reason,
                        'status' => $reward->status,
                        'redeemed_at' => $reward->redeemed_at?->toISOString(),
                        'created_at' => $reward->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'rewards' => $rewards
            ], 'Rewards retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve rewards: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's own badges (Employee)
     */
    public function getMyBadges(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->user()->id;
            
            $badges = EmployeeBadge::with(['badgeType', 'awardedBy'])
                ->byEmployee($employeeId)
                ->orderBy('awarded_at', 'desc')
                ->get()
                ->map(function ($badge) {
                    return [
                        'id' => $badge->id,
                        'badge_type_id' => $badge->badge_type_id,
                        'badge' => [
                            'id' => $badge->badgeType->id,
                            'name' => $badge->badgeType->name,
                            'description' => $badge->badgeType->description,
                            'icon' => $badge->badgeType->icon,
                            'color' => $badge->badgeType->color,
                            'criteria' => $badge->badgeType->criteria,
                        ],
                        'awarded_by' => $badge->awardedBy->name ?? 'Unknown',
                        'reason' => $badge->reason,
                        'awarded_at' => $badge->awarded_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'badges' => $badges
            ], 'Badges retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve badges: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's performance stats (Employee)
     */
    public function getMyPerformance(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->user()->id;
            
            $performance = EmployeePerformance::firstOrCreate(
                ['employee_id' => $employeeId],
                [
                    'total_points' => 0,
                    'total_shoutouts' => 0,
                    'total_rewards' => 0,
                    'total_badges' => 0,
                ]
            );

            return $this->successResponse([
                'performance' => [
                    'total_points' => $performance->total_points,
                    'total_shoutouts' => $performance->total_shoutouts,
                    'total_rewards' => $performance->total_rewards,
                    'total_badges' => $performance->total_badges,
                    'last_updated' => $performance->last_updated?->toISOString(),
                ]
            ], 'Performance stats retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve performance stats: ' . $e->getMessage());
        }
    }

    /**
     * Redeem reward (Employee)
     */
    public function redeemReward(Request $request, $id): JsonResponse
    {
        try {
            $employeeId = $request->user()->id;
            
            $reward = EmployeeReward::where('id', $id)
                ->where('employee_id', $employeeId)
                ->where('status', 'pending')
                ->first();

            if (!$reward) {
                return $this->notFoundResponse('Reward not found or already redeemed');
            }

            $reward->markAsRedeemed();

            return $this->successResponse([
                'reward' => [
                    'id' => $reward->id,
                    'status' => $reward->status,
                    'redeemed_at' => $reward->redeemed_at->toISOString(),
                ]
            ], 'Reward redeemed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to redeem reward: ' . $e->getMessage());
        }
    }

    // =================== HELPER METHODS ===================

    /**
     * Update employee performance
     */
    private function updatePerformance($employeeId, $type, $points = 0)
    {
        $performance = EmployeePerformance::firstOrCreate(
            ['employee_id' => $employeeId],
            [
                'total_points' => 0,
                'total_shoutouts' => 0,
                'total_rewards' => 0,
                'total_badges' => 0,
            ]
        );

        switch ($type) {
            case 'shoutout':
                $performance->incrementShoutout();
                break;
            case 'reward':
                $performance->incrementReward($points);
                break;
            case 'badge':
                $performance->incrementBadge();
                break;
        }
    }

    /**
     * Send shout-out as a chat message to the employee
     */
    private function sendShoutoutChatMessage($employeeId, $employeeName, $category, $message, $adminName)
    {
        // Find or create private conversation between admin and employee
        $conversation = Conversation::where('type', 'private')
            ->whereHas('participants', function ($query) {
                $query->where('participant_id', 'admin');
            })
            ->whereHas('participants', function ($query) use ($employeeId) {
                $query->where('participant_id', $employeeId);
            })
            ->first();

        if (!$conversation) {
            // Create new private conversation
            $conversation = Conversation::create([
                'name' => 'Private Chat',
                'type' => 'private',
                'created_by' => 'admin'
            ]);

            // Add admin participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'participant_id' => 'admin',
                'participant_type' => 'admin',
                'joined_at' => now()
            ]);

            // Add employee participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'participant_id' => (string)$employeeId,
                'participant_type' => 'employee',
                'joined_at' => now()
            ]);
        }

        // Format the shout-out message with proper line breaks and clear labels
        $chatMessage = "🎉 Shout-out to {$employeeName}\n\n";
        $chatMessage .= "Category: {$category}\n\n";
        $chatMessage .= "Message: {$message}\n\n";
        $chatMessage .= "From: {$adminName}";

        // Create the private message
        PrivateMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => 'admin',
            'sender_type' => 'admin',
            'sender_name' => 'Management',
            'content' => $chatMessage,
            'attachments' => null,
            'has_attachments' => false,
            'is_read' => false
        ]);
    }
}
