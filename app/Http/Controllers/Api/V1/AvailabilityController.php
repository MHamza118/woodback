<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityReason;
use App\Models\AvailabilityRequest;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    public function getReasons()
    {
        try {
            $reasons = AvailabilityReason::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'reasons' => $reasons
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reasons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeReason(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:255',
                'comment_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reason = AvailabilityReason::create([
                'reason' => $request->reason,
                'comment_required' => $request->comment_required ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reason created successfully',
                'reason' => $reason
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reason',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateReason(Request $request, $id)
    {
        try {
            $reason = AvailabilityReason::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:255',
                'comment_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reason->update([
                'reason' => $request->reason,
                'comment_required' => $request->comment_required ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reason updated successfully',
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reason',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteReason($id)
    {
        try {
            $reason = AvailabilityReason::findOrFail($id);
            $reason->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reason deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete reason',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRequests(Request $request)
    {
        try {
            $query = AvailabilityRequest::with(['employee', 'approver']);

            // Check if this is an employee request (from employee routes)
            // The prefix could be 'api/v1/employee' or 'api/v1/employee/availability' depending on nesting
            $prefix = $request->route()->getPrefix();
            $isEmployeeRoute = str_contains($prefix, 'employee');

            if ($isEmployeeRoute) {
                // For employee routes, only show their own requests
                $query->where('employee_id', $request->user()->id);
            } else {
                // For admin routes, allow filtering
                if ($request->has('employee_id')) {
                    $query->where('employee_id', $request->employee_id);
                }
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'requests' => $requests
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get new pending availability requests (for real-time notifications)
     * Returns only pending requests created in the last X minutes
     */
    public function getNewRequests(Request $request)
    {
        try {
            $minutesBack = $request->query('minutes', 5); // Default to last 5 minutes
            $since = now()->subMinutes($minutesBack);

            $newRequests = AvailabilityRequest::with(['employee', 'approver'])
                ->where('status', 'pending')
                ->where('created_at', '>=', $since)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'requests' => $newRequests,
                'count' => $newRequests->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch new requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get updated availability requests for an employee (for real-time notifications)
     * Returns requests that were updated in the last X minutes
     */
    public function getUpdatedRequests(Request $request)
    {
        try {
            $employeeId = $request->user()->id;
            $minutesBack = $request->query('minutes', 5); // Default to last 5 minutes
            $since = now()->subMinutes($minutesBack);

            $updatedRequests = AvailabilityRequest::with(['employee', 'approver'])
                ->where('employee_id', $employeeId)
                ->where('status', '!=', 'pending')
                ->where('updated_at', '>=', $since)
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'requests' => $updatedRequests,
                'count' => $updatedRequests->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch updated requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeRequest(Request $request)
    {
        try {
            // Check if this is an employee request (from employee routes)
            $prefix = $request->route()->getPrefix();
            $isEmployeeRequest = str_contains($prefix, 'employee');
            
            $validationRules = [
                'type' => 'required|in:recurring,temporary',
                'availability_data' => 'required|array',
                'effective_from' => 'nullable|date',
                'effective_to' => 'nullable|date|after_or_equal:effective_from'
            ];

            // For admin requests, require employee_id. For employee requests, use authenticated user's ID
            if (!$isEmployeeRequest) {
                $validationRules['employee_id'] = 'required|exists:employees,id';
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Determine employee_id
            $employeeId = $isEmployeeRequest ? $request->user()->id : $request->employee_id;

            // Check if employee already has a pending request
            $existingPendingRequest = AvailabilityRequest::where('employee_id', $employeeId)
                ->where('status', 'pending')
                ->first();

            if ($isEmployeeRequest && $existingPendingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending availability request. Please wait for it to be approved or declined before submitting a new one.'
                ], 422);
            }

            // Admin Override Logic: If Admin, decline existing pending request and replace approved recurring request
            if (!$isEmployeeRequest) {
                // Decline any pending request
                if ($existingPendingRequest) {
                    $existingPendingRequest->update([
                        'status' => 'declined',
                        'admin_notes' => 'Overridden by new admin assignment',
                        'approved_by' => $request->user()->id,
                        'approved_at' => now()
                    ]);
                }

                // For recurring type, replace the existing approved recurring request
                if ($request->type === 'recurring') {
                    $existingApprovedRecurring = AvailabilityRequest::where('employee_id', $employeeId)
                        ->where('type', 'recurring')
                        ->where('status', 'approved')
                        ->first();

                    if ($existingApprovedRecurring) {
                        $existingApprovedRecurring->delete();
                    }
                }
            }

            $status = $isEmployeeRequest ? 'pending' : 'approved';
            $approvedBy = $isEmployeeRequest ? null : $request->user()->id;
            $approvedAt = $isEmployeeRequest ? null : now();

            $availabilityRequest = AvailabilityRequest::create([
                'employee_id' => $employeeId,
                'type' => $request->type,
                'status' => $status,
                'availability_data' => $request->availability_data,
                'effective_from' => $request->effective_from,
                'effective_to' => $request->effective_to,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt
            ]);

            $availabilityRequest->load(['employee', 'approver']);

            // Send Notification if Employee submitted the request
            if ($isEmployeeRequest) {
                // 1. Send Bell Notification (Database) to Admins
                try {
                    $employee = $request->user();
                    $employeeName = $employee->first_name . ' ' . $employee->last_name;
                    $message = "{$employeeName} submitted a new availability request effective from {$availabilityRequest->effective_from}.";
                    
                    \Log::info('Creating availability notification for employee: ' . $employeeName);

                    \App\Models\TableNotification::create([
                        'type' => \App\Models\TableNotification::TYPE_NEW_AVAILABILITY_REQUEST, 
                        'title' => "New Availability Request",
                        'message' => $message,
                        'recipient_type' => \App\Models\TableNotification::RECIPIENT_ADMIN,
                        'priority' => \App\Models\TableNotification::PRIORITY_MEDIUM,
                        'order_number' => null, // Required if migration didn't run properly, safest to include
                        'data' => [
                            'request_id' => $availabilityRequest->id,
                            'employee_id' => $employee->id,
                            'employee_name' => $employeeName,
                            'effective_from' => $availabilityRequest->effective_from
                        ]
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create admin availability notification: ' . $e->getMessage());
                }

                // 2. Send Push Notification (OneSignal) to Admins
                try {
                    $request->user()->sendNewAvailabilityRequestNotification($request->user(), $availabilityRequest);
                } catch (\Exception $notifyError) {
                    \Log::error('Failed to notify admins of new availability request: ' . $notifyError->getMessage());
                }

                // 3. Broadcast Toast Notification Event to Admins (Real-time)
                try {
                    $employee = $request->user();
                    $employeeName = $employee->first_name . ' ' . $employee->last_name;
                    
                    // Broadcast to all admin users
                    \Illuminate\Support\Facades\Broadcast::channel('admin-availability-requests', function () {
                        return true;
                    });
                    
                    // Emit event for real-time toast notification
                    event(new \App\Events\NewAvailabilityRequest(
                        $availabilityRequest,
                        $employeeName,
                        $employee->id
                    ));
                } catch (\Exception $e) {
                    \Log::error('Failed to broadcast availability request event: ' . $e->getMessage());
                }
            }

            // Send Notification if Admin set the availability (Existing Code)
            if (!$isEmployeeRequest) {
                try {
                    $oneSignal = new \App\Services\OneSignalService();
                    $title = 'Availability Updated';
                    $message = 'An admin has updated your availability settings.';
                    
                    // Construct a payload for the employee
                    $oneSignal->sendToEmployee(
                        $employeeId, 
                        $title, 
                        $message, 
                        ['type' => 'availability_update', 'request_id' => $availabilityRequest->id]
                    );
                } catch (\Exception $notifyError) {
                    \Log::error('Failed to notify employee of availability update: ' . $notifyError->getMessage());
                    // Do not fail the request if notification fails
                }
            }

            return response()->json([
                'success' => true,
                'message' => $isEmployeeRequest 
                    ? 'Availability request submitted successfully' 
                    : 'Availability set successfully and employee notified',
                'request' => $availabilityRequest
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit availability request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateRequestStatus(Request $request, $id)
    {
        try {
            $availabilityRequest = AvailabilityRequest::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,declined',
                'admin_notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $availabilityRequest->update([
                'status' => $request->status,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'admin_notes' => $request->admin_notes
            ]);

            $availabilityRequest->load(['employee', 'approver']);

            // 1. Send Bell Notification (Database)
            $statusCap = ucfirst($availabilityRequest->status);
            $message = "Your availability request from " . $availabilityRequest->effective_from . " has been {$availabilityRequest->status}.";
            
            if ($availabilityRequest->status === 'declined' && $availabilityRequest->admin_notes) {
                $message .= " Reason: " . $availabilityRequest->admin_notes;
            }

            try {
                \App\Models\TableNotification::create([
                    'type' => \App\Models\TableNotification::TYPE_AVAILABILITY_STATUS_UPDATE,
                    'title' => "Availability Request {$statusCap}",
                    'message' => $message,
                    'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
                    'recipient_id' => $availabilityRequest->employee_id,
                    'priority' => \App\Models\TableNotification::PRIORITY_MEDIUM,
                    'order_number' => null,
                    'data' => [
                        'request_id' => $availabilityRequest->id,
                        'status' => $availabilityRequest->status,
                        'admin_notes' => $availabilityRequest->admin_notes,
                        'approved_by' => $request->user()->name
                    ]
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create availability notification: ' . $e->getMessage());
            }

            // 2. Send Push Notification (OneSignal)
            try {
                $oneSignal = new \App\Services\OneSignalService();
                $oneSignal->sendToEmployee(
                    $availabilityRequest->employee_id,
                    "Availability Request {$statusCap}",
                    $message,
                    ['type' => 'availability_update', 'request_id' => $availabilityRequest->id]
                );
            } catch (\Exception $notifyError) {
                \Log::error('Failed to notify employee of status update: ' . $notifyError->getMessage());
            }

            // 3. Broadcast Toast Notification Event to Employee (Real-time)
            try {
                event(new \App\Events\AvailabilityRequestStatusUpdated(
                    $availabilityRequest,
                    $availabilityRequest->employee_id,
                    $availabilityRequest->status,
                    $availabilityRequest->admin_notes
                ));
            } catch (\Exception $e) {
                \Log::error('Failed to broadcast availability status update event: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Availability request ' . $request->status . ' successfully',
                'request' => $availabilityRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteRequest($id)
    {
        try {
            $availabilityRequest = AvailabilityRequest::findOrFail($id);
            $availabilityRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Availability request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get effective availability for an employee on a specific date
     * Temporary availability overrides recurring availability
     */
    public function getEffectiveAvailability(Request $request, $employeeId = null)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If employeeId not in route, use authenticated user's ID
            if ($employeeId === null) {
                $employeeId = $request->user()->id;
            }

            $availabilityService = new AvailabilityService();
            $date = $request->date ?? now()->toDateString();
            
            $effectiveness = $availabilityService->getEffectiveAvailability($employeeId, $date);

            return response()->json([
                'success' => true,
                'message' => 'Effective availability retrieved successfully',
                'data' => [
                    'date' => $date,
                    'availability' => $effectiveness
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve effective availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get effective availability for a date range
     */
    public function getEffectiveAvailabilityRange(Request $request, $employeeId = null)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If employeeId not in route, use authenticated user's ID
            if ($employeeId === null) {
                $employeeId = $request->user()->id;
            }

            $availabilityService = new AvailabilityService();
            $availabilityRange = $availabilityService->getEffectiveAvailabilityRange(
                $employeeId,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'message' => 'Availability range retrieved successfully',
                'data' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'availability_by_date' => $availabilityRange
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve availability range',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get availability summary for an employee
     * Shows recurring, active temporary, and upcoming temporary availability
     */
    public function getAvailabilitySummary(Request $request, $employeeId = null)
    {
        try {
            // If employeeId not in route, use authenticated user's ID
            if ($employeeId === null) {
                $employeeId = $request->user()->id;
            }

            $availabilityService = new AvailabilityService();
            $summary = $availabilityService->getEmployeeAvailabilitySummary($employeeId);

            return response()->json([
                'success' => true,
                'message' => 'Availability summary retrieved successfully',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve availability summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if employee is available on a specific date
     */
    public function checkAvailability(Request $request, $employeeId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $availabilityService = new AvailabilityService();
            $isAvailable = $availabilityService->isAvailableOnDate($employeeId, $request->date);

            return response()->json([
                'success' => true,
                'message' => 'Availability check completed',
                'data' => [
                    'date' => $request->date,
                    'is_available' => $isAvailable
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
