<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Services\ScheduleService;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                'week_start' => 'required|date_format:Y-m-d',
                'include_labor_percentage' => 'boolean'
            ]);

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['week_start']);
            $department = $validated['department'];
            $template = $validated['template'];
            $includeLaborPercentage = $validated['include_labor_percentage'] ?? true;

            Log::info("fillFromTemplate called with: department={$department}, template={$template}, weekStart={$weekStart}");

            $result = $this->scheduleService->fillFromTemplate(
                $department,
                $template,
                $weekStart,
                $includeLaborPercentage
            );

            if (!$result['success']) {
                Log::error('fillFromTemplate failed: ' . $result['message']);
                return $this->errorResponse($result['message'], 400);
            }

            return $this->successResponse([
                'shifts_created' => $result['shifts_created'],
                'shifts' => $result['shifts'],
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->addDays(6)->toDateString()
            ], $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in fillFromTemplate: ' . json_encode($e->errors()));
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('Error in fillFromTemplate: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
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

            $weekStart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_start'));
            $weekEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('week_end'));
            $department = $request->input('department');

            $query = Schedule::forWeek($weekStart, $weekEnd)->active();

            if ($department) {
                $query->forDepartment($department);
            }

            $shifts = $query->with('employee')->get()->map(function ($shift) {
                return [
                    'id' => $shift->id,
                    'employee_id' => $shift->employee_id,
                    'employee_name' => $shift->employee->first_name . ' ' . $shift->employee->last_name,
                    'department' => $shift->department,
                    'date' => $shift->date->toDateString(),
                    'day_of_week' => $shift->day_of_week,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'role' => $shift->role,
                    'shift_type' => $shift->shift_type,
                    'requirements' => $shift->requirements
                ];
            });

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
}
