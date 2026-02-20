<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Services\ScheduleService;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    use ApiResponseTrait;

    protected $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Get all departments
     */
    public function getDepartments(): JsonResponse
    {
        try {
            $departments = $this->scheduleService->getAllDepartments();

            return $this->successResponse([
                'departments' => $departments
            ], 'Departments retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch departments', 500);
        }
    }

    /**
     * Get roles for a specific department
     */
    public function getRolesForDepartment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'department' => 'required|string|in:BOH,FOH'
            ]);

            $department = $request->input('department');
            $roles = $this->scheduleService->getRolesForDepartment($department);

            return $this->successResponse([
                'department' => $department,
                'roles' => $roles
            ], 'Roles retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching roles: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch roles', 500);
        }
    }

    /**
     * Get employees by department
     */
    public function getEmployeesByDepartment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'department' => 'required|string'
            ]);

            $department = $request->input('department');
            $employees = $this->scheduleService->getEmployeesByDepartment($department);

            return $this->successResponse([
                'department' => $department,
                'employees' => $employees,
                'count' => $employees->count()
            ], 'Employees retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching employees: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch employees', 500);
        }
    }

    /**
     * Get all employees grouped by department
     */
    public function getEmployeesByDepartmentGrouped(): JsonResponse
    {
        try {
            $employees = $this->scheduleService->getEmployeesByDepartmentGrouped();

            return $this->successResponse([
                'employees_by_department' => $employees
            ], 'Employees retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching grouped employees: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch employees', 500);
        }
    }

    /**
     * Get employee details
     */
    public function getEmployeeDetails(int $employeeId): JsonResponse
    {
        try {
            $employee = $this->scheduleService->getEmployeeDetails($employeeId);

            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            return $this->successResponse($employee, 'Employee retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching employee details: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch employee details', 500);
        }
    }

    /**
     * Fill schedule from template
     */
    public function fillFromTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'department' => 'required|string|in:BOH,FOH',
                'template' => 'required|string|in:BOH,FOH',
                'week_start' => 'required|date_format:Y-m-d'
            ]);

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['week_start'], 'UTC')->startOfDay();
            $department = $validated['department'];
            $template = $validated['template'];

            $result = $this->scheduleService->fillFromTemplate(
                $department,
                $template,
                $weekStart
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            return $this->successResponse([
                'shifts_created' => $result['shifts_created'],
                'shifts' => $result['shifts'],
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->addDays(6)->toDateString()
            ], $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fill schedule from template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get shifts for a specific week
     */
    public function getShiftsForWeek(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'week_start' => 'required|date_format:Y-m-d',
                'week_end' => 'required|date_format:Y-m-d',
                'department' => 'nullable|string'
            ]);

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_start'), 'UTC')->startOfDay();
            $weekEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_end'), 'UTC')->endOfDay();
            $department = $request->input('department');

            \Log::info('[getShiftsForWeek] Query parameters', [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'department' => $department
            ]);

            // Admin should see ALL shifts (active and inactive) to manage unassigned shifts
            $query = Schedule::forWeek($weekStart, $weekEnd);

            if ($department) {
                $query->forDepartment($department);
            }

            $collection = $query->with('employee')->get();

            // Count shifts per (employee_id, date) for conflict detection (only count active shifts)
            $shiftsPerEmployeeDate = [];
            foreach ($collection as $s) {
                if ($s->employee_id !== null && $s->status === 'active') {
                    $key = $s->employee_id . '|' . $s->date->toDateString();
                    $shiftsPerEmployeeDate[$key] = ($shiftsPerEmployeeDate[$key] ?? 0) + 1;
                }
            }

            // First pass: calculate and update is_conflict in database
            foreach ($collection as $shift) {
                $createdFrom = $shift->created_from ?? 'manual';
                $key = $shift->employee_id . '|' . $shift->date->toDateString();
                $count = ($shift->employee_id && $shift->status === 'active') ? ($shiftsPerEmployeeDate[$key] ?? 0) : 0;
                
                // Calculate is_conflict: if created from open_shift AND employee has >1 active shift on same date
                $isConflict = ($createdFrom === 'open_shift' && $shift->status === 'active' && $count > 1);
                
                // Update database if conflict status changed
                if ($shift->is_conflict != $isConflict) {
                    Schedule::where('id', $shift->id)->update(['is_conflict' => $isConflict]);
                }
            }

            // Second pass: build response with updated is_conflict values
            $shifts = $collection->map(function ($shift) use ($shiftsPerEmployeeDate) {
                $createdFrom = $shift->created_from ?? 'manual';
                $key = $shift->employee_id . '|' . $shift->date->toDateString();
                $count = ($shift->employee_id && $shift->status === 'active') ? ($shiftsPerEmployeeDate[$key] ?? 0) : 0;
                $isConflict = ($createdFrom === 'open_shift' && $shift->status === 'active' && $count > 1);

                // Determine status: use database status if set, otherwise infer from employee_id
                $status = $shift->status ?? ($shift->employee_id ? 'active' : 'open');
                
                // For frontend compatibility: map database status to frontend status
                if ($status === 'inactive') {
                    $frontendStatus = 'inactive';
                } elseif (!$shift->employee_id) {
                    $frontendStatus = 'open';
                } else {
                    $frontendStatus = 'assigned';
                }

                return [
                    'id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'employee_name' => $shift->employee ? $shift->employee->first_name . ' ' . $shift->employee->last_name : null,
                    'department' => $shift->department,
                    'date' => $shift->date->toDateString(),
                    'day_of_week' => $shift->day_of_week,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'role' => $shift->role,
                    'shift_type' => $shift->shift_type,
                    'requirements' => $shift->requirements,
                    'created_from' => $createdFrom,
                    'status' => $frontendStatus,
                    'is_conflict' => $isConflict,
                ];
            });

            \Log::info('[getShiftsForWeek] Shifts retrieved', [
                'count' => $shifts->count(),
                'open_shifts' => $shifts->filter(fn($s) => !$s['employee_id'])->count(),
                'assigned_shifts' => $shifts->filter(fn($s) => $s['employee_id'])->count()
            ]);

            return $this->successResponse([
                'shifts' => $shifts,
                'count' => $shifts->count(),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ], 'Shifts retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error in getShiftsForWeek: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch shifts', 500);
        }
    }

    /**
     * Create a new shift
     */
    public function createShift(Request $request): JsonResponse
    {
        try {
            \Log::info('[createShift] Request received', [
                'all_data' => $request->all()
            ]);
            
            $validated = $request->validate([
                'employee_id' => 'nullable|exists:employees,id',
                'department' => 'required|string|in:BOH,FOH',
                'date' => 'required|date_format:Y-m-d',
                'start_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
                'end_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
                'role' => 'required|string',
                'shift_type' => 'nullable|string',
                'requirements' => 'nullable|array',
                'created_from' => 'nullable|string|in:open_shift,template,manual'
            ]);

            \Log::info('[createShift] Validation passed', [
                'validated' => $validated,
                'is_open_shift' => is_null($validated['employee_id']),
                'created_from' => $validated['created_from'] ?? 'NOT PROVIDED'
            ]);

            $shift = $this->scheduleService->createShift($validated);

            $createdFrom = $shift->created_from ?? 'manual';
            $isConflict = false;
            
            \Log::info('[createShift] After service creation', [
                'shift_id' => $shift->id,
                'employee_id' => $shift->employee_id,
                'date' => $shift->date->toDateString(),
                'created_from_from_db' => $shift->created_from,
                'created_from_expected' => $validated['created_from'] ?? 'NOT PROVIDED',
            ]);
            
            if ($shift->employee_id && $createdFrom === 'open_shift') {
                $count = Schedule::where('employee_id', $shift->employee_id)
                    ->whereDate('date', $shift->date)
                    ->where('status', 'active')
                    ->count();
                $isConflict = $count > 1;
                
                // Save is_conflict to database
                $shift->update(['is_conflict' => $isConflict]);
                
                \Log::info('[createShift] Conflict calculated and saved', [
                    'shift_id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'date' => $shift->date->toDateString(),
                    'created_from' => $createdFrom,
                    'shift_count' => $count,
                    'is_conflict' => $isConflict,
                ]);
            } else {
                \Log::info('[createShift] No conflict check needed', [
                    'shift_id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'created_from' => $createdFrom,
                    'reason' => !$shift->employee_id ? 'no employee_id' : 'not open_shift',
                ]);
            }

            \Log::info('[createShift] Shift created successfully', [
                'shift_id' => $shift->id,
                'employee_id' => $shift->employee_id,
                'is_open_shift' => is_null($shift->employee_id),
                'date' => $shift->date->toDateString(),
                'is_conflict' => $isConflict
            ]);

            return $this->successResponse([
                'shift' => [
                    'id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'employee_name' => $shift->employee ? $shift->employee->first_name . ' ' . $shift->employee->last_name : null,
                    'department' => $shift->department,
                    'date' => $shift->date->toDateString(),
                    'day_of_week' => $shift->day_of_week,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'role' => $shift->role,
                    'shift_type' => $shift->shift_type,
                    'requirements' => $shift->requirements,
                    'created_from' => $createdFrom,
                    'status' => $shift->employee_id ? 'assigned' : 'open',
                    'is_conflict' => $isConflict,
                ]
            ], 'Shift created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('[createShift] Validation error', [
                'errors' => $e->errors()
            ]);
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            \Log::error('[createShift] Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to create shift: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing shift
     */
    public function updateShift(Request $request, int $shiftId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'nullable|exists:employees,id',
                'department' => 'nullable|string|in:BOH,FOH',
                'date' => 'nullable|date_format:Y-m-d',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'role' => 'nullable|string',
                'shift_type' => 'nullable|string',
                'requirements' => 'nullable|array',
                'status' => 'nullable|string|in:active,inactive'
            ]);

            $shift = $this->scheduleService->updateShift($shiftId, $validated);

            if (!$shift) {
                return $this->notFoundResponse('Shift not found');
            }

            return $this->successResponse([
                'shift' => [
                    'id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'employee_name' => $shift->employee ? $shift->employee->first_name . ' ' . $shift->employee->last_name : null,
                    'department' => $shift->department,
                    'date' => $shift->date->toDateString(),
                    'day_of_week' => $shift->day_of_week,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'role' => $shift->role,
                    'shift_type' => $shift->shift_type,
                    'requirements' => $shift->requirements,
                    'created_from' => $shift->created_from,
                    'status' => $shift->status,
                    'is_conflict' => $shift->is_conflict
                ]
            ], 'Shift updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('Error updating shift: ' . $e->getMessage());
            return $this->errorResponse('Failed to update shift', 500);
        }
    }

    /**
     * Delete a shift
     */
    public function deleteShift(int $shiftId): JsonResponse
    {
        try {
            $result = $this->scheduleService->deleteShift($shiftId);

            if (!$result) {
                return $this->notFoundResponse('Shift not found');
            }

            return $this->successResponse(null, 'Shift deleted successfully');
        } catch (\Exception $e) {
            Log::error('Error deleting shift: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete shift', 500);
        }
    }

    /**
     * Publish schedule for a specific week
     */
    public function publishSchedule(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'week_start' => 'required|date_format:Y-m-d',
                'week_end' => 'required|date_format:Y-m-d',
                'department' => 'nullable|string'
            ]);

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['week_start'], 'UTC')->startOfDay();
            $weekEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['week_end'], 'UTC')->endOfDay();
            $department = $validated['department'] ?? null;

            $result = $this->scheduleService->publishSchedule($weekStart, $weekEnd, $department);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            return $this->successResponse([
                'shifts_published' => $result['shifts_published'],
                'employees_notified' => $result['employees_notified'],
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ], $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('Error publishing schedule: ' . $e->getMessage());
            return $this->errorResponse('Failed to publish schedule', 500);
        }
    }

    /**
     * Get published shifts for an employee
     */
    public function getEmployeeShifts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'week_start' => 'required|date_format:Y-m-d',
                'week_end' => 'required|date_format:Y-m-d'
            ]);

            // Get employee ID from authenticated user
            $employeeId = auth()->user()->id;

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_start'), 'UTC')->startOfDay();
            $weekEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_end'), 'UTC')->endOfDay();

            $shifts = Schedule::forWeek($weekStart, $weekEnd)
                ->where('employee_id', $employeeId)
                ->active()
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
                ->map(function ($shift) {
                    return [
                        'id' => $shift->id,
                        'employee_id' => $shift->employee_id,
                        'department' => $shift->department,
                        'date' => $shift->date->toDateString(),
                        'day_of_week' => $shift->day_of_week,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'role' => $shift->role,
                        'shift_type' => $shift->shift_type,
                        'requirements' => $shift->requirements,
                        'published' => $shift->published,
                        'published_at' => $shift->published_at,
                        'created_from' => $shift->created_from
                    ];
                });

            return $this->successResponse([
                'shifts' => $shifts,
                'count' => $shifts->count(),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ], 'Employee shifts retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching employee shifts: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch employee shifts', 500);
        }
    }

    /**
     * Clear all shifts for a specific week and department
     */
    public function clearSchedule(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'week_start' => 'required|date_format:Y-m-d',
                'week_end' => 'required|date_format:Y-m-d',
                'department' => 'nullable|string'
            ]);

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_start'), 'UTC')->startOfDay();
            $weekEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_end'), 'UTC')->endOfDay();
            $department = $request->input('department');

            $query = Schedule::forWeek($weekStart, $weekEnd)->active();

            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }

            $deletedCount = $query->delete();

            return $this->successResponse([
                'deleted_count' => $deletedCount,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ], "Successfully deleted {$deletedCount} shifts");
        } catch (\Exception $e) {
            Log::error('Error clearing schedule: ' . $e->getMessage());
            return $this->errorResponse('Failed to clear schedule', 500);
        }
    }

    /**
     * Import schedule from CSV
     */
    public function importSchedule(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'shifts' => 'required|array',
                'week_start' => 'required|date_format:Y-m-d'
            ]);

            $shifts = $request->input('shifts');
            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_start'), 'UTC')->startOfDay();
            $weekEnd = $weekStart->copy()->addDays(6);

            error_log("=== IMPORT START ===");
            error_log("Total shifts received: " . count($shifts));
            error_log("Shifts data: " . json_encode($shifts));

            $shiftsCreated = 0;
            $assignedCount = 0;
            $openCount = 0;
            $unassignedCount = 0;

            foreach ($shifts as $shift) {
                error_log("Processing shift: " . json_encode($shift));

                // Calculate day_of_week and week dates from date
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', $shift['date']);
                $dayOfWeek = $date->format('l'); // Monday, Tuesday, etc.
                
                // Calculate week start/end for this date
                $shiftWeekStart = $date->copy()->startOfWeek();
                $shiftWeekEnd = $shiftWeekStart->copy()->addDays(6);

                if ($shift['section'] === 'assigned') {
                    $assignedCount++;
                    error_log("ASSIGNED shift - looking for employee: " . $shift['employee_name']);
                    
                    // Find employee by name
                    $employee = \App\Models\Employee::where('status', 'approved')
                        ->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$shift['employee_name']])
                        ->first();

                    error_log("Employee found: " . ($employee ? "YES (ID: " . $employee->id . ")" : "NO"));

                    if ($employee) {
                        Schedule::create([
                            'employee_id' => $employee->id,
                            'date' => $shift['date'],
                            'day_of_week' => $dayOfWeek,
                            'start_time' => $shift['start_time'],
                            'end_time' => $shift['end_time'],
                            'role' => $shift['role'],
                            'department' => $shift['department'],
                            'week_start' => $shiftWeekStart->format('Y-m-d'),
                            'week_end' => $shiftWeekEnd->format('Y-m-d'),
                            'status' => 'assigned',
                            'created_from' => 'import'
                        ]);
                        $shiftsCreated++;
                        error_log("Assigned shift created!");
                    } else {
                        error_log("Employee NOT FOUND - shift skipped");
                    }
                } elseif ($shift['section'] === 'unassigned') {
                    $unassignedCount++;
                    error_log("UNASSIGNED shift");
                    
                    Schedule::create([
                        'employee_id' => null,
                        'date' => $shift['date'],
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $shift['start_time'],
                        'end_time' => $shift['end_time'],
                        'role' => $shift['role'],
                        'department' => $shift['department'],
                        'week_start' => $shiftWeekStart->format('Y-m-d'),
                        'week_end' => $shiftWeekEnd->format('Y-m-d'),
                        'status' => 'inactive',
                        'created_from' => 'import'
                    ]);
                    $shiftsCreated++;
                    error_log("Unassigned shift created!");
                } elseif ($shift['section'] === 'open') {
                    $openCount++;
                    error_log("OPEN shift");
                    
                    Schedule::create([
                        'employee_id' => null,
                        'date' => $shift['date'],
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $shift['start_time'],
                        'end_time' => $shift['end_time'],
                        'role' => $shift['role'],
                        'department' => $shift['department'],
                        'week_start' => $shiftWeekStart->format('Y-m-d'),
                        'week_end' => $shiftWeekEnd->format('Y-m-d'),
                        'status' => 'open',
                        'created_from' => 'import'
                    ]);
                    $shiftsCreated++;
                    error_log("Open shift created!");
                }
            }

            error_log("=== IMPORT END ===");
            error_log("Summary - Assigned: {$assignedCount}, Unassigned: {$unassignedCount}, Open: {$openCount}, Created: {$shiftsCreated}");

            return $this->successResponse([
                'shifts_created' => $shiftsCreated
            ], "Successfully imported {$shiftsCreated} shifts");
        } catch (\Exception $e) {
            error_log("Import error: " . $e->getMessage());
            Log::error('Error importing schedule: ' . $e->getMessage());
            return $this->errorResponse('Failed to import schedule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Normalize time format - remove seconds if present
     */
    /**
     * Validate time format (HH:MM)
     */
    private function isValidTime($time): bool
    {
        return preg_match('/^\d{1,2}:\d{2}$/', $time) === 1;
    }

    /**
     * Save current schedule as a template
     */
    public function saveAsTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'week_start' => 'required|date',
                'week_end' => 'required|date',
                'name' => 'required|string|max:255',
                'department' => 'required|string',
                'location' => 'nullable|string',
                'description' => 'nullable|string'
            ]);

            $weekStart = Carbon::parse($validated['week_start']);
            $weekEnd = Carbon::parse($validated['week_end']);

            $result = $this->scheduleService->saveAsTemplate(
                $weekStart,
                $weekEnd,
                $validated['name'],
                $validated['department'],
                $validated['location'] ?? null,
                $validated['description'] ?? null,
                auth()->id()
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all templates
     */
    public function getTemplates(Request $request): JsonResponse
    {
        try {
            $department = $request->query('department');
            $location = $request->query('location');

            $result = $this->scheduleService->getTemplates($department, $location);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'templates' => [],
                'message' => 'Error fetching templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(int $templateId): JsonResponse
    {
        try {
            $result = $this->scheduleService->deleteTemplate($templateId);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fill schedule from a saved template
     */
    public function fillFromSavedTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'template_id' => 'required|integer|exists:schedule_templates,id',
                'week_start' => 'required|date'
            ]);

            $weekStart = Carbon::parse($validated['week_start']);

            $result = $this->scheduleService->fillScheduleFromTemplate(
                $validated['template_id'],
                $weekStart
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error filling schedule from template: ' . $e->getMessage(),
                'shifts' => []
            ], 500);
        }
    }
}
