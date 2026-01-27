<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\AvailabilityRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

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

    /**
     * Fill schedule from template
     * Fetches employees in department, checks their availability, and creates shifts
     * Uses caching - replaces old shifts with new ones for the same week
     */
    public function fillFromTemplate(string $department, string $template, Carbon $weekStart, bool $includeLaborPercentage = true): array
    {
        try {
            Log::info("Starting fillFromTemplate for department: {$department}, template: {$template}");

            // Calculate week end (Sunday)
            $weekEnd = $weekStart->copy()->addDays(6);

            // Delete existing shifts for this week and department to replace with new ones
            Log::info("Deleting existing shifts for {$department} in week {$weekStart} to {$weekEnd}");
            $deletedCount = Schedule::forDepartment($department)
                ->forWeek($weekStart, $weekEnd)
                ->delete();
            Log::info("Deleted {$deletedCount} existing shifts");

            // Get all employees in the department
            $employees = $this->getEmployeesByDepartment($department);
            Log::info("Found " . count($employees) . " employees in {$department}");

            if (empty($employees)) {
                return [
                    'success' => false,
                    'message' => 'No employees found in this department',
                    'shifts' => []
                ];
            }

            // Template shift definitions - roles will be taken from employee assignments
            $templateShifts = $this->getTemplateShifts($department, $template);
            Log::info("Template shifts: " . json_encode($templateShifts));

            $createdShifts = [];
            $availabilityService = new AvailabilityService();

            // For each employee, create shifts based on their availability
            foreach ($employees as $employee) {
                $employeeId = $employee['id'];
                Log::info("Processing employee: {$employeeId}");

                // Get the employee's actual role from their assignments
                $employeeRole = 'Staff'; // Default role
                if (isset($employee['assignments']['roles']) && is_array($employee['assignments']['roles']) && !empty($employee['assignments']['roles'])) {
                    $employeeRole = $employee['assignments']['roles'][0]; // Use first role
                    Log::info("Employee {$employeeId} role from assignments: {$employeeRole}");
                }

                // Get availability for the entire week
                $weekAvailability = $availabilityService->getEffectiveAvailabilityRange(
                    $employeeId,
                    $weekStart,
                    $weekEnd
                );

                Log::info("Week availability for employee {$employeeId}: " . json_encode($weekAvailability));

                // For each day in the week
                $currentDate = $weekStart->copy();
                while ($currentDate <= $weekEnd) {
                    $dayOfWeek = strtolower($currentDate->format('l')); // monday, tuesday, etc.
                    $dateString = $currentDate->toDateString();

                    // Check if employee is available on this day
                    $dayAvailability = $weekAvailability[$dateString] ?? null;

                    Log::info("Day {$dateString} ({$dayOfWeek}) availability for employee {$employeeId}: " . json_encode($dayAvailability));

                    // Only create shifts if availability is explicitly set and employee is available
                    if ($dayAvailability && isset($dayAvailability['availability_data'][$dayOfWeek])) {
                        $dayData = $dayAvailability['availability_data'][$dayOfWeek];

                        // If employee is available on this day
                        if (isset($dayData['enabled']) && $dayData['enabled'] && isset($dayData['status']) && $dayData['status'] === 'available') {
                            // Get available time slots
                            $availableSlots = $this->getAvailableTimeSlots($dayData);

                            // Match with template shifts
                            foreach ($templateShifts as $templateShift) {
                                // Check if template shift fits within available slots
                                if ($this->shiftFitsInAvailability($templateShift, $availableSlots)) {
                                    // Check if shift already exists for this employee on this day with same time
                                    $existingShift = Schedule::where('employee_id', $employeeId)
                                        ->where('date', $currentDate)
                                        ->where('start_time', $templateShift['start_time'])
                                        ->where('end_time', $templateShift['end_time'])
                                        ->first();
                                    
                                    if ($existingShift) {
                                        Log::info("Shift already exists for employee {$employeeId} on {$dateString} at {$templateShift['start_time']}-{$templateShift['end_time']}, skipping");
                                        continue;
                                    }

                                    $shift = Schedule::create([
                                        'employee_id' => $employeeId,
                                        'department' => $department,
                                        'day_of_week' => ucfirst($dayOfWeek),
                                        'date' => $currentDate,
                                        'start_time' => $templateShift['start_time'],
                                        'end_time' => $templateShift['end_time'],
                                        'role' => $employeeRole,
                                        'shift_type' => $templateShift['shift_type'] ?? null,
                                        'requirements' => $templateShift['requirements'] ?? null,
                                        'week_start' => $weekStart,
                                        'week_end' => $weekEnd,
                                        'status' => 'active'
                                    ]);

                                    $createdShifts[] = [
                                        'id' => $shift->id,
                                        'employee_id' => $employeeId,
                                        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
                                        'date' => $dateString,
                                        'day_of_week' => ucfirst($dayOfWeek),
                                        'start_time' => $templateShift['start_time'],
                                        'end_time' => $templateShift['end_time'],
                                        'role' => $employeeRole,
                                        'shift_type' => $templateShift['shift_type'] ?? null,
                                        'requirements' => $templateShift['requirements'] ?? null
                                    ];

                                    Log::info("Created shift for employee {$employeeId} on {$dateString} with role {$employeeRole}");
                                }
                            }
                        } else {
                            Log::info("Employee {$employeeId} not available on {$dateString}");
                        }
                    } else {
                        Log::info("No availability set for employee {$employeeId} on {$dateString} - skipping");
                    }

                    $currentDate->addDay();
                }
            }

            Log::info("Total shifts created: " . count($createdShifts));

            return [
                'success' => true,
                'message' => 'Schedule filled from template successfully',
                'shifts_created' => count($createdShifts),
                'shifts' => $createdShifts
            ];
        } catch (\Exception $e) {
            Log::error('Error in fillFromTemplate: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to fill schedule from template',
                'error' => $e->getMessage(),
                'shifts' => []
            ];
        }
    }

    /**
     * Get template shifts for a department
     * This can be extended to fetch from database
     */
    private function getTemplateShifts(string $department, string $template): array
    {
        // Template shift definitions - roles will be taken from employee assignments
        $templates = [
            'BOH' => [
                'BOH' => [
                    [
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'shift_type' => 'F',
                        'requirements' => 'Food Prep & Cooking'
                    ]
                ]
            ],
            'FOH' => [
                'FOH' => [
                    [
                        'start_time' => '10:00',
                        'end_time' => '18:00',
                        'shift_type' => 'F',
                        'requirements' => 'Floor Service'
                    ]
                ]
            ]
        ];

        return $templates[$department][$template] ?? [];
    }

    /**
     * Extract available time slots from day availability data
     */
    private function getAvailableTimeSlots(array $dayData): array
    {
        $slots = [];

        // Check if status is available
        if (!isset($dayData['status']) || $dayData['status'] !== 'available') {
            return $slots;
        }

        // If type is not set, assume all day availability
        $type = $dayData['type'] ?? 'all_day';

        if ($type === 'all_day') {
            // Available all day - assume 6 AM to 11 PM
            $slots[] = [
                'start' => '06:00',
                'end' => '23:00'
            ];
        } elseif ($type === 'specific_hours' && isset($dayData['hours']) && is_array($dayData['hours'])) {
            // Specific hours available
            foreach ($dayData['hours'] as $hour) {
                if (isset($hour['start']) && isset($hour['end'])) {
                    $slots[] = [
                        'start' => $hour['start'],
                        'end' => $hour['end']
                    ];
                }
            }
        }

        return $slots;
    }

    /**
     * Check if a shift fits within available time slots
     */
    private function shiftFitsInAvailability(array $shift, array $availableSlots): bool
    {
        if (empty($availableSlots)) {
            return false;
        }

        $shiftStart = strtotime($shift['start_time']);
        $shiftEnd = strtotime($shift['end_time']);

        foreach ($availableSlots as $slot) {
            $slotStart = strtotime($slot['start']);
            $slotEnd = strtotime($slot['end']);

            // Check if shift fits completely within the slot
            if ($shiftStart >= $slotStart && $shiftEnd <= $slotEnd) {
                return true;
            }
        }

        return false;
    }
}