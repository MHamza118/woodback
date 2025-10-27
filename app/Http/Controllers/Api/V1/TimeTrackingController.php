<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\EmployeeClockStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TimeTrackingController extends Controller
{
    /**
     * Clock in an employee
     */
    public function clockIn(Request $request)
    {
        try {
            $employee = $request->user();
            
            if (!$employee instanceof Employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Employee must be logged in to clock in',
                    'errorType' => 'NOT_LOGGED_IN'
                ], 401);
            }

            // Validate location info and client time
            $validator = Validator::make($request->all(), [
                'location_info' => 'nullable|array',
                'qr_code_data' => 'required|string',
                'client_date' => 'required|string',
                'client_time' => 'required|string',
                'client_date_time' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first(),
                    'errorType' => 'VALIDATION_ERROR'
                ], 422);
            }

            // Validate QR code
            if ($request->qr_code_data !== 'CLOCK_IN_RESTAURANT_GENERAL') {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid QR code for clock-in',
                    'errorType' => 'INVALID_QR'
                ], 400);
            }

            // Use client date and time
            $clientDate = $request->client_date;
            $clientTime = $request->client_time;

            // Check if already clocked in today
            $existingEntry = TimeEntry::where('employee_id', $employee->id)
                ->where('date', $clientDate)
                ->whereNull('clock_out_time')
                ->first();

            if ($existingEntry) {
                return response()->json([
                    'success' => false,
                    'error' => 'Already clocked in. Please clock out first.',
                    'errorType' => 'ALREADY_CLOCKED_IN',
                    'existingEntry' => $existingEntry
                ], 400);
            }

            DB::beginTransaction();

            // Create new time entry using CLIENT time
            $timeEntry = TimeEntry::create([
                'employee_id' => $employee->id,
                'date' => $clientDate,
                'clock_in_time' => $clientTime,
                'location_info' => $request->location_info,
                'status' => 'APPROVED', // Auto-approved
            ]);

            // Update or create clock status
            EmployeeClockStatus::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'is_currently_clocked' => true,
                    'current_time_entry_id' => $timeEntry->id,
                    'last_clock_in' => $timeEntry->clock_in_time,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully clocked in',
                'timeEntry' => $timeEntry
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to process clock-in: ' . $e->getMessage(),
                'errorType' => 'PROCESSING_ERROR'
            ], 500);
        }
    }

    /**
     * Clock out an employee
     */
    public function clockOut(Request $request)
    {
        try {
            $employee = $request->user();
            
            if (!$employee instanceof Employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Employee must be logged in to clock out',
                    'errorType' => 'NOT_LOGGED_IN'
                ], 401);
            }

            // Validate QR code and client time
            $validator = Validator::make($request->all(), [
                'qr_code_data' => 'required|string',
                'client_date' => 'required|string',
                'client_time' => 'required|string',
                'client_date_time' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first(),
                    'errorType' => 'VALIDATION_ERROR'
                ], 422);
            }

            if ($request->qr_code_data !== 'CLOCK_OUT_RESTAURANT_GENERAL') {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid QR code for clock-out',
                    'errorType' => 'INVALID_QR'
                ], 400);
            }

            // Use client date and time
            $clientDate = $request->client_date;
            $clientTime = $request->client_time;

            // Find active time entry
            $timeEntry = TimeEntry::where('employee_id', $employee->id)
                ->where('date', $clientDate)
                ->whereNull('clock_out_time')
                ->first();

            if (!$timeEntry) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not currently clocked in. Please clock in first.',
                    'errorType' => 'NOT_CLOCKED_IN'
                ], 400);
            }

            DB::beginTransaction();

            // Update time entry with clock out using CLIENT time
            $clockOutTime = $clientTime;
            $timeEntry->clock_out_time = $clockOutTime;
            $timeEntry->total_hours = $timeEntry->calculateTotalHours();
            $timeEntry->save();

            // Update clock status
            EmployeeClockStatus::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'is_currently_clocked' => false,
                    'current_time_entry_id' => null,
                    'last_clock_out' => $clockOutTime,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully clocked out',
                'timeEntry' => $timeEntry,
                'hoursWorked' => $timeEntry->total_hours
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to process clock-out: ' . $e->getMessage(),
                'errorType' => 'PROCESSING_ERROR'
            ], 500);
        }
    }

    /**
     * Get employee's current clock status
     */
    public function getClockStatus(Request $request)
    {
        try {
            $employee = $request->user();
            
            if (!$employee instanceof Employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $clockStatus = EmployeeClockStatus::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'is_currently_clocked' => false,
                    'current_time_entry_id' => null,
                    'last_clock_in' => null,
                    'last_clock_out' => null,
                ]
            );

            return response()->json([
                'success' => true,
                'clockStatus' => [
                    'isCurrentlyClocked' => $clockStatus->is_currently_clocked,
                    'currentTimeEntryId' => $clockStatus->current_time_entry_id,
                    'lastClockIn' => $clockStatus->last_clock_in,
                    'lastClockOut' => $clockStatus->last_clock_out,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee's time entries
     */
    public function getTimeEntries(Request $request)
    {
        try {
            $employee = $request->user();
            
            if (!$employee instanceof Employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $query = TimeEntry::where('employee_id', $employee->id);

            // Apply date range filter if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            $timeEntries = $query->orderBy('date', 'desc')
                ->orderBy('clock_in_time', 'desc')
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'employeeId' => $entry->employee_id,
                        'date' => $entry->date,
                        'clockInTime' => $entry->clock_in_time,
                        'clockOutTime' => $entry->clock_out_time,
                        'totalHours' => $entry->total_hours,
                        'totalHoursFormatted' => $entry->formatted_total_hours,
                        'status' => $entry->status,
                        'locationInfo' => $entry->location_info,
                    ];
                });

            return response()->json([
                'success' => true,
                'timeEntries' => $timeEntries
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current time entry for employee (today's active entry)
     */
    public function getCurrentTimeEntry(Request $request)
    {
        try {
            $employee = $request->user();
            
            if (!$employee instanceof Employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 401);
            }

            $timeEntry = TimeEntry::where('employee_id', $employee->id)
                ->today()
                ->active()
                ->first();

            return response()->json([
                'success' => true,
                'timeEntry' => $timeEntry
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live roster - all currently clocked in employees (Admin)
     */
    public function getLiveRoster(Request $request)
    {
        try {
            $clockedInStatuses = EmployeeClockStatus::with(['employee', 'currentTimeEntry'])
                ->clockedIn()
                ->get();

            $liveRoster = $clockedInStatuses->map(function ($status) {
                $employee = $status->employee;
                
                return [
                    'id' => $employee->id,
                    'personalInfo' => [
                        'firstName' => $employee->first_name,
                        'lastName' => $employee->last_name,
                        'position' => $employee->position,
                    ],
                    'assignments' => $employee->assignments,
                    'location' => [
                        'name' => $employee->location
                    ],
                    'clockStatus' => [
                        'isCurrentlyClocked' => $status->is_currently_clocked,
                        'lastClockIn' => $status->last_clock_in,
                        'lastClockOut' => $status->last_clock_out,
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'liveRoster' => $liveRoster,
                'total' => $liveRoster->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all time entries for a specific employee (Admin)
     */
    public function getEmployeeTimeEntries(Request $request, $employeeId)
    {
        try {
            $query = TimeEntry::where('employee_id', $employeeId);

            // Apply date range filter if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            $timeEntries = $query->orderBy('date', 'desc')
                ->orderBy('clock_in_time', 'desc')
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'employeeId' => $entry->employee_id,
                        'date' => $entry->date,
                        'clockInTime' => $entry->clock_in_time,
                        'clockOutTime' => $entry->clock_out_time,
                        'totalHours' => $entry->total_hours,
                        'totalHoursFormatted' => $entry->formatted_total_hours,
                        'status' => $entry->status,
                        'locationInfo' => $entry->location_info,
                    ];
                });

            return response()->json([
                'success' => true,
                'timeEntries' => $timeEntries,
                'employeeId' => $employeeId
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all time entries across all employees (Admin)
     */
    public function getAllTimeEntries(Request $request)
    {
        try {
            $query = TimeEntry::with('employee');

            // Apply filters
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $timeEntries = $query->orderBy('date', 'desc')
                ->orderBy('clock_in_time', 'desc')
                ->paginate($request->per_page ?? 50);

            return response()->json([
                'success' => true,
                'timeEntries' => $timeEntries->items(),
                'pagination' => [
                    'total' => $timeEntries->total(),
                    'per_page' => $timeEntries->perPage(),
                    'current_page' => $timeEntries->currentPage(),
                    'last_page' => $timeEntries->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
