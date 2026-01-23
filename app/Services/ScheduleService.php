<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class ScheduleService
{
    /**
     * Get all unique departments from role assignments
     */
    public function getAllDepartments(): array
    {
        try {
            $employees = Employee::where('status', Employee::STATUS_APPROVED)
                ->whereNotNull('assignments')
                ->get();

            Log::info('Total approved employees with assignments: ' . $employees->count());

            $departments = [];
            foreach ($employees as $employee) {
                $assignments = $employee->assignments;
                Log::info('Employee ' . $employee->id . ' assignments: ' . json_encode($assignments));
                
                if (is_array($assignments) && isset($assignments['departments']) && is_array($assignments['departments'])) {
                    foreach ($assignments['departments'] as $dept) {
                        if (!in_array($dept, $departments)) {
                            $departments[] = $dept;
                        }
                    }
                }
            }

            Log::info('Departments found: ' . json_encode($departments));
            sort($departments);

            return array_map(function ($dept) {
                return ['name' => $dept];
            }, $departments);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get employees by department (from role assignments)
     */
    public function getEmployeesByDepartment(string $departmentName): array
    {
        try {
            $employees = Employee::where('status', Employee::STATUS_APPROVED)
                ->whereNotNull('assignments')
                ->get();

            Log::info('Fetching employees for department: ' . $departmentName);
            Log::info('Total employees with assignments: ' . $employees->count());

            $filtered = [];
            foreach ($employees as $employee) {
                $assignments = $employee->assignments;
                Log::info('Checking employee ' . $employee->id . ' with assignments: ' . json_encode($assignments));
                
                if (is_array($assignments) && isset($assignments['departments']) && is_array($assignments['departments'])) {
                    Log::info('Employee ' . $employee->id . ' has departments: ' . json_encode($assignments['departments']));
                    if (in_array($departmentName, $assignments['departments'])) {
                        Log::info('Employee ' . $employee->id . ' matches department ' . $departmentName);
                        $filtered[] = [
                            'id' => $employee->id,
                            'first_name' => $employee->first_name,
                            'last_name' => $employee->last_name,
                            'email' => $employee->email,
                            'employment_type' => $employee->employment_type,
                            'assignments' => $assignments
                        ];
                    }
                }
            }

            Log::info('Filtered employees for ' . $departmentName . ': ' . count($filtered));
            return $filtered;
        } catch (\Exception $e) {
            Log::error('Error fetching employees by department: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get all approved employees grouped by department (from role assignments)
     */
    public function getEmployeesByDepartmentGrouped(): array
    {
        try {
            $departments = $this->getAllDepartments();
            $result = [];

            foreach ($departments as $department) {
                $deptName = $department['name'];
                $employees = $this->getEmployeesByDepartment($deptName);
                $result[$deptName] = $employees;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error fetching grouped employees: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get employee details
     */
    public function getEmployeeDetails(int $employeeId): ?Employee
    {
        try {
            return Employee::select('id', 'first_name', 'last_name', 'email', 'employment_type', 'assignments')
                ->find($employeeId);
        } catch (\Exception $e) {
            Log::error('Error fetching employee details: ' . $e->getMessage());
            throw $e;
        }
    }
}
