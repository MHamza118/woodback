<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRegistrationRequest;
use App\Http\Requests\EmployeeLocationRequest;
use App\Http\Requests\EmployeeQuestionnaireRequest;
use App\Http\Requests\EmployeePersonalInfoRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\QuestionnaireResource;
use App\Services\EmployeeService;
use App\Services\EmployeeTrainingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\OnboardingPage;
use App\Models\EmployeeOnboardingProgress;
use App\Models\TrainingModule;
use App\Models\TrainingAssignment;
use App\Models\TableNotification;
use App\Models\Admin;
use App\Models\SystemSetting;

class EmployeeController extends Controller
{
    use ApiResponseTrait;

    protected $employeeService;
    protected $employeeTrainingService;

    public function __construct(EmployeeService $employeeService, EmployeeTrainingService $employeeTrainingService)
    {
        $this->employeeService = $employeeService;
        $this->employeeTrainingService = $employeeTrainingService;
    }

    /**
     * Register a new employee
     */
    public function register(EmployeeRegistrationRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->register($request->validated());

            return $this->successResponse(
                new EmployeeResource($employee),
                'Employee registration successful. Please wait for admin approval.',
                201
            );
        } catch (ValidationException $e) {
            // Return the actual validation error message from the first error
            $errors = $e->errors();
            $firstError = reset($errors);
            $message = is_array($firstError) ? $firstError[0] : $firstError;
            return $this->errorResponse($message, 422);
        } catch (\Exception $e) {
            return $this->errorResponse('An unexpected error occurred. Please try again.', 500);
        }
    }

    /**
     * Login employee only
     */
    public function employeeLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        try {
            $loginData = $this->employeeService->login(
                $request->email,
                $request->password
            );

            $response = [
                'user_type' => 'employee',
                'employee' => new EmployeeResource($loginData['employee']),
                'token' => $loginData['token'],
                'stage' => $loginData['stage'],
                'status' => $loginData['status'],
                'can_access_dashboard' => $loginData['can_access_dashboard']
            ];

            $message = $loginData['can_access_dashboard'] 
                ? 'Employee login successful. Welcome to the dashboard!' 
                : 'Employee login successful. Please complete your onboarding process.';

            return $this->successResponse($response, $message);
        } catch (ValidationException $e) {
            // Return the actual validation error message
            $errors = $e->errors();
            $message = isset($errors['email']) ? $errors['email'][0] : 'Invalid credentials. Please check your email and password.';
            return $this->errorResponse($message, 401);
        } catch (\Exception $e) {
            return $this->errorResponse('An unexpected error occurred. Please try again.', 500);
        }
    }

    /**
     * Get employee profile and stage information
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $profileData = $this->employeeService->getProfile($request->user()->id);

            if (empty($profileData)) {
                return $this->notFoundResponse('Employee not found');
            }

            // Add personal_info to employee object for resource transformation
            if (isset($profileData['employee']) && isset($profileData['personal_info'])) {
                $profileData['employee']->personal_info = $profileData['personal_info'];
            }

            // Transform the employee data using EmployeeResource
            $profileData['employee'] = new EmployeeResource($profileData['employee']);

            return $this->successResponse($profileData, 'Profile retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage());
        }
    }

    /**
     * Generate QR code for interview stage
     */
    public function generateQR(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            
            if ($employee->stage !== 'interview') {
                return $this->errorResponse('QR code can only be generated in interview stage', 400);
            }

            $qrCode = $this->employeeService->generateInterviewQR($employee->id);

            return $this->successResponse([
                'qr_code' => $qrCode,
                'employee' => new EmployeeResource($employee),
                'stage_info' => [
                    'title' => 'Interview Stage',
                    'description' => 'Scan the QR code to proceed to location selection',
                    'next_step' => 'Select your preferred work location'
                ]
            ], 'QR code generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Submit location selection
     */
    public function submitLocation(EmployeeLocationRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->submitLocation(
                $request->user()->id,
                $request->location
            );

            return $this->successResponse(
                new EmployeeResource($employee),
                'Location submitted successfully. Please proceed to questionnaire.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit location: ' . $e->getMessage());
        }
    }

    /**
     * Get questionnaire for employee
     */
    public function getQuestionnaire(Request $request): JsonResponse
    {
        try {
            $questionnaireData = $this->employeeService->getQuestionnaire($request->user()->id);

            return $this->successResponse([
                'questionnaire' => new QuestionnaireResource($questionnaireData['questionnaire']),
                'employee' => new EmployeeResource($questionnaireData['employee'])
            ], 'Questionnaire retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Submit questionnaire responses
     */
    public function submitQuestionnaire(EmployeeQuestionnaireRequest $request): JsonResponse
    {
        try {
            $responses = $request->responses;
            
            // If responses is a JSON string (from FormData), decode it
            if (is_string($responses)) {
                $responses = json_decode($responses, true);
            }
            
            // Handle file uploads if present
            $uploadedFiles = [];
            $allFiles = $request->allFiles();
            if (!empty($allFiles)) {
                foreach ($allFiles as $fieldName => $file) {
                    if (strpos($fieldName, 'file_') === 0) {
                        $questionIndex = str_replace('file_', '', $fieldName);
                        
                        // Store the file
                        $filename = time() . '_' . $questionIndex . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('employee_documents', $filename, 'public');
                        
                        // Extract question text from responses for this file
                        $questionText = null;
                        if (isset($responses[$questionIndex]) && is_array($responses[$questionIndex])) {
                            $questionText = $responses[$questionIndex]['question'] ?? null;
                        }
                        
                        // Create file upload record
                        $fileUpload = \App\Models\EmployeeFileUpload::create([
                            'employee_id' => $request->user()->id,
                            'field_name' => 'question_' . $questionIndex,
                            'original_filename' => $file->getClientOriginalName(),
                            'stored_filename' => $filename,
                            'file_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'file_extension' => $file->getClientOriginalExtension(),
                            'upload_status' => 'pending',
                            'notes' => $questionText
                        ]);
                        
                        $uploadedFiles[$questionIndex] = $fileUpload;
                    }
                }
            }

            $employee = $this->employeeService->submitQuestionnaire(
                $request->user()->id,
                $responses,
                $uploadedFiles
            );

            return $this->successResponse(
                new EmployeeResource($employee),
                'Questionnaire submitted successfully. Please wait for admin approval.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit questionnaire: ' . $e->getMessage());
        }
    }

    /**
     * Get welcome page data (for questionnaire_completed stage)
     */
    public function getWelcomePage(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            if ($employee->stage !== 'questionnaire_completed') {
                return $this->errorResponse('Welcome page only available for questionnaire completed stage', 400);
            }

            return $this->successResponse([
                'employee' => new EmployeeResource($employee),
                'stage_info' => [
                    'title' => 'Welcome!',
                    'description' => 'Thank you for completing the onboarding process. Your application is now under review.',
                    'status' => $employee->status,
                    'next_step' => 'Please wait for admin approval to access the full dashboard.'
                ]
            ], 'Welcome page data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get welcome page: ' . $e->getMessage());
        }
    }

    /**
     * Get confirmation page (for returning users waiting approval)
     */
    public function getConfirmationPage(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            if ($employee->status !== 'pending_approval') {
                return $this->errorResponse('Confirmation page only available for pending approval status', 400);
            }

            return $this->successResponse([
                'employee' => new EmployeeResource($employee),
                'confirmation_info' => [
                    'title' => 'Application Under Review',
                    'description' => 'Your application is currently being reviewed by our admin team.',
                    'status' => $employee->status,
                    'stage' => $employee->stage,
                    'submitted_at' => $employee->created_at->toISOString(),
                    'message' => 'We will notify you once your application has been processed. Thank you for your patience.'
                ]
            ], 'Confirmation page data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get confirmation page: ' . $e->getMessage());
        }
    }

    /**
     * Get dashboard (only for approved employees)
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            if (!$employee->canAccessDashboard()) {
                return $this->forbiddenResponse('Dashboard access not allowed. Please complete onboarding and wait for approval.');
            }

            // Get current date for calculations
            $today = today();
            $startOfWeek = $today->copy()->startOfWeek();
            $startOfMonth = $today->copy()->startOfMonth();

            // Calculate work statistics (placeholder - implement when time tracking is added)
            $workStats = [
                'hours_this_week' => 0, // TODO: Implement when time tracking system is added
                'shifts_completed_this_month' => 0, // TODO: Implement when shift tracking is added
                'hours_change_from_last_week' => 0
            ];

            // Get training statistics from the training service (uses real progress data)
            $trainingStatsFromService = $this->employeeTrainingService->getEmployeeTrainingStats($employee->id);
            
            // Get overdue assignments for detailed overdue training info
            $trainingAssignments = TrainingAssignment::where('employee_id', $employee->id)->get();
            $overdueAssignments = $trainingAssignments->filter(function ($assignment) use ($today) {
                return $assignment->due_date && 
                       $assignment->due_date < $today && 
                       $assignment->status !== 'completed';
            });

            $trainingStats = [
                'total_assigned' => $trainingStatsFromService['total_assigned'],
                'completed' => $trainingStatsFromService['completed'],
                'in_progress' => $trainingStatsFromService['in_progress'],
                'overdue' => $trainingStatsFromService['overdue'],
                'assigned' => $trainingStatsFromService['assigned'],
                'completion_percentage' => $trainingStatsFromService['completion_rate']
            ];

            // Performance rating (placeholder - implement when performance system is added)
            $performanceStats = [
                'rating' => 0, // TODO: Implement when performance review system is added
                'rating_text' => 'No data available',
                'last_review_date' => null
            ];

            // Get upcoming schedule (placeholder - implement when scheduling system is added)
            $upcomingShifts = [];
            
            // Get announcements (placeholder - implement when announcement system is added)
            $announcements = [];

            // Calculate tenure information
            $hireDate = $employee->created_at; // Using created_at as hire date for now
            $tenureDays = $hireDate ? $today->diffInDays($hireDate) : 0;
            
            $anniversaryInfo = null;
            if ($hireDate) {
                $anniversaryInfo = [
                    'hire_date' => $hireDate->toISOString(),
                    'tenure_days' => $tenureDays,
                    'tenure_formatted' => $this->formatTenure($tenureDays),
                    'next_anniversary' => $this->getNextAnniversary($hireDate, $today),
                    'upcoming_anniversary' => $this->hasUpcomingAnniversary($hireDate, $today)
                ];
            }

            // Get customizable welcome message and replace {name} placeholder
            $welcomeMessageTemplate = SystemSetting::get(
                'employee_welcome_message',
                "Welcome {name}! Here's your personalized dashboard with training progress, anniversaries, and important updates."
            );
            $welcomeMessage = str_replace('{name}', $employee->full_name, $welcomeMessageTemplate);
            
            return $this->successResponse([
                'employee' => new EmployeeResource($employee),
                'dashboard_data' => [
                    'welcome_message' => $welcomeMessage,
                    'status' => $employee->status,
                    'stage' => $employee->stage,
                    'approved_at' => $employee->approved_at?->toISOString(),
                    'access_level' => 'full_dashboard',
                    'work_stats' => $workStats,
                    'training_stats' => $trainingStats,
                    'performance_stats' => $performanceStats,
                    'upcoming_shifts' => $upcomingShifts,
                    'announcements' => $announcements,
                    'anniversary_info' => $anniversaryInfo,
                    'overdue_training' => $overdueAssignments->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'module_title' => $assignment->trainingModule->title ?? 'Unknown Module',
                            'due_date' => $assignment->due_date->toISOString(),
                            'days_overdue' => today()->diffInDays($assignment->due_date)
                        ];
                    })->values()->toArray()
                ]
            ], 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to format tenure for display
     */
    private function formatTenure($days)
    {
        if ($days < 30) {
            return $days . ' day' . ($days !== 1 ? 's' : '');
        } else if ($days < 365) {
            $months = floor($days / 30);
            return $months . ' month' . ($months !== 1 ? 's' : '');
        } else {
            $years = floor($days / 365);
            $remainingMonths = floor(($days % 365) / 30);
            $result = $years . ' year' . ($years !== 1 ? 's' : '');
            if ($remainingMonths > 0) {
                $result .= ' ' . $remainingMonths . ' month' . ($remainingMonths !== 1 ? 's' : '');
            }
            return $result;
        }
    }

    /**
     * Helper method to get next anniversary
     */
    private function getNextAnniversary($hireDate, $today)
    {
        $tenureDays = $today->diffInDays($hireDate);
        $milestones = [30, 90, 180, 365, 730, 1095, 1460, 1825]; // days
        
        foreach ($milestones as $milestone) {
            if ($tenureDays < $milestone) {
                $targetDate = $hireDate->copy()->addDays($milestone);
                return [
                    'milestone_days' => $milestone,
                    'milestone_label' => $this->getMilestoneLabel($milestone),
                    'date' => $targetDate->toISOString(),
                    'days_remaining' => $milestone - $tenureDays
                ];
            }
        }
        
        return null;
    }

    /**
     * Helper method to check if employee has upcoming anniversary
     */
    private function hasUpcomingAnniversary($hireDate, $today)
    {
        $nextAnniversary = $this->getNextAnniversary($hireDate, $today);
        if (!$nextAnniversary) return false;
        
        return $nextAnniversary['days_remaining'] <= 7 && $nextAnniversary['days_remaining'] >= 0;
    }

    /**
     * Helper method to get milestone label
     */
    private function getMilestoneLabel($days)
    {
        $labels = [
            30 => '1 Month',
            90 => '3 Months', 
            180 => '6 Months',
            365 => '1 Year',
            730 => '2 Years',
            1095 => '3 Years',
            1460 => '4 Years',
            1825 => '5 Years'
        ];
        
        return $labels[$days] ?? 'Anniversary';
    }

    /**
     * Update employee personal information (only for approved employees)
     */
    public function updatePersonalInfo(EmployeePersonalInfoRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->updatePersonalInfo(
                $request->user()->id,
                $request->validated()
            );

            return $this->successResponse(
                new EmployeeResource($employee),
                'Personal information updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update personal information: ' . $e->getMessage());
        }
    }

    /**
     * Get onboarding pages for employee
     */
    public function getOnboardingPages(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            
            // Get all active and approved onboarding pages
            $pages = OnboardingPage::active()->approved()->ordered()->get();
            
            // Get employee's progress for each page with detailed information
            $progress = [];
            $details = [];
            foreach ($pages as $page) {
                $progressRecord = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                    ->where('onboarding_page_id', $page->id)
                    ->first();
                
                $status = $progressRecord ? $progressRecord->status : 'not_started';
                $progress[$page->id] = $status;
                
                $details[$page->id] = [
                    'status' => $status,
                    'completed_at' => $progressRecord ? $progressRecord->completed_at?->toISOString() : null,
                    'signature' => $progressRecord ? $progressRecord->signature : null,
                    'has_test' => $page->hasTest(),
                    'test_status' => $progressRecord ? $progressRecord->test_status : null,
                    'test_score' => $progressRecord ? $progressRecord->test_score : null,
                    'test_attempts' => $progressRecord ? $progressRecord->test_attempts : 0,
                ];
            }
            
            return $this->successResponse([
                'pages' => $pages,
                'progress' => $progress,
                'details' => $details,
                'summary' => $employee->getOnboardingPageProgress(),
                'personal_info_complete' => $employee->isPersonalInfoComplete()
            ], 'Onboarding pages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get onboarding pages: ' . $e->getMessage());
        }
    }
    
    /**
     * Complete an onboarding page
     */
    public function completeOnboardingPage(Request $request): JsonResponse
    {
        try {
            // Log the request data for debugging
            \Log::info('Onboarding page completion request', [
                'user_id' => $request->user()->id,
                'request_data' => $request->all()
            ]);
            
            $request->validate([
                'page_id' => 'required|exists:onboarding_pages,id',
                'signature' => 'required|string|max:255'
            ]);
            
            $employee = $request->user();
            $pageId = $request->page_id;
            $signature = $request->signature;
            
            \Log::info('Processing onboarding completion', [
                'employee_id' => $employee->id,
                'page_id' => $pageId,
                'signature_length' => strlen($signature)
            ]);
            
            // Verify the page exists, is active, and is approved
            $page = OnboardingPage::where('id', $pageId)->active()->approved()->firstOrFail();
            
            // Get or create progress record
            $progress = EmployeeOnboardingProgress::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'onboarding_page_id' => $pageId
                ],
                [
                    'status' => 'in_progress',
                    'signature' => $signature
                ]
            );
            
            // If page has a test, mark as in_progress and set test status to pending
            // If no test, mark as completed
            if ($page->hasTest()) {
                $progress->update([
                    'status' => 'in_progress',
                    'signature' => $signature,
                    'test_status' => 'pending',
                    'test_attempts' => 0
                ]);
            } else {
                $progress->update([
                    'status' => 'completed',
                    'signature' => $signature,
                    'completed_at' => now()
                ]);
            }
            
            \Log::info('Onboarding page completed successfully', [
                'employee_id' => $employee->id,
                'page_id' => $pageId,
                'progress_id' => $progress->id,
                'status' => $progress->status,
                'completed_at' => $progress->completed_at
            ]);
            
            $allPages = OnboardingPage::active()->approved()->get();
            $allPageIds = $allPages->pluck('id')->toArray();
            
            $completedCount = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                ->whereIn('onboarding_page_id', $allPageIds)
                ->where('status', 'completed')
                ->count();
            
            \Log::info('Checking onboarding completion', [
                'employee_id' => $employee->id,
                'total_active_pages' => $allPages->count(),
                'completed_count' => $completedCount,
                'all_completed' => $completedCount === $allPages->count()
            ]);
            
            if ($completedCount === $allPages->count() && $allPages->count() > 0) {
                try {
                    // Check if notification already exists for this employee's completion (check recent notifications)
                    $recentNotifications = TableNotification::where('type', TableNotification::TYPE_ONBOARDING_COMPLETE)
                        ->where('created_at', '>=', now()->subMinutes(5))
                        ->get();
                    
                    $existingNotification = false;
                    foreach ($recentNotifications as $notification) {
                        if (isset($notification->data['employee_id']) && $notification->data['employee_id'] == $employee->id) {
                            $existingNotification = true;
                            break;
                        }
                    }
                    
                    if (!$existingNotification) {
                        // Get all admins with onboarding notifications enabled (excluding expo role)
                        $enabledAdmins = Admin::where('onboarding_notifications_enabled', true)
                            ->where('role', '!=', 'expo')
                            ->get();
                        
                        // Create individual notification for each admin with notifications enabled
                        foreach ($enabledAdmins as $admin) {
                            $notification = TableNotification::create([
                                'type' => TableNotification::TYPE_ONBOARDING_COMPLETE,
                                'title' => 'All Onboarding Documents Completed',
                                'message' => $employee->full_name . ' has completed all ' . $allPages->count() . ' onboarding documents',
                                'order_number' => 'N/A', // Required field for notification table
                                'priority' => TableNotification::PRIORITY_MEDIUM,
                                'recipient_type' => TableNotification::RECIPIENT_ADMIN,
                                'recipient_id' => $admin->id, // Link notification to specific admin
                                'data' => [
                                    'employee_id' => $employee->id,
                                    'employee_name' => $employee->full_name,
                                    'total_documents' => $allPages->count(),
                                    'completed_at' => $progress->completed_at ? $progress->completed_at->toISOString() : now()->toISOString()
                                ],
                                'is_read' => false
                            ]);
                        }
                    }
                } catch (\Exception $notificationError) {
                    // Log notification creation error but don't fail the entire request
                    \Log::error('Failed to create onboarding completion notification', [
                        'employee_id' => $employee->id,
                        'error' => $notificationError->getMessage()
                    ]);
                }
            }
            
            // Get updated progress for all pages (only approved)
            $allProgress = [];
            $pages = OnboardingPage::active()->approved()->get();
            foreach ($pages as $p) {
                $progressRecord = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                    ->where('onboarding_page_id', $p->id)
                    ->first();
                $allProgress[$p->id] = $progressRecord ? $progressRecord->status : 'not_started';
            }
            
            // Refresh employee data
            $employee = $employee->fresh();
            
            return $this->successResponse([
                'progress' => $allProgress,
                'employee' => new EmployeeResource($employee),
                'completed_page' => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'completed_at' => $progress->completed_at ? $progress->completed_at->toISOString() : null,
                    'signature' => $progress->signature
                ]
            ], 'Onboarding page completed successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to complete onboarding page', [
                'user_id' => $request->user()?->id,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to complete onboarding page: ' . $e->getMessage());
        }
    }
    
    /**
     * Get onboarding progress for employee
     */
    public function getOnboardingProgress(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            
            // Get all progress records for this employee
            $progressRecords = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                ->with('onboardingPage')
                ->get();
            
            $progress = [];
            $completions = [];
            $signatures = [];
            
            foreach ($progressRecords as $record) {
                $pageId = $record->onboarding_page_id;
                $progress[$pageId] = $record->status;
                
                if ($record->completed_at) {
                    $completions[$pageId] = $record->completed_at->toISOString();
                }
                
                if ($record->signature) {
                    $signatures[$pageId] = $record->signature;
                }
            }
            
            return $this->successResponse([
                'progress' => $progress,
                'completions' => $completions,
                'signatures' => $signatures
            ], 'Onboarding progress retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get onboarding progress: ' . $e->getMessage());
        }
    }

    /**
     * Get test questions for an onboarding page
     */
    public function getOnboardingPageTest(Request $request, $pageId): JsonResponse
    {
        try {
            $employee = $request->user();
            
            $page = OnboardingPage::where('id', $pageId)
                ->active()
                ->approved()
                ->firstOrFail();
            
            if (!$page->hasTest()) {
                return $this->errorResponse('This onboarding page does not have a test');
            }
            
            // Get employee progress for this page
            $progress = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                ->where('onboarding_page_id', $pageId)
                ->first();
            
            // Only return test if the page is acknowledged (status is completed or in progress)
            if (!$progress || $progress->status === 'not_started') {
                return $this->errorResponse('You must acknowledge the document first');
            }
            
            // Get test questions without correct answers
            $questions = collect($page->getTestQuestions())->map(function($question) {
                return [
                    'id' => $question['id'],
                    'question' => $question['question'],
                    'options' => $question['options']
                ];
            });
            
            return $this->successResponse([
                'page_id' => $page->id,
                'page_title' => $page->title,
                'questions' => $questions,
                'passing_score' => $page->getPassingScore(),
                'test_attempts' => $progress->test_attempts ?? 0
            ], 'Test questions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get test questions: ' . $e->getMessage());
        }
    }

    /**
     * Submit test answers for an onboarding page
     */
    public function submitOnboardingPageTest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page_id' => 'required|exists:onboarding_pages,id',
                'answers' => 'required|array'
            ]);
            
            $employee = $request->user();
            $pageId = $request->page_id;
            $answers = $request->answers;
            
            $page = OnboardingPage::where('id', $pageId)
                ->active()
                ->approved()
                ->firstOrFail();
            
            if (!$page->hasTest()) {
                return $this->errorResponse('This onboarding page does not have a test');
            }
            
            // Get or create progress record
            $progress = EmployeeOnboardingProgress::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'onboarding_page_id' => $pageId
                ],
                [
                    'status' => 'in_progress',
                    'test_status' => 'pending',
                    'test_attempts' => 0
                ]
            );
            
            // Validate answers
            $result = $page->validateTestAnswers($answers);
            
            // Increment test attempts
            $progress->increment('test_attempts');
            $attemptNumber = $progress->test_attempts;
            
            // Save test result
            $testResult = \App\Models\OnboardingPageTestResult::create([
                'employee_id' => $employee->id,
                'onboarding_page_id' => $pageId,
                'attempt_number' => $attemptNumber,
                'score' => $result['score'],
                'answers' => $answers,
                'passed' => $result['passed'],
                'completed_at' => now()
            ]);
            
            // Update progress based on result
            if ($result['passed']) {
                $progress->update([
                    'status' => 'completed',
                    'test_status' => 'passed',
                    'test_score' => $result['score'],
                    'completed_at' => now()
                ]);
                
                // Check if all onboarding is now complete and send notification
                $allPages = OnboardingPage::active()->approved()->get();
                $allPageIds = $allPages->pluck('id')->toArray();
                
                $completedCount = EmployeeOnboardingProgress::where('employee_id', $employee->id)
                    ->whereIn('onboarding_page_id', $allPageIds)
                    ->where('status', 'completed')
                    ->count();
                
                if ($completedCount === $allPages->count() && $allPages->count() > 0) {
                    try {
                        // Check if notification already exists for this employee's completion
                        $recentNotifications = TableNotification::where('type', TableNotification::TYPE_ONBOARDING_COMPLETE)
                            ->where('created_at', '>=', now()->subMinutes(5))
                            ->get();
                        
                        $existingNotification = false;
                        foreach ($recentNotifications as $notification) {
                            if (isset($notification->data['employee_id']) && $notification->data['employee_id'] == $employee->id) {
                                $existingNotification = true;
                                break;
                            }
                        }
                        
                        if (!$existingNotification) {
                            // Get all admins with onboarding notifications enabled
                            $enabledAdmins = Admin::where('onboarding_notifications_enabled', true)
                                ->where('role', '!=', 'expo')
                                ->get();
                            
                            // Create individual notification for each admin with notifications enabled
                            foreach ($enabledAdmins as $admin) {
                                TableNotification::create([
                                    'type' => TableNotification::TYPE_ONBOARDING_COMPLETE,
                                    'title' => 'All Onboarding Documents Completed',
                                    'message' => $employee->full_name . ' has completed all ' . $allPages->count() . ' onboarding documents',
                                    'order_number' => 'N/A',
                                    'priority' => TableNotification::PRIORITY_MEDIUM,
                                    'recipient_type' => TableNotification::RECIPIENT_ADMIN,
                                    'recipient_id' => $admin->id,
                                    'data' => [
                                        'employee_id' => $employee->id,
                                        'employee_name' => $employee->full_name,
                                        'total_documents' => $allPages->count(),
                                        'completed_at' => now()->toISOString()
                                    ],
                                    'is_read' => false
                                ]);
                            }
                        }
                    } catch (\Exception $notificationError) {
                        \Log::error('Failed to create onboarding completion notification after test', [
                            'employee_id' => $employee->id,
                            'error' => $notificationError->getMessage()
                        ]);
                    }
                }
            } else {
                $progress->update([
                    'test_status' => 'failed',
                    'test_score' => $result['score']
                ]);
            }
            
            return $this->successResponse([
                'passed' => $result['passed'],
                'score' => $result['score'],
                'correct_answers' => $result['correct'],
                'total_questions' => $result['total'],
                'passing_score' => $page->getPassingScore(),
                'attempt_number' => $attemptNumber,
                'can_retake' => !$result['passed']
            ], $result['passed'] ? 'Test passed successfully!' : 'Test failed. You can try again.');
        } catch (\Exception $e) {
            \Log::error('Failed to submit test', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to submit test: ' . $e->getMessage());
        }
    }

    // =================== TRAINING MODULE METHODS ===================
    
    /**
     * Get training modules for authenticated employee
     */
    public function getTrainingModules(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            $result = $this->employeeTrainingService->getAssignedTrainingModules($employee->id);
            
            return $this->successResponse($result, 'Training modules retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training modules: ' . $e->getMessage());
        }
    }
    
    /**
     * Unlock training module via QR code
     */
    public function unlockTrainingModule(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'qr_code' => 'required|string'
            ]);
            
            $employee = $request->user();
            $result = $this->employeeTrainingService->unlockTrainingViaQR($employee->id, $request->qr_code);
            
            return $this->successResponse($result, $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to unlock training module: ' . $e->getMessage());
        }
    }
    
    /**
     * Get training module content (only if unlocked)
     */
    public function getTrainingContent(Request $request, $moduleId): JsonResponse
    {
        try {
            $employee = $request->user();
            $result = $this->employeeTrainingService->getModuleContent($employee->id, $moduleId);
            
            return $this->successResponse($result, 'Training module content retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve training module content: ' . $e->getMessage());
        }
    }
    
    /**
     * Mark training module as completed
     */
    public function completeTraining(Request $request, $moduleId): JsonResponse
    {
        try {
            $request->validate([
                'completion_data' => 'array',
                'completion_data.video_watched' => 'boolean',
                'completion_data.content_reviewed' => 'boolean',
                'completion_data.time_spent_minutes' => 'integer|min:0',
                'completion_data.quiz_score' => 'nullable|integer|min:0|max:100'
            ]);
            
            $employee = $request->user();
            $completionData = $request->input('completion_data', []);
            
            $result = $this->employeeTrainingService->completeTraining($employee->id, $moduleId, $completionData);
            
            return $this->successResponse($result, $result['message']);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to complete training module: ' . $e->getMessage());
        }
    }
    
    
    /**
     * Check employee status (for polling from frontend)
     */
    public function checkStatus(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            
            // Return current status
            return $this->successResponse([
                'status' => $employee->status,
                'stage' => $employee->stage,
                'is_active' => $employee->isActive(),
                'is_paused' => $employee->isPaused(),
                'is_inactive' => $employee->isInactive(),
                'can_access_dashboard' => $employee->canAccessDashboard(),
            ], 'Status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check status: ' . $e->getMessage());
        }
    }
    
    /**
     * Logout employee
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Logout successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }
}
