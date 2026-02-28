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
     * Get roles for a specific department from department_structure table
     */
    public function getRolesForDepartment(string $department): array
    {
        try {
            $departmentStructures = \App\Models\DepartmentStructure::where('department_id', $department)->get();
            
            $roles = [];
            foreach ($departmentStructures as $structure) {
                if (is_array($structure->roles)) {
                    foreach ($structure->roles as $role) {
                        // Handle both string and object roles
                        $roleName = null;
                        
                        if (is_string($role)) {
                            $roleName = $role;
                        } elseif (is_array($role) && isset($role['name'])) {
                            $roleName = (string)$role['name'];
                        } elseif (is_object($role) && isset($role->name)) {
                            $roleName = (string)$role->name;
                        }
                        
                        // Ensure we only add non-empty strings
                        if ($roleName && is_string($roleName) && trim($roleName) !== '' && !in_array($roleName, $roles)) {
                            $roles[] = $roleName;
                        }
                    }
                }
            }
            
            sort($roles);
            return array_values($roles); // Re-index array to ensure clean output
        } catch (\Exception $e) {
            Log::error('Error fetching roles for department: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get employees by department (from role assignments
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
                        
                        // Extract roles for this department from assignments
                        $roles = [];
                        if (isset($assignments['roles']) && is_array($assignments['roles'])) {
                            $roles = $assignments['roles'];
                        }
                        
                        $filtered[] = [
                            'id' => $employee->id,
                            'first_name' => $employee->first_name,
                            'last_name' => $employee->last_name,
                            'email' => $employee->email,
                            'employment_type' => $employee->employment_type,
                            'location' => $employee->location,
                            'roles' => $roles,
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
    public function fillFromTemplate(string $department, string $template, Carbon $weekStart): array
    {
        try {
            // Calculate week end (Sunday)
            $weekEnd = $weekStart->copy()->addDays(6);

            // IMPORTANT: DO NOT DELETE ANY SHIFTS
            // Just create new template shifts alongside existing open shifts
            // Both open shifts and template shifts should coexist

            \Log::info('[fillFromTemplate] Creating template shifts without deleting existing shifts');

            // Get all employees in the department
            $employees = $this->getEmployeesByDepartment($department);

            if (empty($employees)) {
                return [
                    'success' => false,
                    'message' => 'No employees found in this department',
                    'shifts' => []
                ];
            }

            // Template shift definitions - roles will be taken from employee assignments
            $templateShifts = $this->getTemplateShifts($department, $template);

            $createdShifts = [];
            $shiftsCreated = 0;
            $availabilityService = new AvailabilityService();

            // For each employee, create shifts based on their availability
            foreach ($employees as $employee) {
                $employeeId = $employee['id'];

                // Get the employee's actual role from their assignments
                $employeeRole = 'Staff'; // Default role
                if (isset($employee['assignments']['roles']) && is_array($employee['assignments']['roles']) && !empty($employee['assignments']['roles'])) {
                    $employeeRole = $employee['assignments']['roles'][0]; // Use first role
                }

                // Get availability for the entire week
                $weekAvailability = $availabilityService->getEffectiveAvailabilityRange(
                    $employeeId,
                    $weekStart,
                    $weekEnd
                );

                // For each day in the week
                $currentDate = $weekStart->copy();
                while ($currentDate <= $weekEnd) {
                    $dayOfWeek = strtolower($currentDate->format('l')); // monday, tuesday, etc.
                    $dateString = $currentDate->toDateString();

                    // Check if employee is available on this day
                    $dayAvailability = $weekAvailability[$dateString] ?? null;

                    // Only create shifts if availability is explicitly set and employee is available
                    if ($dayAvailability && isset($dayAvailability['availability_data'][$dayOfWeek])) {
                        $dayData = $dayAvailability['availability_data'][$dayOfWeek];

                        // If employee is available on this day
                        if (isset($dayData['enabled']) && $dayData['enabled'] && isset($dayData['status']) && $dayData['status'] === 'available') {
                            // Use the employee's availability times, not template times
                            $startTime = $dayData['startTime'] ?? $dayData['start_time'] ?? '09:00';
                            $endTime = $dayData['endTime'] ?? $dayData['end_time'] ?? '17:00';
                            
                            // IMPORTANT: Allow multiple shifts per employee per day
                            // Open shifts and template shifts can coexist
                            // Do NOT check for existing shifts - they should be separate rows

                            // Use the first template shift for shift_type and requirements
                            $templateShift = $templateShifts[0] ?? [];

                            $shift = Schedule::create([
                                'employee_id' => $employeeId,
                                'department' => $department,
                                'day_of_week' => ucfirst($dayOfWeek),
                                'date' => $currentDate,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'role' => $employeeRole,
                                'shift_type' => $templateShift['shift_type'] ?? 'F',
                                'requirements' => $templateShift['requirements'] ?? null,
                                'week_start' => $weekStart,
                                'week_end' => $weekEnd,
                                'status' => 'active',
                                'created_from' => 'template'
                            ]);

                            $createdShifts[] = [
                                'id' => $shift->id,
                                'employee_id' => $employeeId,
                                'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
                                'date' => $dateString,
                                'day_of_week' => ucfirst($dayOfWeek),
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'role' => $employeeRole,
                                'shift_type' => $templateShift['shift_type'] ?? 'F',
                                'requirements' => $templateShift['requirements'] ?? null,
                                'department' => $department
                            ];

                            $shiftsCreated++;
                        }
                    }

                    $currentDate->addDay();
                }
            }

            return [
                'success' => true,
                'message' => 'Schedule filled from template successfully',
                'shifts_created' => count($createdShifts),
                'shifts' => $createdShifts
            ];
        } catch (\Exception $e) {
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
                        'start_time' => '09:00',
                        'end_time' => '17:00',
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

    /**
     * Create a new shift
     */
    public function createShift(array $data): Schedule
    {
        \Log::info('[ScheduleService.createShift] Starting', [
            'data' => $data
        ]);
        
        try {
            // Create date in UTC timezone to avoid timezone conversion issues
            $date = Carbon::createFromFormat('Y-m-d', $data['date'], 'UTC')->startOfDay();
            
            // Calculate week start (Monday of the week)
            $weekStart = $date->copy()->startOfWeek();
            $weekEnd = $date->copy()->endOfWeek();
            
            \Log::info('[ScheduleService.createShift] Date parsed', [
                'date' => $date->toDateString(),
                'day_of_week' => strtolower($date->format('l')),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ]);
            
            // Use provided created_from when assigning an open shift to an employee; otherwise infer
            $createdFrom = isset($data['created_from']) && in_array($data['created_from'], ['open_shift', 'template', 'manual'], true)
                ? $data['created_from']
                : ($data['employee_id'] ? 'manual' : 'open_shift');
            
            $shift = Schedule::create([
                'employee_id' => $data['employee_id'],
                'department' => $data['department'],
                'date' => $date,
                'day_of_week' => strtolower($date->format('l')),
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'role' => $data['role'],
                'shift_type' => $data['shift_type'] ?? 'regular',
                'requirements' => $data['requirements'] ?? null,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'status' => 'active',
                'created_from' => $createdFrom
            ]);

            \Log::info('[ScheduleService.createShift] Shift created', [
                'shift_id' => $shift->id,
                'date_stored' => $shift->date->toDateString(),
                'created_from' => $createdFrom
            ]);

            $shift->load('employee');
            
            return $shift;
        } catch (\Exception $e) {
            \Log::error('[ScheduleService.createShift] Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing shift
     */
    public function updateShift(int $shiftId, array $data): ?Schedule
    {
        $shift = Schedule::find($shiftId);
        
        if (!$shift) {
            return null;
        }

        $updateData = [];
        
        if (isset($data['employee_id'])) {
            $updateData['employee_id'] = $data['employee_id'];
        }
        
        if (isset($data['department'])) {
            $updateData['department'] = $data['department'];
        }
        
        if (isset($data['date'])) {
            $date = Carbon::createFromFormat('Y-m-d', $data['date'], 'UTC')->startOfDay();
            $updateData['date'] = $date;
            $updateData['day_of_week'] = strtolower($date->format('l'));
        }
        
        if (isset($data['start_time'])) {
            $updateData['start_time'] = $data['start_time'];
        }
        
        if (isset($data['end_time'])) {
            $updateData['end_time'] = $data['end_time'];
        }
        
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        
        if (isset($data['shift_type'])) {
            $updateData['shift_type'] = $data['shift_type'];
        }
        
        if (isset($data['requirements'])) {
            $updateData['requirements'] = $data['requirements'];
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $shift->update($updateData);
        $shift->load('employee');
        
        \Log::info('[ScheduleService.updateShift] Shift updated', [
            'shift_id' => $shift->id,
            'employee_id' => $shift->employee_id,
            'date' => $shift->date->toDateString(),
            'status_updated' => isset($data['status']),
            'new_status' => $shift->status,
            'created_from' => $shift->created_from,
        ]);
        
        return $shift;
    }

    /**
     * Delete a shift (hard delete - completely remove from database)
     */
    public function deleteShift(int $shiftId): bool
    {
        $shift = Schedule::find($shiftId);
        
        if (!$shift) {
            return false;
        }

        // Hard delete - completely remove from database
        $shift->delete();
        
        return true;
    }

    /**
     * Publish schedule for a specific week
     */
    public function publishSchedule(Carbon $weekStart, Carbon $weekEnd, ?string $department = null): array
    {
        try {
            Log::info("Publishing schedule for week {$weekStart} to {$weekEnd}, department: " . ($department ?? 'all'));

            // Create snapshot BEFORE publishing
            $this->createSnapshot($weekStart, $department, 'publish');

            // Build query for shifts to publish
            // IMPORTANT: Only publish shifts that have an employee assigned (employee_id IS NOT NULL)
            // Open shifts (employee_id = NULL) should NOT be published
            $query = Schedule::forWeek($weekStart, $weekEnd)
                ->active()
                ->where('published', false)
                ->whereNotNull('employee_id'); // Only publish assigned shifts

            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }

            $shifts = $query->get();
            
            Log::info("Found {$shifts->count()} unpublished assigned shifts to publish");

            if ($shifts->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No unpublished shifts found for this week',
                    'shifts_published' => 0,
                    'employees_notified' => 0
                ];
            }

            // Mark all shifts as published
            $shiftsPublished = 0;
            $employeeIds = [];

            foreach ($shifts as $shift) {
                Log::info("Publishing shift ID: {$shift->id} for employee: {$shift->employee_id}");
                
                $updateResult = $shift->update([
                    'published' => true,
                    'published_at' => now(),
                    'published_by' => auth()->id() ?? null
                ]);
                
                Log::info("Update result for shift {$shift->id}: " . ($updateResult ? 'success' : 'failed'));
                
                if ($updateResult) {
                    $shiftsPublished++;
                }
                
                // Only add employee_id if it's not null
                if ($shift->employee_id && !in_array($shift->employee_id, $employeeIds)) {
                    $employeeIds[] = $shift->employee_id;
                }
            }
            
            Log::info("Successfully published {$shiftsPublished} shifts");

            // Send notifications to employees
            $employeesNotified = 0;
            foreach ($employeeIds as $employeeId) {
                try {
                    $employee = Employee::find($employeeId);
                    if ($employee) {
                        $employeeShiftsCount = $shifts->where('employee_id', $employeeId)->count();
                        
                        // Create notification for employee using TableNotification
                        \App\Models\TableNotification::create([
                            'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
                            'recipient_id' => $employeeId,
                            'type' => \App\Models\TableNotification::TYPE_SCHEDULE_UPDATE,
                            'title' => 'New Schedule Published',
                            'message' => "Your schedule for the week of {$weekStart->format('M d, Y')} has been published. You have {$employeeShiftsCount} shift(s) assigned.",
                            'priority' => \App\Models\TableNotification::PRIORITY_MEDIUM,
                            'data' => json_encode([
                                'week_start' => $weekStart->toDateString(),
                                'week_end' => $weekEnd->toDateString(),
                                'shifts_count' => $employeeShiftsCount
                            ]),
                            'is_read' => false
                        ]);
                        $employeesNotified++;
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to notify employee {$employeeId}: " . $e->getMessage());
                }
            }

            Log::info("Published {$shiftsPublished} shifts and notified {$employeesNotified} employees");

            return [
                'success' => true,
                'message' => "Successfully published {$shiftsPublished} shifts",
                'shifts_published' => $shiftsPublished,
                'employees_notified' => $employeesNotified
            ];
        } catch (\Exception $e) {
            Log::error('Error publishing schedule: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to publish schedule: ' . $e->getMessage(),
                'shifts_published' => 0,
                'employees_notified' => 0
            ];
        }
    }

    /**
     * Save current schedule as a template
     */
    public function saveAsTemplate(Carbon $weekStart, Carbon $weekEnd, string $name, string $department, ?string $location = null, ?string $description = null, ?int $createdBy = null): array
    {
        try {
            // Get all shifts for the week
            $query = Schedule::forWeek($weekStart, $weekEnd);
            
            // Only filter by department if not "All departments"
            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }
            
            $shifts = $query->get();

            if ($shifts->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No shifts found for this week and department to save as template'
                ];
            }

            // Prepare shifts data for storage
            $shiftsData = [];
            foreach ($shifts as $shift) {
                $shiftsData[] = [
                    'day_of_week' => $shift->day_of_week,
                    'date' => $shift->date ? $shift->date->format('m/d/Y') : null,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'role' => $shift->role,
                    'shift_type' => $shift->shift_type,
                    'requirements' => $shift->requirements,
                    'department' => $shift->department,
                    'status' => $shift->status ?? 'assigned',
                    'employee_id' => $shift->employee_id,
                    'employee_name' => $shift->employee ? $shift->employee->first_name . ' ' . $shift->employee->last_name : null,
                ];
            }

            // Create or update template
            $template = \App\Models\ScheduleTemplate::updateOrCreate(
                [
                    'name' => $name,
                    'department' => $department,
                    'location' => $location
                ],
                [
                    'description' => $description,
                    'shifts_data' => $shiftsData,
                    'created_by' => $createdBy
                ]
            );

            return [
                'success' => true,
                'message' => 'Template saved successfully',
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'department' => $template->department,
                    'location' => $template->location,
                    'shifts_count' => count($shiftsData)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error saving template: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to save template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all templates for a department
     */
    public function getTemplates(?string $department = null, ?string $location = null): array
    {
        try {
            $query = \App\Models\ScheduleTemplate::query();

            if ($department) {
                $query->where('department', $department);
            }

            if ($location) {
                $query->where('location', $location);
            }

            $templates = $query->orderBy('created_at', 'desc')->get();

            return [
                'success' => true,
                'templates' => $templates->map(function ($template) {
                    return [
                        'id' => $template->id,
                        'name' => $template->name,
                        'department' => $template->department,
                        'location' => $template->location,
                        'description' => $template->description,
                        'shifts_count' => count($template->shifts_data),
                        'shifts_data' => $template->shifts_data,
                        'created_at' => $template->created_at,
                        'created_by' => $template->createdBy ? $template->createdBy->name : null
                    ];
                })->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching templates: ' . $e->getMessage());
            return [
                'success' => false,
                'templates' => []
            ];
        }
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(int $templateId): array
    {
        try {
            $template = \App\Models\ScheduleTemplate::find($templateId);

            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found'
                ];
            }

            $template->delete();

            return [
                'success' => true,
                'message' => 'Template deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Error deleting template: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fill schedule from saved template
     */
    public function fillScheduleFromTemplate(int $templateId, Carbon $weekStart): array
    {
        try {
            $template = \App\Models\ScheduleTemplate::find($templateId);

            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found'
                ];
            }

            $weekEnd = $weekStart->copy()->addDays(6);
            $shiftsData = $template->shifts_data;
            $createdShifts = [];

            foreach ($shiftsData as $shiftData) {
                // Calculate the date for this day of week
                $dayOfWeek = $shiftData['day_of_week'];
                $currentDate = $weekStart->copy();
                
                while ($currentDate <= $weekEnd) {
                    if (strtolower($currentDate->format('l')) === strtolower($dayOfWeek)) {
                        // Create shift for this date
                        $shift = Schedule::create([
                            'employee_id' => $shiftData['employee_id'],
                            'department' => $shiftData['department'] ?? $template->department,
                            'day_of_week' => $dayOfWeek,
                            'date' => $currentDate,
                            'start_time' => $shiftData['start_time'],
                            'end_time' => $shiftData['end_time'],
                            'role' => $shiftData['role'],
                            'shift_type' => $shiftData['shift_type'],
                            'requirements' => $shiftData['requirements'],
                            'week_start' => $weekStart,
                            'week_end' => $weekEnd,
                            'status' => 'active',
                            'created_from' => 'template'
                        ]);

                        $createdShifts[] = [
                            'id' => $shift->id,
                            'employee_id' => $shift->employee_id,
                            'employee_name' => $shiftData['employee_name'],
                            'date' => $currentDate->toDateString(),
                            'day_of_week' => $dayOfWeek,
                            'start_time' => $shiftData['start_time'],
                            'end_time' => $shiftData['end_time'],
                            'role' => $shiftData['role'],
                            'shift_type' => $shiftData['shift_type'],
                            'requirements' => $shiftData['requirements'],
                            'department' => $template->department
                        ];
                        break;
                    }
                    $currentDate->addDay();
                }
            }

            return [
                'success' => true,
                'message' => 'Schedule filled from template successfully',
                'shifts_created' => count($createdShifts),
                'shifts' => $createdShifts
            ];
        } catch (\Exception $e) {
            Log::error('Error filling schedule from template: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fill schedule from template: ' . $e->getMessage(),
                'shifts' => []
            ];
        }
    }

    /**
     * Update a template (rename/update description)
     */
    public function updateTemplate(int $templateId, array $data): array
    {
        try {
            $template = \App\Models\ScheduleTemplate::find($templateId);

            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found'
                ];
            }

            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description
            ]);

            return [
                'success' => true,
                'message' => 'Template updated successfully',
                'template' => $template
            ];
        } catch (\Exception $e) {
            Log::error('Error updating template: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Duplicate a template
     */
    public function duplicateTemplate(int $templateId, string $newName): array
    {
        try {
            $originalTemplate = \App\Models\ScheduleTemplate::find($templateId);

            if (!$originalTemplate) {
                return [
                    'success' => false,
                    'message' => 'Template not found'
                ];
            }

            $newTemplate = \App\Models\ScheduleTemplate::create([
                'name' => $newName,
                'department' => $originalTemplate->department,
                'location' => $originalTemplate->location,
                'description' => $originalTemplate->description,
                'shifts_data' => $originalTemplate->shifts_data,
                'created_by' => auth()->id()
            ]);

            return [
                'success' => true,
                'message' => 'Template duplicated successfully',
                'template' => $newTemplate
            ];
        } catch (\Exception $e) {
            Log::error('Error duplicating template: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to duplicate template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Revert schedule to last published state
     */
    public function revertScheduleToLastPublished(Carbon $weekStart, Carbon $weekEnd, ?string $department = null): array
    {
        try {
            Log::info("Reverting schedule for week {$weekStart} to {$weekEnd}, department: " . ($department ?? 'all'));

            // Get all shifts with a saved published state
            $query = Schedule::forWeek($weekStart, $weekEnd)
                ->whereNotNull('last_published_state');

            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }

            $shifts = $query->get();

            Log::info("Found {$shifts->count()} shifts with published state to revert");

            if ($shifts->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No published version found to revert to',
                    'shifts_reverted' => 0
                ];
            }

            $reverted = 0;

            foreach ($shifts as $shift) {
                try {
                    $state = $shift->last_published_state;

                    if (!$state) {
                        continue;
                    }

                    Log::info("Reverting shift ID: {$shift->id} to state: " . json_encode($state));

                    $shift->update([
                        'employee_id' => $state['employee_id'],
                        'start_time' => $state['start_time'],
                        'end_time' => $state['end_time'],
                        'role' => $state['role'],
                        'status' => $state['status'],
                        'published' => false,
                        'published_at' => null,
                        'published_by' => null
                    ]);

                    $reverted++;

                    Log::info("Successfully reverted shift {$shift->id}");
                } catch (\Exception $e) {
                    Log::error("Error reverting shift {$shift->id}: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("Successfully reverted {$reverted} shifts");

            return [
                'success' => true,
                'message' => "Successfully reverted {$reverted} shifts to last published state",
                'shifts_reverted' => $reverted
            ];
        } catch (\Exception $e) {
            Log::error('Error reverting schedule: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to revert schedule: ' . $e->getMessage(),
                'shifts_reverted' => 0
            ];
        }
    }

    /**
     * Clear all shifts for a specific week and department
     */
    public function clearSchedule(Carbon $weekStart, Carbon $weekEnd, ?string $department = null): array
    {
        try {
            Log::info("Clearing schedule for week {$weekStart} to {$weekEnd}, department: " . ($department ?? 'all'));

            // Create snapshot BEFORE clearing
            $this->createSnapshot($weekStart, $department, 'clear');

            // Delete ALL shifts (active, inactive, open, assigned) - hard delete
            $query = Schedule::forWeek($weekStart, $weekEnd);

            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }

            $deletedCount = $query->delete();

            Log::info("Successfully deleted {$deletedCount} shifts");

            return [
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} shifts",
                'deleted_count' => $deletedCount
            ];
        } catch (\Exception $e) {
            Log::error('Error clearing schedule: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to clear schedule: ' . $e->getMessage(),
                'deleted_count' => 0
            ];
        }
    }

    /**
     * Create a snapshot of current schedule state
     */
    private function createSnapshot(Carbon $weekStart, ?string $department = null, string $action = 'manual', ?int $createdBy = null): void
    {
        try {
            $query = Schedule::forWeek($weekStart, $weekStart->copy()->addDays(6));

            if ($department && $department !== 'All departments') {
                $query->forDepartment($department);
            }

            $shifts = $query->get();

            if ($shifts->isEmpty()) {
                return;
            }

            // Prepare shifts data for snapshot
            $shiftsData = [];
            foreach ($shifts as $shift) {
                $shiftsData[] = [
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
                    'status' => $shift->status,
                    'created_from' => $shift->created_from,
                    'is_conflict' => $shift->is_conflict,
                    'published' => $shift->published,
                    'published_at' => $shift->published_at,
                ];
            }

            // Create snapshot
            \App\Models\ScheduleSnapshot::create([
                'week_start_timestamp' => $weekStart->timestamp,
                'department' => $department,
                'shifts_data' => $shiftsData,
                'action' => $action,
                'created_by' => $createdBy ?? auth()->id()
            ]);

            Log::info("Snapshot created for week {$weekStart}, department: " . ($department ?? 'all') . ", action: {$action}");
        } catch (\Exception $e) {
            Log::error('Error creating snapshot: ' . $e->getMessage());
        }
    }

    /**
     * Revert schedule to last snapshot
     */
    public function revertScheduleToLastSnapshot(Carbon $weekStart, Carbon $weekEnd, ?string $department = null): array
    {
        try {
            Log::info("Reverting schedule for week {$weekStart} to {$weekEnd}, department: " . ($department ?? 'all'));

            // Get the most recent snapshot for this week/department
            $query = \App\Models\ScheduleSnapshot::where('week_start_timestamp', $weekStart->timestamp);

            if ($department && $department !== 'All departments') {
                $query->where('department', $department);
            }

            $snapshot = $query->orderBy('created_at', 'desc')->first();

            if (!$snapshot) {
                return [
                    'success' => false,
                    'message' => 'No previous version found to revert to',
                    'shifts_reverted' => 0
                ];
            }

            Log::info("Found snapshot from {$snapshot->created_at}, action: {$snapshot->action}");

            // Delete all current shifts for this week/department
            $deleteQuery = Schedule::forWeek($weekStart, $weekEnd);

            if ($department && $department !== 'All departments') {
                $deleteQuery->forDepartment($department);
            }

            $deleteQuery->delete();

            Log::info("Deleted current shifts for week {$weekStart}");

            // Restore shifts from snapshot
            $shiftsData = $snapshot->shifts_data;
            $restored = 0;

            foreach ($shiftsData as $shiftData) {
                try {
                    Schedule::create([
                        'employee_id' => $shiftData['employee_id'],
                        'department' => $shiftData['department'],
                        'day_of_week' => $shiftData['day_of_week'],
                        'date' => $shiftData['date'],
                        'start_time' => $shiftData['start_time'],
                        'end_time' => $shiftData['end_time'],
                        'role' => $shiftData['role'],
                        'shift_type' => $shiftData['shift_type'],
                        'requirements' => $shiftData['requirements'],
                        'week_start' => $weekStart,
                        'week_end' => $weekEnd,
                        'status' => $shiftData['status'],
                        'created_from' => $shiftData['created_from'],
                        'is_conflict' => $shiftData['is_conflict'],
                        'published' => false,
                        'published_at' => null,
                        'published_by' => null
                    ]);

                    $restored++;
                } catch (\Exception $e) {
                    Log::error("Error restoring shift: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("Successfully restored {$restored} shifts from snapshot");

            return [
                'success' => true,
                'message' => "Successfully reverted to previous state. Restored {$restored} shifts.",
                'shifts_reverted' => $restored
            ];
        } catch (\Exception $e) {
            Log::error('Error reverting schedule: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to revert schedule: ' . $e->getMessage(),
                'shifts_reverted' => 0
            ];
        }
    }
}