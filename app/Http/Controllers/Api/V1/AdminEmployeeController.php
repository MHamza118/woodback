<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Employee;
use App\Models\Questionnaire;
use App\Models\EmployeeFileUpload;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminEmployeeController extends Controller
{
    use ApiResponseTrait;

    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
        // Add middleware for admin-only access in routes
    }

    /**
     * Get all employees with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'stage', 'department', 'search', 'sort_by', 'sort_direction']);
            $perPage = $request->get('per_page', 15);

            $employees = $this->employeeService->getAllEmployees($filters, $perPage);

            return $this->successResponse([
                'employees' => EmployeeResource::collection($employees->items()),
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total()
                ]
            ], 'Employees retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Get employees pending approval
     */
    public function pendingApproval(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getPendingApprovalEmployees();

            return $this->successResponse(
                EmployeeResource::collection($employees),
                'Pending approval employees retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve pending employees: ' . $e->getMessage());
        }
    }

    /**
     * Show specific employee
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $employee = Employee::with(['fileUploads', 'approvedBy', 'trainingAssignments.module', 'trainingProgress'])->find($id);

            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            return $this->successResponse(new EmployeeResource($employee), 'Employee details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve employee: ' . $e->getMessage());
        }
    }

    /**
     * Approve employee
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500'
        ]);

        try {
            $employee = $this->employeeService->approveEmployee($id, $request->user()->id);

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee approved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve employee: ' . $e->getMessage());
        }
    }

    /**
     * Reject employee
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            $employee = $this->employeeService->rejectEmployee(
                $id,
                $request->rejection_reason,
                $request->user()->id
            );

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee rejected successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject employee: ' . $e->getMessage());
        }
    }

    /**
     * Get employee statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeeStatistics();

            return $this->successResponse($stats, 'Employee statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Pause an approved employee (no dashboard access while paused)
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $employee = $this->employeeService->pauseEmployee($id, $request->get('reason'));
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee paused successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to pause employee: ' . $e->getMessage());
        }
    }

    /**
     * Resume a paused employee
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->resumeEmployee($id);
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee resumed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to resume employee: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate employee (mark inactive)
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $employee = $this->employeeService->deactivateEmployee($id, $request->get('reason'));
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee marked inactive successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to deactivate employee: ' . $e->getMessage());
        }
    }

    /**
     * Activate an inactive employee (restore access if stage active)
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->activateEmployee($id);
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }
            return $this->successResponse(new EmployeeResource($employee), 'Employee activated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to activate employee: ' . $e->getMessage());
        }
    }

    /**
     * Delete employee permanently from the database
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $deleted = $this->employeeService->deleteEmployee($id);
            
            if (!$deleted) {
                return $this->notFoundResponse('Employee not found');
            }
            
            return $this->successResponse(
                null,
                'Employee deleted successfully from the database'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete employee: ' . $e->getMessage());
        }
    }

    /**
     * Get all questionnaires
     */
    public function questionnaires(Request $request): JsonResponse
    {
        try {
            $questionnaires = Questionnaire::when($request->get('active_only'), function ($query) {
                return $query->active();
            })
            ->with('createdBy')
            ->ordered()
            ->get();

            return $this->successResponse(
                QuestionnaireResource::collection($questionnaires),
                'Questionnaires retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve questionnaires: ' . $e->getMessage());
        }
    }

    /**
     * Create new questionnaire
     */
    public function createQuestionnaire(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'required|array',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:text,multiple_choice,single_choice,boolean',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array',
            'questions.*.required' => 'boolean',
            'is_active' => 'boolean',
            'order_index' => 'integer|min:0'
        ]);

        try {
            $questionnaire = Questionnaire::create([
                'title' => $request->title,
                'description' => $request->description,
                'questions' => $request->questions,
                'is_active' => $request->get('is_active', true),
                'order_index' => $request->get('order_index', 0),
                'created_by' => $request->user()->id
            ]);

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Questionnaire creation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Update questionnaire
     */
    public function updateQuestionnaire(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'sometimes|array',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:text,multiple_choice,single_choice,boolean',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice,single_choice|array',
            'questions.*.required' => 'boolean',
            'is_active' => 'boolean',
            'order_index' => 'integer|min:0'
        ]);

        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->update($request->only([
                'title', 'description', 'questions', 'is_active', 'order_index'
            ]));

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Questionnaire update failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Delete questionnaire
     */
    public function deleteQuestionnaire(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->delete();

            return $this->successResponse(null, 'Questionnaire deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Get questionnaire by ID
     */
    public function showQuestionnaire(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::with('createdBy')->findOrFail($id);

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                'Questionnaire retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Toggle questionnaire active status
     */
    public function toggleQuestionnaireStatus(string $id): JsonResponse
    {
        try {
            $questionnaire = Questionnaire::findOrFail($id);
            $questionnaire->update(['is_active' => !$questionnaire->is_active]);

            $status = $questionnaire->is_active ? 'activated' : 'deactivated';

            return $this->successResponse(
                new QuestionnaireResource($questionnaire),
                "Questionnaire {$status} successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle questionnaire status: ' . $e->getMessage());
        }
    }

    /**
     * Update employee personal information (Admin only)
     */
    public function updatePersonalInfo(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'zip_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:255',
            'requested_hours' => 'sometimes|nullable|integer|min:1|max:40',
            'emergency_contact' => 'sometimes|nullable|string|max:255',
            'emergency_phone' => 'sometimes|nullable|string|max:20'
        ]);

        try {
            $employee = $this->employeeService->updatePersonalInfoByAdmin($id, $request->all());

            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee personal information updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update personal information: ' . $e->getMessage());
        }
    }

    /**
     * Update employee role assignments
     */
    public function updateRoleAssignments(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'departments' => 'required|array|min:1',
            'departments.*' => 'required|string|in:FOH,BOH',
            'areas' => 'nullable|array',
            'areas.*' => 'string',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string',
            'primaryDepartment' => 'nullable|string|in:FOH,BOH',
            'primaryArea' => 'nullable|string',
            'primaryRole' => 'nullable|string',
            'isFlexible' => 'nullable|boolean'
        ]);

        try {
            $employee = Employee::findOrFail($id);
            
            if (!$employee) {
                return $this->notFoundResponse('Employee not found');
            }

            // Prepare assignments data
            $assignments = [
                'departments' => $request->departments,
                'areas' => $request->areas ?? [],
                'roles' => $request->roles,
                'primaryDepartment' => $request->primaryDepartment ?? ($request->departments[0] ?? null),
                'primaryArea' => $request->primaryArea ?? null,
                'primaryRole' => $request->primaryRole ?? ($request->roles[0] ?? null),
                'isFlexible' => $request->isFlexible ?? false,
                'assignedAt' => $employee->assignments['assignedAt'] ?? now()->toISOString(),
                'assignedBy' => $request->user()->id,
                'lastModified' => now()->toISOString()
            ];

            // Update employee assignments
            $employee->assignments = $assignments;
            $employee->save();

            return $this->successResponse(
                new EmployeeResource($employee),
                'Role assignments updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update role assignments: ' . $e->getMessage());
        }
    }

    /**
     * Download an employee file upload
     */
    public function downloadFile(Request $request, string $id)
    {
        try {
            // Find the file record in the database
            $file = EmployeeFileUpload::findOrFail($id);
            
            // Use the 'public' disk since files are stored there
            $disk = Storage::disk('public');
            
            // Check if file exists in storage
            if (!$disk->exists($file->file_path)) {
                return response()->json([
                    'error' => 'File not found in storage',
                    'path' => $file->file_path
                ], 404);
            }
            
            // Use Laravel's download response for proper binary file handling
            return $disk->download(
                $file->file_path,
                $file->original_filename,
                [
                    'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Pragma' => 'public',
                    'Expires' => '0'
                ]
            );
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to download file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file for employee (admin upload)
     */
    public function uploadFile(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'field_name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $employee = Employee::findOrFail($id);
            
            if (!$request->hasFile('file')) {
                return $this->errorResponse('No file provided', 400);
            }

            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
            $extension = $file->getClientOriginalExtension();
            
            // Generate unique filename
            $storedFilename = uniqid('emp_' . $employee->id . '_') . '.' . $extension;
            
            // Store file in public disk under employee_documents directory
            $filePath = $file->storeAs('employee_documents', $storedFilename, 'public');

            // Create database record
            $fileUpload = EmployeeFileUpload::create([
                'employee_id' => $employee->id,
                'field_name' => $request->field_name,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'file_extension' => $extension,
                'upload_status' => 'verified', // Admin uploads are auto-verified
                'notes' => $request->notes
            ]);

            return $this->successResponse(
                $fileUpload,
                'File uploaded successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload file: ' . $e->getMessage());
        }
    }

    /**
     * Delete an employee file upload
     */
    public function deleteFile(string $id): JsonResponse
    {
        try {
            // Find the file record in the database
            $file = EmployeeFileUpload::findOrFail($id);
            
            // Use the 'public' disk since files are stored there
            $disk = Storage::disk('public');
            
            // Delete file from storage if it exists
            if ($disk->exists($file->file_path)) {
                $disk->delete($file->file_path);
            }
            
            // Delete database record
            $file->delete();
            
            return $this->successResponse(
                null,
                'File deleted successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete file: ' . $e->getMessage());
        }
    }
}
