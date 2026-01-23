<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Services\ScheduleService;
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
}
