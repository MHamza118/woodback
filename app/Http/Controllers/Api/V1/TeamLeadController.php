<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\TeamLeadAssignment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamLeadController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get team leads for a specific date and department
     */
    public function getTeamLeads(Request $request): JsonResponse
    {
        try {
            Log::info('[getTeamLeads] Raw request data', [
                'all' => $request->all(),
                'date' => $request->input('date'),
                'department' => $request->input('department'),
            ]);

            // Don't validate department strictly - just accept any string
            $validated = $request->validate([
                'date' => 'required|date_format:Y-m-d',
                'department' => 'required|string'
            ]);

            $date = $validated['date'];
            $department = $validated['department'];

            Log::info('[getTeamLeads] After validation', [
                'date' => $date,
                'department' => $department
            ]);

            $query = TeamLeadAssignment::forDate($date);
            
            // Only filter by department if not "All departments"
            if ($department !== 'All departments') {
                $query->forDepartment($department);
            }

            $teamLeads = $query->with('employee')
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'employee_id' => $assignment->employee_id,
                        'employee_name' => $assignment->employee ? $assignment->employee->first_name . ' ' . $assignment->employee->last_name : null,
                        'assigned_date' => $assignment->assigned_date->toDateString(),
                        'department' => $assignment->department,
                        'assigned_by_admin_id' => $assignment->assigned_by_admin_id,
                        'created_at' => $assignment->created_at,
                    ];
                });

            Log::info('[getTeamLeads] Team leads retrieved', [
                'count' => $teamLeads->count()
            ]);

            return $this->successResponse([
                'team_leads' => $teamLeads,
                'count' => $teamLeads->count(),
                'date' => $date,
                'department' => $department
            ], 'Team leads retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('[getTeamLeads] Validation error', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('[getTeamLeads] Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to fetch team leads: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign an employee as team lead for a specific date
     */
    public function assignTeamLead(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'assigned_date' => 'required|date_format:Y-m-d',
                'department' => 'required|string|in:BOH,FOH'
            ]);

            // Check if employee has a shift on this date in this department
            $employee = Employee::find($validated['employee_id']);
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            // Check if already assigned as team lead
            $existing = TeamLeadAssignment::where('employee_id', $validated['employee_id'])
                ->whereDate('assigned_date', $validated['assigned_date'])
                ->where('department', $validated['department'])
                ->first();

            if ($existing) {
                return $this->errorResponse('Employee is already assigned as team lead for this date and department', 400);
            }

            // Create the assignment
            $assignment = TeamLeadAssignment::create([
                'employee_id' => $validated['employee_id'],
                'assigned_date' => $validated['assigned_date'],
                'department' => $validated['department'],
                'assigned_by_admin_id' => auth()->user()->id ?? null,
            ]);

            return $this->successResponse([
                'team_lead' => [
                    'id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'assigned_date' => $assignment->assigned_date->toDateString(),
                    'department' => $assignment->department,
                    'assigned_by_admin_id' => $assignment->assigned_by_admin_id,
                    'created_at' => $assignment->created_at,
                ]
            ], 'Team lead assigned successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            Log::error('Error assigning team lead: ' . $e->getMessage());
            return $this->errorResponse('Failed to assign team lead: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove a team lead assignment
     */
    public function removeTeamLead(Request $request, int $assignmentId): JsonResponse
    {
        try {
            $assignment = TeamLeadAssignment::find($assignmentId);

            if (!$assignment) {
                return $this->notFoundResponse('Team lead assignment not found');
            }

            $assignment->delete();

            return $this->successResponse(null, 'Team lead assignment removed successfully');
        } catch (\Exception $e) {
            Log::error('Error removing team lead: ' . $e->getMessage());
            return $this->errorResponse('Failed to remove team lead', 500);
        }
    }
}
