<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceReportResource;
use App\Http\Resources\PerformanceInteractionResource;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\PerformanceInteraction;
use App\Models\PerformanceReviewSchedule;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PerformanceManagementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all employees with their performance data (Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get employees with approved/active status (not rejected or inactive)
            $query = Employee::with(['performanceReports', 'performanceInteractions'])
                ->where('stage', '!=', 'rejected')
                ->whereIn('status', ['approved', 'active', 'ACTIVE']);

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(personal_info, '$.firstName')) LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(personal_info, '$.lastName')) LIKE ?", ["%{$search}%"]);
                });
            }

            $employees = $query->get();

            // Format response with performance statistics
            $formattedEmployees = $employees->map(function ($employee) {
                $reports = $employee->performanceReports;
                $interactions = $employee->performanceInteractions;
                $latestReport = $reports->sortByDesc('created_at')->first();

                return [
                    'id' => $employee->id,
                    'email' => $employee->email,
                    'firstName' => $employee->first_name,
                    'lastName' => $employee->last_name,
                    'personalInfo' => $employee->personal_info,
                    'location' => $employee->location,
                    'status' => $employee->status,
                    'performanceStats' => [
                        'totalReports' => $reports->count(),
                        'totalInteractions' => $interactions->count(),
                        'latestReport' => $latestReport ? [
                            'id' => $latestReport->id,
                            'type' => $latestReport->type,
                            'overallRating' => (float) $latestReport->overall_rating,
                            'createdAt' => $latestReport->created_at->toISOString(),
                        ] : null,
                        'averageRating' => $reports->count() > 0 
                            ? round($reports->avg('overall_rating'), 1) 
                            : null,
                    ],
                ];
            });

            return $this->successResponse([
                'employees' => $formattedEmployees,
                'total' => $employees->count(),
            ], 'Employees performance data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Get all performance reports (Admin)
     */
    public function getAllReports(Request $request): JsonResponse
    {
        try {
            $query = PerformanceReport::with('employee')->orderBy('created_at', 'desc');

            // Filter by employee
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $reports = $query->get();

            return $this->successResponse(
                PerformanceReportResource::collection($reports),
                'Performance reports retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve reports: ' . $e->getMessage());
        }
    }

    /**
     * Get performance reports for specific employee (Admin)
     */
    public function getEmployeeReports(string $employeeId): JsonResponse
    {
        try {
            $employee = Employee::find($employeeId);
            
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            $reports = PerformanceReport::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse(
                PerformanceReportResource::collection($reports),
                'Employee performance reports retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve reports: ' . $e->getMessage());
        }
    }

    /**
     * Create a new performance report (Admin)
     */
    public function createReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string|in:Performance Review,90-Day Review,Annual Review,Disciplinary Action',
            'review_period' => 'required|string|in:weekly,monthly,quarterly,annual',
            'ratings' => 'required|array',
            'ratings.punctuality' => 'required|numeric|min:1|max:5',
            'ratings.workQuality' => 'required|numeric|min:1|max:5',
            'ratings.teamwork' => 'required|numeric|min:1|max:5',
            'ratings.communication' => 'required|numeric|min:1|max:5',
            'ratings.customerService' => 'required|numeric|min:1|max:5',
            'ratings.initiative' => 'required|numeric|min:1|max:5',
            'strengths' => 'nullable|string',
            'areasForImprovement' => 'nullable|string',
            'goals' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $admin = $request->user();
            $adminName = $admin->name ?? 'Admin';

            DB::beginTransaction();

            $report = PerformanceReport::create([
                'employee_id' => $request->employee_id,
                'type' => $request->type,
                'review_period' => $request->review_period,
                'punctuality' => $request->ratings['punctuality'],
                'work_quality' => $request->ratings['workQuality'],
                'teamwork' => $request->ratings['teamwork'],
                'communication' => $request->ratings['communication'],
                'customer_service' => $request->ratings['customerService'],
                'initiative' => $request->ratings['initiative'],
                'strengths' => $request->strengths,
                'areas_for_improvement' => $request->areasForImprovement,
                'goals' => $request->goals,
                'notes' => $request->notes,
                'created_by' => $admin->id,
                'created_by_name' => $adminName,
            ]);

            $report->load('employee');

            // Check if this report satisfies a pending schedule and mark it as completed
            if ($request->has('schedule_id') && $request->schedule_id) {
                $schedule = PerformanceReviewSchedule::find($request->schedule_id);
                if ($schedule && !$schedule->completed) {
                    $schedule->markCompleted($report->id);
                }
            } else {
                // Try to find a matching pending schedule (within 14 days of scheduled date)
                $schedule = PerformanceReviewSchedule::where('employee_id', $request->employee_id)
                    ->where('completed', false)
                    ->whereBetween('scheduled_date', [
                        now()->subDays(14)->toDateString(),
                        now()->addDays(14)->toDateString()
                    ])
                    ->orderBy('scheduled_date', 'asc')
                    ->first();
                    
                if ($schedule) {
                    $schedule->markCompleted($report->id);
                }
            }

            DB::commit();

            return $this->successResponse(
                new PerformanceReportResource($report),
                'Performance report created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create report: ' . $e->getMessage());
        }
    }

    /**
     * Update a performance report (Admin)
     */
    public function updateReport(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|string|in:Performance Review,90-Day Review,Annual Review,Disciplinary Action',
            'review_period' => 'sometimes|string|in:weekly,monthly,quarterly,annual',
            'ratings' => 'sometimes|array',
            'ratings.punctuality' => 'sometimes|numeric|min:1|max:5',
            'ratings.workQuality' => 'sometimes|numeric|min:1|max:5',
            'ratings.teamwork' => 'sometimes|numeric|min:1|max:5',
            'ratings.communication' => 'sometimes|numeric|min:1|max:5',
            'ratings.customerService' => 'sometimes|numeric|min:1|max:5',
            'ratings.initiative' => 'sometimes|numeric|min:1|max:5',
            'strengths' => 'nullable|string',
            'areasForImprovement' => 'nullable|string',
            'goals' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $report = PerformanceReport::find($id);

            if (!$report) {
                return $this->notFoundResponse('Performance report not found');
            }

            DB::beginTransaction();

            if ($request->has('type')) {
                $report->type = $request->type;
            }

            if ($request->has('review_period')) {
                $report->review_period = $request->review_period;
            }

            if ($request->has('ratings')) {
                if (isset($request->ratings['punctuality'])) {
                    $report->punctuality = $request->ratings['punctuality'];
                }
                if (isset($request->ratings['workQuality'])) {
                    $report->work_quality = $request->ratings['workQuality'];
                }
                if (isset($request->ratings['teamwork'])) {
                    $report->teamwork = $request->ratings['teamwork'];
                }
                if (isset($request->ratings['communication'])) {
                    $report->communication = $request->ratings['communication'];
                }
                if (isset($request->ratings['customerService'])) {
                    $report->customer_service = $request->ratings['customerService'];
                }
                if (isset($request->ratings['initiative'])) {
                    $report->initiative = $request->ratings['initiative'];
                }
            }

            if ($request->has('strengths')) {
                $report->strengths = $request->strengths;
            }

            if ($request->has('areasForImprovement')) {
                $report->areas_for_improvement = $request->areasForImprovement;
            }

            if ($request->has('goals')) {
                $report->goals = $request->goals;
            }

            if ($request->has('notes')) {
                $report->notes = $request->notes;
            }

            $report->save();
            $report->load('employee');

            DB::commit();

            return $this->successResponse(
                new PerformanceReportResource($report),
                'Performance report updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update report: ' . $e->getMessage());
        }
    }

    /**
     * Delete a performance report (Admin)
     */
    public function deleteReport(string $id): JsonResponse
    {
        try {
            $report = PerformanceReport::find($id);

            if (!$report) {
                return $this->notFoundResponse('Performance report not found');
            }

            $report->delete();

            return $this->successResponse(null, 'Performance report deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete report: ' . $e->getMessage());
        }
    }

    /**
     * Get all performance interactions (Admin)
     */
    public function getAllInteractions(Request $request): JsonResponse
    {
        try {
            $query = PerformanceInteraction::with('employee')->orderBy('created_at', 'desc');

            // Filter by employee
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $interactions = $query->get();

            return $this->successResponse(
                PerformanceInteractionResource::collection($interactions),
                'Performance interactions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve interactions: ' . $e->getMessage());
        }
    }

    /**
     * Get performance interactions for specific employee (Admin)
     */
    public function getEmployeeInteractions(string $employeeId): JsonResponse
    {
        try {
            $employee = Employee::find($employeeId);
            
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            $interactions = PerformanceInteraction::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse(
                PerformanceInteractionResource::collection($interactions),
                'Employee performance interactions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve interactions: ' . $e->getMessage());
        }
    }

    /**
     * Create a new performance interaction/feedback (Admin)
     */
    public function createInteraction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string|in:general,recognition,coaching,correction,development',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $admin = $request->user();
            $adminName = $admin->name ?? 'Admin';

            DB::beginTransaction();

            $interaction = PerformanceInteraction::create([
                'employee_id' => $request->employee_id,
                'type' => $request->type,
                'subject' => $request->subject,
                'message' => $request->message,
                'priority' => $request->priority,
                'follow_up_required' => $request->follow_up_required ?? false,
                'follow_up_date' => $request->follow_up_date,
                'created_by' => $admin->id,
                'created_by_name' => $adminName,
            ]);

            $interaction->load('employee');

            DB::commit();

            return $this->successResponse(
                new PerformanceInteractionResource($interaction),
                'Performance interaction created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create interaction: ' . $e->getMessage());
        }
    }

    /**
     * Delete a performance interaction (Admin)
     */
    public function deleteInteraction(string $id): JsonResponse
    {
        try {
            $interaction = PerformanceInteraction::find($id);

            if (!$interaction) {
                return $this->notFoundResponse('Performance interaction not found');
            }

            $interaction->delete();

            return $this->successResponse(null, 'Performance interaction deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete interaction: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's own performance reports (Employee)
     */
    public function getMyReports(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            $reports = PerformanceReport::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse(
                PerformanceReportResource::collection($reports),
                'Your performance reports retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve your reports: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's own performance interactions (Employee)
     */
    public function getMyInteractions(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            $interactions = PerformanceInteraction::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse(
                PerformanceInteractionResource::collection($interactions),
                'Your performance interactions retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve your interactions: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's performance dashboard data (Employee)
     */
    public function getMyPerformanceDashboard(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            $reports = PerformanceReport::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $interactions = PerformanceInteraction::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $latestReport = $reports->first();
            $averageRating = $reports->count() > 0 ? round($reports->avg('overall_rating'), 1) : 0;

            // Calculate trend
            $trend = null;
            if ($reports->count() >= 2) {
                $latest = $reports->first()->overall_rating;
                $previous = $reports->skip(1)->first()->overall_rating;
                $trend = round($latest - $previous, 1);
            }

            return $this->successResponse([
                'reports' => PerformanceReportResource::collection($reports),
                'interactions' => PerformanceInteractionResource::collection($interactions),
                'summary' => [
                    'totalReports' => $reports->count(),
                    'totalInteractions' => $interactions->count(),
                    'currentRating' => $latestReport ? (float) $latestReport->overall_rating : 0,
                    'averageRating' => $averageRating,
                    'trend' => $trend,
                    'latestReportDate' => $latestReport ? $latestReport->created_at->toISOString() : null,
                ],
            ], 'Performance dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve performance dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Get all employees list for chat (Employee)
     * Returns basic employee info for employee-to-employee chat
     */
    public function getEmployeesList(Request $request): JsonResponse
    {
        try {
            // Get all active employees
            $employees = Employee::where('stage', '!=', 'rejected')
                ->whereIn('status', ['approved', 'active', 'ACTIVE'])
                ->select('id', 'email', 'first_name', 'last_name', 'profile_data')
                ->get();

            // Format response with basic employee info
            $formattedEmployees = $employees->map(function ($employee) {
                $profileData = $employee->profile_data ?? [];
                
                return [
                    'id' => $employee->id,
                    'email' => $employee->email,
                    'first_name' => $employee->first_name ?? ($profileData['firstName'] ?? 'Employee'),
                    'last_name' => $employee->last_name ?? ($profileData['lastName'] ?? ''),
                    'designation' => $profileData['designation'] ?? null,
                ];
            });

            return $this->successResponse($formattedEmployees, 'Employees list retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employees list: ' . $e->getMessage());
        }
    }

    /**
     * Get pending performance review schedules (Admin)
     */
    public function getPendingReviews(Request $request): JsonResponse
    {
        try {
            $query = PerformanceReviewSchedule::with(['employee'])
                ->where('completed', false)
                ->orderBy('scheduled_date', 'asc');

            // Filter by employee if specified
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            // Filter by urgency
            if ($request->has('urgency')) {
                if ($request->urgency === 'overdue') {
                    $query->where('scheduled_date', '<', now()->toDateString());
                } elseif ($request->urgency === 'due_soon') {
                    $query->whereBetween('scheduled_date', [
                        now()->toDateString(),
                        now()->addDays(7)->toDateString()
                    ]);
                }
            }

            $schedules = $query->get();

            // Format response with employee details
            $formattedSchedules = $schedules->map(function ($schedule) {
                $employee = $schedule->employee;
                $personalInfo = $employee->personal_info ?? [];
                $firstName = $personalInfo['firstName'] ?? $employee->first_name ?? '';
                $lastName = $personalInfo['lastName'] ?? $employee->last_name ?? '';
                $employeeName = trim($firstName . ' ' . $lastName) ?: $employee->email;

                $daysOverdue = now()->diffInDays($schedule->scheduled_date, false);
                $urgencyStatus = $schedule->urgency_status;

                return [
                    'id' => $schedule->id,
                    'employee_id' => $schedule->employee_id,
                    'employee_name' => $employeeName,
                    'employee_email' => $employee->email,
                    'review_type' => $schedule->review_type,
                    'review_type_label' => $this->getReviewTypeLabel($schedule->review_type),
                    'scheduled_date' => $schedule->scheduled_date->toDateString(),
                    'first_shift_date' => $schedule->first_shift_date->toDateString(),
                    'days_since_first_shift' => now()->diffInDays($schedule->first_shift_date),
                    'days_overdue' => $daysOverdue,
                    'urgency_status' => $urgencyStatus,
                ];
            });

            return $this->successResponse(
                $formattedSchedules,
                'Pending reviews retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve pending reviews: ' . $e->getMessage());
        }
    }

    /**
     * Get count of pending performance reviews (Admin)
     */
    public function getReviewNotificationCount(Request $request): JsonResponse
    {
        try {
            $total = PerformanceReviewSchedule::where('completed', false)->count();
            $overdue = PerformanceReviewSchedule::overdue()->count();
            $dueSoon = PerformanceReviewSchedule::dueSoon(7)->count();

            return $this->successResponse([
                'total' => $total,
                'overdue' => $overdue,
                'due_soon' => $dueSoon,
            ], 'Notification count retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve notification count: ' . $e->getMessage());
        }
    }

    /**
     * Get all review schedules for a specific employee (Admin)
     */
    public function getEmployeeSchedules(string $employeeId): JsonResponse
    {
        try {
            $employee = Employee::find($employeeId);
            
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            $schedules = PerformanceReviewSchedule::where('employee_id', $employeeId)
                ->with('performanceReport')
                ->orderBy('scheduled_date', 'desc')
                ->get();

            $formattedSchedules = $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'review_type' => $schedule->review_type,
                    'review_type_label' => $this->getReviewTypeLabel($schedule->review_type),
                    'scheduled_date' => $schedule->scheduled_date->toDateString(),
                    'first_shift_date' => $schedule->first_shift_date->toDateString(),
                    'completed' => $schedule->completed,
                    'completed_at' => $schedule->completed_at?->toISOString(),
                    'performance_report_id' => $schedule->performance_report_id,
                    'urgency_status' => $schedule->urgency_status,
                ];
            });

            return $this->successResponse(
                $formattedSchedules,
                'Employee review schedules retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employee schedules: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to get readable review type label
     */
    private function getReviewTypeLabel($reviewType): string
    {
        return match($reviewType) {
            'one_week' => '1 Week Review',
            'one_month' => '1 Month Review',
            'quarterly' => 'Quarterly Review',
            default => ucfirst(str_replace('_', ' ', $reviewType)),
        };
    }
}

