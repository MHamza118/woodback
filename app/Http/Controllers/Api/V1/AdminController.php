<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Location;
use App\Models\OnboardingPage;
use App\Models\EmployeeOnboardingProgress;
use App\Models\TrainingAssignment;
use App\Models\TrainingModule;
use App\Models\SystemSetting;
use App\Models\TableNotification;
use App\Services\AdminService;
use App\Http\Requests\CreateAdminRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use ApiResponseTrait;

    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        try {
            $loginData = $this->adminService->login(
                $request->email,
                $request->password
            );

            $response = [
                'user_type' => 'admin',
                'admin' => new AdminResource($loginData['admin']),
                'token' => $loginData['token'],
                'role' => $loginData['role'],
                'status' => $loginData['status'],
                'can_access_dashboard' => $loginData['can_access_dashboard']
            ];

            return $this->successResponse($response, "Admin login successful. Welcome {$loginData['admin']->full_name}!");
        } catch (ValidationException $e) {
            // Return the actual validation error message, not generic "Login failed"
            $errors = $e->errors();
            $message = isset($errors['email']) ? $errors['email'][0] : 'Invalid credentials. Please check your email and password.';
            return $this->errorResponse($message, 401);
        } catch (\Exception $e) {
            return $this->errorResponse('An unexpected error occurred. Please try again.', 500);
        }
    }

    /**
     * Get admin dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();

            // Update last login time
            $admin->update([
                'last_login_at' => now()
            ]);

            // Get dashboard statistics
            $stats = [
                'total_employees' => Employee::count(),
                'pending_employees' => Employee::pendingApproval()->count(),
                'approved_employees' => Employee::approved()->count(),
                'total_customers' => Customer::count(),
                'new_customers_today' => Customer::whereDate('created_at', today())->count(),
                'employees_by_stage' => [
                    'interview' => Employee::byStage(Employee::STAGE_INTERVIEW)->count(),
                    'location_selected' => Employee::byStage(Employee::STAGE_LOCATION_SELECTED)->count(),
                    'questionnaire_completed' => Employee::byStage(Employee::STAGE_QUESTIONNAIRE_COMPLETED)->count(),
                    'active' => Employee::byStage(Employee::STAGE_ACTIVE)->count(),
                ],
                'employees_by_status' => [
                    'pending_approval' => Employee::where('status', Employee::STATUS_PENDING_APPROVAL)->count(),
                    'approved' => Employee::where('status', Employee::STATUS_APPROVED)->count(),
                    'rejected' => Employee::where('status', Employee::STATUS_REJECTED)->count(),
                ]
            ];

            // Get training statistics
            $totalAssignments = TrainingAssignment::count();
            $completedAssignments = TrainingAssignment::where('status', 'completed')->count();
            $pendingAssignments = TrainingAssignment::where('status', 'assigned')->count();
            $inProgressAssignments = TrainingAssignment::where('status', 'in_progress')->count();
            $overdueAssignments = TrainingAssignment::where('status', 'overdue')->count();
            
            $trainingStats = [
                'total_assignments' => $totalAssignments,
                'completed_assignments' => $completedAssignments,
                'pending_assignments' => $pendingAssignments,
                'in_progress_assignments' => $inProgressAssignments,
                'overdue_assignments' => $overdueAssignments,
                'completion_rate' => $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0
            ];
            
            // Add training stats to main stats array
            $stats['training'] = $trainingStats;

            // Get recent activities (last 10 employees registered)
            $recentEmployees = Employee::with(['approvedBy'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'email' => $employee->email,
                        'stage' => $employee->stage,
                        'status' => $employee->status,
                        'created_at' => $employee->created_at->toISOString(),
                        'approved_at' => $employee->approved_at?->toISOString(),
                        'approved_by' => $employee->approvedBy ? $employee->approvedBy->name : null,
                    ];
                });

            // Get recent customers (last 10 customers registered)
            $recentCustomers = Customer::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'created_at' => $customer->created_at->toISOString(),
                    ];
                });

            return $this->successResponse([
                'admin' => new AdminResource($admin),
                'dashboard_data' => [
                    'welcome_message' => "Welcome back, {$admin->full_name}!",
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'last_login_at' => $admin->last_login_at?->toISOString(),
                    'access_level' => 'admin_dashboard',
                    'statistics' => $stats,
                    'recent_activities' => [
                        'employees' => $recentEmployees,
                        'customers' => $recentCustomers,
                    ],
                    'quick_actions' => [
                        'pending_employees_count' => $stats['pending_employees'],
                        'can_approve_employees' => true,
                        'can_manage_customers' => true,
                        'can_view_analytics' => true,
                    ]
                ]
            ], 'Admin dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get dashboard data: ' . $e->getMessage());
        }
    }

    /**
     * Get admin profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return $this->successResponse(
                new AdminResource($admin),
                'Admin profile retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage());
        }
    }

    /**
     * Logout admin
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Admin logout successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all interviewers (admins marked as interviewers)
     */
    public function getInterviewers(Request $request): JsonResponse
    {
        try {
            $interviewers = Admin::interviewers()
                ->select('id', 'first_name', 'last_name', 'email', 'role')
                ->orderBy('first_name')
                ->get();
            
            return $this->successResponse([
                'interviewers' => $interviewers
            ], 'Interviewers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get interviewers: ' . $e->getMessage());
        }
    }

    /**
     * Get all admin users (role-based access)
     */
    public function getAdminUsers(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $filters = $request->only(['role', 'status']);
            
            $result = $this->adminService->getAdminUsers($admin, $filters);
            
            return $this->successResponse([
                'users' => AdminResource::collection($result['admins']),
                'meta' => [
                    'total' => $result['total'],
                    'can_create' => $result['can_create'],
                    'requesting_user_role' => $result['requesting_user_role']
                ]
            ], 'Admin users retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get admin users: ' . $e->getMessage());
        }
    }

    /**
     * Create new admin/manager user (OWNER only)
     */
    public function createAdminUser(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:admins,email',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string',
                'role' => 'required|in:owner,admin,manager,hiring_manager,expo',
                'location_id' => 'nullable|exists:locations,id',
                'department' => 'nullable|string',
                'notes' => 'nullable|string',
                'is_interviewer' => 'nullable|boolean'
            ]);

            $admin = $request->user();
            $result = $this->adminService->createAdminUser($admin, $validatedData);
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
                'message' => $result['message']
            ], $result['message'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create admin user: ' . $e->getMessage());
        }
    }

    /**
     * Update admin user
     */
    public function updateAdminUser(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:admins,email,' . $id,
                'password' => 'nullable|string|min:8|confirmed',
                'phone' => 'nullable|string',
                'role' => 'sometimes|required|in:owner,admin,manager,hiring_manager,expo',
                'location_id' => 'nullable|exists:locations,id',
                'department' => 'nullable|string',
                'is_interviewer' => 'nullable|boolean'
            ]);

            $adminUser = Admin::findOrFail($id);
            
            // Update fields
            if (isset($validatedData['first_name'])) {
                $adminUser->first_name = $validatedData['first_name'];
            }
            if (isset($validatedData['last_name'])) {
                $adminUser->last_name = $validatedData['last_name'];
            }
            if (isset($validatedData['email'])) {
                $adminUser->email = $validatedData['email'];
            }
            if (isset($validatedData['password'])) {
                $adminUser->password = bcrypt($validatedData['password']);
            }
            if (isset($validatedData['phone'])) {
                $adminUser->phone = $validatedData['phone'];
            }
            if (isset($validatedData['role'])) {
                $adminUser->role = $validatedData['role'];
            }
            if (isset($validatedData['location_id'])) {
                $adminUser->location_id = $validatedData['location_id'];
            }
            if (isset($validatedData['department'])) {
                $adminUser->department = $validatedData['department'];
            }
            if (isset($validatedData['is_interviewer'])) {
                $adminUser->is_interviewer = $validatedData['is_interviewer'];
            }
            
            $adminUser->save();
            
            return $this->successResponse([
                'user' => new AdminResource($adminUser->fresh()),
            ], 'Admin user updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update admin user: ' . $e->getMessage());
        }
    }

    /**
     * Update admin user permissions
     */
    public function updateAdminPermissions(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'string|in:' . implode(',', Admin::getAllPermissions())
            ]);

            $admin = $request->user();
            $result = $this->adminService->updateAdminPermissions(
                $admin, 
                (int)$id, 
                $request->permissions
            );
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update permissions: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate admin user
     */
    public function deactivateAdminUser(Request $request, $id): JsonResponse
    {
        try {
            $admin = $request->user();
            $result = $this->adminService->deactivateAdminUser($admin, (int)$id);
            
            return $this->successResponse([
                'user' => new AdminResource($result['admin']),
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to deactivate user: ' . $e->getMessage());
        }
    }

    /**
     * Get available roles and permissions for management
     */
    public function getRolesAndPermissions(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $result = $this->adminService->getAvailableRolesAndPermissions($admin);
            
            return $this->successResponse($result, 'Roles and permissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get roles and permissions: ' . $e->getMessage());
        }
    }

    /**
     * Get all locations
     */
    public function getLocations(Request $request): JsonResponse
    {
        try {
            $locations = Location::active()->orderBy('name')->get();
            
            return $this->successResponse(
                $locations,
                'Locations retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get locations: ' . $e->getMessage());
        }
    }

    /**
     * Create new location
     */
    public function createLocation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:50',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'description' => 'nullable|string|max:1000',
                'settings' => 'nullable|array'
            ]);

            $location = Location::create([
                'name' => $request->name,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'country' => $request->country ?? 'USA',
                'phone' => $request->phone,
                'email' => $request->email,
                'description' => $request->description,
                'settings' => $request->settings ?? [],
                'created_by' => $request->user()->id,
                'active' => true
            ]);
            
            return $this->successResponse(
                $location,
                'Location created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create location: ' . $e->getMessage());
        }
    }

    /**
     * Update location
     */
    public function updateLocation(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:50',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'description' => 'nullable|string|max:1000',
                'active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            $location = Location::findOrFail($id);
            $location->update($validatedData);
            
            return $this->successResponse(
                $location->fresh(),
                'Location updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update location: ' . $e->getMessage());
        }
    }

    /**
     * Delete/deactivate location
     */
    public function deleteLocation(Request $request, $id): JsonResponse
    {
        try {
            $location = Location::findOrFail($id);
            $location->update(['active' => false]);
            
            return $this->successResponse(
                $location->fresh(),
                'Location deactivated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete location: ' . $e->getMessage());
        }
    }

    /**
     * Get all onboarding pages
     */
    public function getOnboardingPages(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userRole = $user ? $user->role : null;
            
            // Build query
            $query = OnboardingPage::query();
            
            // Admin and Owner see all pages
            // Manager sees their own, hiring_manager created, and approved pages
            // Hiring Manager sees their own and approved pages
            // Employees (if this endpoint is ever called by them) see only approved active pages
            if (in_array($userRole, ['admin', 'owner'])) {
                // Admin/Owner see everything
                $query->ordered();
            } else if ($userRole === 'manager') {
                // Manager sees:
                // 1. Their own submissions
                // 2. Pages created by hiring_manager (for approval)
                // 3. Approved pages
                $query->where(function($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('creator', function($creatorQuery) {
                          $creatorQuery->where('role', 'hiring_manager');
                      })
                      ->orWhere('approval_status', 'approved');
                })->ordered();
            } else if ($userRole === 'hiring_manager') {
                // Hiring Manager sees their own and approved pages
                $query->where(function($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhere('approval_status', 'approved');
                })->ordered();
            } else {
                // Others (employees) see only approved active pages
                $query->approved()->active()->ordered();
            }
            
            $pages = $query->with(['creator', 'approver'])->get();
            
            return $this->successResponse(
                $pages,
                'Onboarding pages retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get onboarding pages: ' . $e->getMessage());
        }
    }

    /**
     * Create new onboarding page
     */
    public function createOnboardingPage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'icon' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:1',
                'active' => 'nullable|boolean',
                'has_test' => 'nullable|boolean',
                'test_questions' => 'nullable|array',
                'test_questions.*.id' => 'required',
                'test_questions.*.question' => 'required|string',
                'test_questions.*.options' => 'required|array|size:4',
                'test_questions.*.correctAnswer' => 'required|integer|min:0|max:3',
                'passing_score' => 'nullable|integer|min:0|max:100'
            ]);

            $user = $request->user();
            $userRole = $user->role;
            
            // Determine approval status based on role
            // Admin and Owner: auto-approve
            // Manager and Hiring Manager: pending approval
            $approvalStatus = in_array($userRole, ['admin', 'owner']) ? 'approved' : 'pending';
            $approvedBy = $approvalStatus === 'approved' ? $user->id : null;
            $approvedAt = $approvalStatus === 'approved' ? now() : null;

            $page = OnboardingPage::create([
                'title' => $request->title,
                'content' => $request->content,
                'icon' => $request->icon ?? 'BookOpen',
                'order' => $request->order ?? (OnboardingPage::max('order') + 1),
                'active' => $request->active ?? true,
                'approval_status' => $approvalStatus,
                'created_by' => $user->id,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'has_test' => $request->has_test ?? false,
                'test_questions' => $request->test_questions ?? null,
                'passing_score' => $request->passing_score ?? 80
            ]);
            
            // Create notification for admin/owner when manager/hiring_manager submits for approval
            if ($approvalStatus === 'pending') {
                try {
                    TableNotification::create([
                        'type' => 'onboarding_document_pending',
                        'title' => 'New Onboarding Document',
                        'message' => $user->name . ' created a new onboarding document "' . $request->title . '" pending approval.',
                        'order_number' => null,
                        'recipient_type' => TableNotification::RECIPIENT_ADMIN,
                        'recipient_id' => null,
                        'priority' => TableNotification::PRIORITY_MEDIUM,
                        'data' => [
                            'type' => 'onboarding_document',
                            'page_id' => $page->id,
                            'creator_id' => $user->id,
                            'creator_name' => $user->name,
                            'creator_role' => $userRole,
                            'title' => $request->title
                        ],
                        'is_read' => false
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create onboarding notification: ' . $e->getMessage());
                }
            }
            
            $message = $approvalStatus === 'pending' 
                ? 'Onboarding page submitted for approval' 
                : 'Onboarding page created successfully';
            
            return $this->successResponse(
                $page->load(['creator', 'approver']),
                $message,
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Update onboarding page
     */
    public function updateOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'icon' => 'nullable|string|max:50',
                'order' => 'nullable|integer|min:1',
                'active' => 'nullable|boolean',
                'has_test' => 'nullable|boolean',
                'test_questions' => 'nullable|array',
                'test_questions.*.id' => 'required',
                'test_questions.*.question' => 'required|string',
                'test_questions.*.options' => 'required|array|size:4',
                'test_questions.*.correctAnswer' => 'required|integer|min:0|max:3',
                'passing_score' => 'nullable|integer|min:0|max:100'
            ]);

            $page = OnboardingPage::findOrFail($id);
            $user = $request->user();
            $userRole = $user->role;
            
            // If manager/hiring_manager is updating (possibly resubmitting after rejection)
            // reset approval status to pending
            if (in_array($userRole, ['manager', 'hiring_manager'])) {
                $validatedData['approval_status'] = 'pending';
                $validatedData['approved_by'] = null;
                $validatedData['approved_at'] = null;
                $validatedData['rejection_reason'] = null;
            }
            // Admin/Owner updates don't change approval status if already approved
            
            $page->update($validatedData);
            
            $message = isset($validatedData['approval_status']) && $validatedData['approval_status'] === 'pending'
                ? 'Onboarding page resubmitted for approval'
                : 'Onboarding page updated successfully';
            
            return $this->successResponse(
                $page->fresh()->load(['creator', 'approver']),
                $message
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Delete onboarding page
     */
    public function deleteOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $page = OnboardingPage::findOrFail($id);
            
            // Delete all employee progress for this page first
            EmployeeOnboardingProgress::where('onboarding_page_id', $id)->delete();
            
            // Hard delete the page
            $page->delete();
            
            return $this->successResponse(null, 'Onboarding page deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Toggle onboarding page active status
     */
    public function toggleOnboardingPageStatus(Request $request, $id): JsonResponse
    {
        try {
            $page = OnboardingPage::findOrFail($id);
            $page->update(['active' => !$page->active]);
            
            $status = $page->active ? 'activated' : 'deactivated';
            
            return $this->successResponse(
                $page->fresh(),
                "Onboarding page {$status} successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle page status: ' . $e->getMessage());
        }
    }

    /**
     * Approve onboarding page (Admin/Owner/Manager for hiring_manager pages)
     */
    public function approveOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $page = OnboardingPage::with('creator')->findOrFail($id);
            
            // Check permissions
            // Admin and Owner can approve all pages
            // Manager can only approve pages created by hiring_manager
            $canApprove = false;
            
            if (in_array($user->role, ['admin', 'owner'])) {
                $canApprove = true;
            } elseif ($user->role === 'manager' && $page->creator && $page->creator->role === 'hiring_manager') {
                $canApprove = true;
            }
            
            if (!$canApprove) {
                return $this->forbiddenResponse('You do not have permission to approve this onboarding page');
            }
            
            $page->update([
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null
            ]);
            
            // Mark related notification as read
            TableNotification::where('type', 'onboarding_document_pending')
                ->where('data->page_id', $page->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
            
            return $this->successResponse(
                $page->fresh()->load(['creator', 'approver']),
                'Onboarding page approved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Reject onboarding page (Admin/Owner/Manager for hiring_manager pages)
     */
    public function rejectOnboardingPage(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string|max:1000'
            ]);
            
            $user = $request->user();
            $page = OnboardingPage::with('creator')->findOrFail($id);
            
            // Check permissions
            // Admin and Owner can reject all pages
            // Manager can only reject pages created by hiring_manager
            $canReject = false;
            
            if (in_array($user->role, ['admin', 'owner'])) {
                $canReject = true;
            } elseif ($user->role === 'manager' && $page->creator && $page->creator->role === 'hiring_manager') {
                $canReject = true;
            }
            
            if (!$canReject) {
                return $this->forbiddenResponse('You do not have permission to reject this onboarding page');
            }
            
            $page->update([
                'approval_status' => 'rejected',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);
            
            // Mark related notification as read
            TableNotification::where('type', 'onboarding_document_pending')
                ->where('data->page_id', $page->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
            
            return $this->successResponse(
                $page->fresh()->load(['creator', 'approver']),
                'Onboarding page rejected'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject onboarding page: ' . $e->getMessage());
        }
    }

    /**
     * Get count of pending onboarding pages (Admin/Owner only)
     */
    public function getPendingOnboardingCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Only admin and owner can see pending count
            if (!in_array($user->role, ['admin', 'owner'])) {
                return $this->successResponse(['count' => 0], 'Pending onboarding count retrieved');
            }
            
            $count = OnboardingPage::where('approval_status', 'pending')->count();
            
            return $this->successResponse(
                ['count' => $count],
                'Pending onboarding count retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get pending count: ' . $e->getMessage());
        }
    }

    /**
     * Get test results for a specific onboarding page
     */
    public function getOnboardingPageTestResults(Request $request, $id): JsonResponse
    {
        try {
            $page = OnboardingPage::findOrFail($id);
            
            if (!$page->hasTest()) {
                return $this->errorResponse('This onboarding page does not have a test');
            }
            
            $results = \App\Models\OnboardingPageTestResult::where('onboarding_page_id', $id)
                ->with(['employee:id,full_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($result) {
                    return [
                        'id' => $result->id,
                        'employee' => $result->employee,
                        'attempt_number' => $result->attempt_number,
                        'score' => $result->score,
                        'passed' => $result->passed,
                        'completed_at' => $result->completed_at->toISOString()
                    ];
                });
            
            return $this->successResponse([
                'page_title' => $page->title,
                'passing_score' => $page->getPassingScore(),
                'results' => $results
            ], 'Test results retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get test results: ' . $e->getMessage());
        }
    }

    /**
     * Create new customer (Admin only)
     */
    public function createCustomer(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:customers,email',
                'phone' => 'nullable|string|regex:/^\+1 \(\d{3}\) \d{3}-\d{4}$/|max:18',
                'password' => 'required|string|min:6',
                'home_location' => 'nullable|string|max:255',
                'homeLocation' => 'nullable|string|max:255', // Support frontend field name
                'status' => 'in:ACTIVE,INACTIVE,SUSPENDED',
                'loyalty_points' => 'integer|min:0'
            ], [
                'phone.regex' => 'Phone number must be in valid US format: +1 (XXX) XXX-XXXX'
            ]);

            // Map frontend field names to backend field names
            if (isset($validatedData['homeLocation'])) {
                $validatedData['home_location'] = $validatedData['homeLocation'];
            }

            $customer = Customer::create([
                'name' => $validatedData['name'],
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'] ?? null,
                'password' => bcrypt($validatedData['password']),
                'home_location' => $validatedData['home_location'] ?? null,
                'status' => $validatedData['status'] ?? 'ACTIVE',
                'loyalty_points' => $validatedData['loyalty_points'] ?? 0,
                'total_orders' => 0,
                'total_spent' => 0.00,
                'preferences' => []
            ]);

            return $this->successResponse(
                $customer,
                'Customer created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Update customer (Admin only)
     */
    public function updateCustomer(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:customers,email,' . $id,
                'phone' => 'nullable|string|regex:/^\+1 \(\d{3}\) \d{3}-\d{4}$/|max:18',
                'password' => 'nullable|string|min:6', // Optional for updates
                'home_location' => 'nullable|string|max:255',
                'homeLocation' => 'nullable|string|max:255', // Support frontend field name
                'status' => 'in:ACTIVE,INACTIVE,SUSPENDED',
                'loyalty_points' => 'nullable|integer|min:0',
                'loyaltyPoints' => 'nullable|integer|min:0', // Support frontend field name
                'locations' => 'nullable|array',
                'locations.*' => 'string'
            ], [
                'phone.regex' => 'Phone number must be in valid US format: +1 (XXX) XXX-XXXX'
            ]);

            // Map frontend field names to backend field names
            if (isset($validatedData['homeLocation'])) {
                $validatedData['home_location'] = $validatedData['homeLocation'];
                unset($validatedData['homeLocation']);
            }
            if (isset($validatedData['loyaltyPoints'])) {
                $validatedData['loyalty_points'] = $validatedData['loyaltyPoints'];
                unset($validatedData['loyaltyPoints']);
            }

            // Hash password if provided
            if (isset($validatedData['password'])) {
                $validatedData['password'] = bcrypt($validatedData['password']);
            }

            $customer = Customer::findOrFail($id);
            $customer->update($validatedData);

            return $this->successResponse(
                $customer->fresh(),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update customer: ' . $e->getMessage());
        }
    }

    /**
     * Get all customers with filters
     */
    public function getCustomers(Request $request): JsonResponse
    {
        try {
            $query = Customer::query();
            
            // Apply filters
            if ($request->has('status') && $request->status !== 'All') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('location') && $request->location !== 'All') {
                $query->where('home_location', $request->location);
            }
            
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }
            
            // Apply sorting
            $sortBy = $request->get('sortBy', 'created_at');
            $sortOrder = $request->get('sortOrder', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Get customers
            $customers = $query->get();
            
            // Get pagination meta information
            $meta = [
                'total' => Customer::count(),
                'filtered_total' => $customers->count(),
                'active_customers' => Customer::where('status', 'Active')->count(),
                'inactive_customers' => Customer::where('status', 'Inactive')->count(),
                'paused_customers' => Customer::where('status', 'Paused')->count()
            ];
            
            return $this->successResponse([
                'customers' => $customers,
                'meta' => $meta
            ], 'Customers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customers: ' . $e->getMessage());
        }
    }

    /**
     * Get specific customer details
     */
    public function getCustomer(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            
            return $this->successResponse(
                $customer,
                'Customer retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customer: ' . $e->getMessage());
        }
    }

    /**
     * Delete customer (Admin only)
     */
    public function deleteCustomer(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $customerName = $customer->name;
            
            $customer->delete();
            
            return $this->successResponse(
                null,
                "Customer '{$customerName}' deleted successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'Active')->count(),
                'inactive_customers' => Customer::where('status', 'Inactive')->count(),
                'paused_customers' => Customer::where('status', 'Paused')->count(),
                'new_customers_today' => Customer::whereDate('created_at', today())->count(),
                'new_customers_this_week' => Customer::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'new_customers_this_month' => Customer::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'total_loyalty_points' => Customer::sum('loyalty_points'),
                'total_orders' => Customer::sum('total_orders'),
                'total_revenue' => Customer::sum('total_spent'),
                'average_order_value' => Customer::where('total_orders', '>', 0)
                    ->avg('total_spent'),
                'top_locations' => Customer::selectRaw('home_location, COUNT(*) as customer_count')
                    ->whereNotNull('home_location')
                    ->groupBy('home_location')
                    ->orderByDesc('customer_count')
                    ->limit(5)
                    ->get()
            ];
            
            return $this->successResponse(
                $stats,
                'Customer statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get customer statistics: ' . $e->getMessage());
        }
    }

    /**
     * Create new employee with direct dashboard access (admin only)
     */
    public function createEmployee(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:employees,email',
                'password' => 'required|string|min:6',
                'phone' => 'required|string|max:50', // Support international formats
                'position' => 'required|string|max:255',
                'department' => 'nullable|string|max:255',
                'location_id' => 'required|exists:locations,id'
            ]);

            // Get the location name for the employee record
            $location = Location::findOrFail($validatedData['location_id']);

            // Create employee with approved status and active stage (bypass onboarding)
            $employee = Employee::create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'],
                'position' => $validatedData['position'],
                'department' => $validatedData['department'] ?? 'Front of House',
                'location' => $location->name,
                'stage' => Employee::STAGE_ACTIVE,
                'status' => Employee::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id
            ]);

            return $this->successResponse(
                $employee->load('approvedBy'),
                'Employee created successfully with direct dashboard access',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Validation failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create employee: ' . $e->getMessage());
        }
    }

    /**
     * Get notification preferences (Expo only)
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            return $this->successResponse([
                'notifications_enabled' => $user->notifications_enabled ?? true
            ], 'Notification preferences retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get notification preferences: ' . $e->getMessage());
        }
    }

    /**
     * Toggle notification preferences (Expo only)
     */
    public function toggleNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Only allow expo users to toggle notifications
            if ($user->role !== 'expo') {
                return $this->errorResponse('Only expo users can toggle notifications', 403);
            }
            
            $request->validate([
                'enabled' => 'required|boolean'
            ]);
            
            $user->notifications_enabled = $request->enabled;
            $user->save();
            
            return $this->successResponse([
                'notifications_enabled' => $user->notifications_enabled
            ], 'Notification preferences updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update notification preferences: ' . $e->getMessage());
        }
    }

    /**
     * Toggle onboarding notifications for admin
     */
    public function toggleOnboardingNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Toggle the onboarding notifications setting
            $user->update([
                'onboarding_notifications_enabled' => !$user->onboarding_notifications_enabled
            ]);
            
            return $this->successResponse([
                'onboarding_notifications_enabled' => $user->fresh()->onboarding_notifications_enabled
            ], 'Onboarding notification preferences updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update onboarding notification preferences: ' . $e->getMessage());
        }
    }
    
    /**
     * Get employee welcome message setting
     */
    public function getWelcomeMessage(Request $request): JsonResponse
    {
        try {
            $welcomeMessage = SystemSetting::get('employee_welcome_message', 'Here\'s your personalized dashboard with training progress, anniversaries, and important updates.');
            
            return $this->successResponse([
                'message' => $welcomeMessage
            ], 'Welcome message retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get welcome message: ' . $e->getMessage());
        }
    }
    
    /**
     * Update employee welcome message setting (Owner/Admin/Manager only)
     */
    public function updateWelcomeMessage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Only owner, admin, and manager can update welcome message
            if (!in_array($user->role, ['owner', 'admin', 'manager'])) {
                return $this->forbiddenResponse('Only owner, admin, and manager can update welcome message');
            }
            
            $request->validate([
                'message' => 'required|string|max:500'
            ]);
            
            SystemSetting::set(
                'employee_welcome_message',
                $request->message,
                'text',
                'Welcome message shown on employee dashboard'
            );
            
            return $this->successResponse([
                'message' => $request->message
            ], 'Welcome message updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update welcome message: ' . $e->getMessage());
        }
    }
}
