<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API V1 Routes
Route::prefix('v1')->group(function () {
    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('customer/register', [App\Http\Controllers\Api\V1\CustomerController::class, 'register']);
        Route::post('customer/login', [App\Http\Controllers\Api\V1\AuthController::class, 'customerLogin']);

        // Employee authentication routes
        Route::post('employee/register', [App\Http\Controllers\Api\V1\EmployeeController::class, 'register']);
        Route::post('employee/login', [App\Http\Controllers\Api\V1\EmployeeController::class, 'employeeLogin']);

        // Admin authentication routes
        Route::post('admin/login', [App\Http\Controllers\Api\V1\AdminController::class, 'login']);

        // Password reset routes (public)
        Route::post('password/forgot', [App\Http\Controllers\Api\V1\PasswordResetController::class, 'sendResetLink']);
        Route::post('password/verify-token', [App\Http\Controllers\Api\V1\PasswordResetController::class, 'verifyToken']);
        Route::post('password/reset', [App\Http\Controllers\Api\V1\PasswordResetController::class, 'resetPassword']);

        // Logout route (protected)
        Route::middleware('auth:sanctum')->post('logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    });

    // Public OneSignal config route (no auth required)
    Route::get('notifications/config', [App\Http\Controllers\Api\V1\OneSignalController::class, 'getConfig']);

    // Public locations endpoint (for customer signup)
    Route::get('locations', [App\Http\Controllers\Api\V1\AdminController::class, 'getLocations']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // User profile routes
        Route::prefix('user')->group(function () {
            Route::get('profile', [App\Http\Controllers\Api\V1\UserController::class, 'profile']);
            Route::put('profile', [App\Http\Controllers\Api\V1\UserController::class, 'updateProfile']);
            Route::post('logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        });

        // Customer routes (for authenticated customers)
        Route::prefix('customer')->group(function () {
            Route::get('profile', [App\Http\Controllers\Api\V1\CustomerController::class, 'profile']);
            Route::put('profile', [App\Http\Controllers\Api\V1\CustomerController::class, 'updateProfile']);
            Route::get('dashboard', [App\Http\Controllers\Api\V1\CustomerController::class, 'dashboard']);
            Route::get('announcements', [App\Http\Controllers\Api\V1\CustomerController::class, 'announcements']);
            Route::post('announcements/{id}/dismiss', [App\Http\Controllers\Api\V1\CustomerController::class, 'dismissAnnouncement']);
            Route::post('rewards/redeem', [App\Http\Controllers\Api\V1\CustomerController::class, 'redeemReward']);
            Route::put('preferences', [App\Http\Controllers\Api\V1\CustomerController::class, 'updatePreferences']);
            Route::post('profile-image', [App\Http\Controllers\Api\V1\CustomerController::class, 'uploadProfileImage']);
            Route::get('profile-image-url', [App\Http\Controllers\Api\V1\CustomerController::class, 'getProfileImageUrl']);
            Route::delete('profile-image', [App\Http\Controllers\Api\V1\CustomerController::class, 'deleteProfileImage']);
            Route::put('home-location', [App\Http\Controllers\Api\V1\CustomerController::class, 'updateHomeLocation']);
        });

// Employee routes (for authenticated employees)
Route::prefix('employee')->middleware(['auth:sanctum'])->group(function () {
    // Locations endpoint for employees (read-only access to all locations) - no status check needed
    Route::get('locations', [App\Http\Controllers\Api\V1\AdminController::class, 'getLocations']);
    
    // Routes that require employee status check
    Route::middleware(['check.employee.status'])->group(function () {
        Route::get('profile', [App\Http\Controllers\Api\V1\EmployeeController::class, 'profile']);
        Route::get('check-status', [App\Http\Controllers\Api\V1\EmployeeController::class, 'checkStatus']);
        Route::put('personal-info', [App\Http\Controllers\Api\V1\EmployeeController::class, 'updatePersonalInfo']);
        Route::post('personal-info', [App\Http\Controllers\Api\V1\EmployeeController::class, 'updatePersonalInfo']); // For file uploads
        Route::delete('documents/{documentId}', [App\Http\Controllers\Api\V1\EmployeeController::class, 'deleteDocument']);
        Route::get('qr', [App\Http\Controllers\Api\V1\EmployeeController::class, 'generateQR']);
        Route::post('location', [App\Http\Controllers\Api\V1\EmployeeController::class, 'submitLocation']);
        Route::get('questionnaire', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getQuestionnaire']);
        Route::post('questionnaire', [App\Http\Controllers\Api\V1\EmployeeController::class, 'submitQuestionnaire']);

        // Get interviewers for questionnaire (accessible during onboarding)
        Route::get('interviewers', [App\Http\Controllers\Api\V1\AdminController::class, 'getInterviewers']);
        Route::get('welcome', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getWelcomePage']);
        Route::get('confirmation', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getConfirmationPage']);
        Route::get('dashboard', [App\Http\Controllers\Api\V1\EmployeeController::class, 'dashboard']);

        // Employee announcements routes
        Route::get('announcements', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'getActive']);
        Route::post('announcements/{id}/mark-as-viewed', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'markAsViewed']);

    // Employee FAQ routes
    Route::prefix('faqs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\FaqController::class, 'index']);
        Route::get('/categories', [App\Http\Controllers\Api\V1\FaqController::class, 'categories']);
    });

            // Onboarding routes
            Route::get('onboarding-pages', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getOnboardingPages']);
            Route::post('onboarding-pages/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeOnboardingPage']);
            Route::get('onboarding-progress', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getOnboardingProgress']);
            Route::get('onboarding-pages/{pageId}/test', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getOnboardingPageTest']);
            Route::post('onboarding-pages/test/submit', [App\Http\Controllers\Api\V1\EmployeeController::class, 'submitOnboardingPageTest']);

            // Training routes
            Route::prefix('training')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingModules']);
                Route::post('/unlock', [App\Http\Controllers\Api\V1\EmployeeController::class, 'unlockTrainingModule']);
                Route::get('/{moduleId}/content', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingContent']);
                Route::post('/{moduleId}/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeTraining']);
            });

            // Alias routes for frontend compatibility
            Route::get('training-modules', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingModules']);

            // Employee schedule routes
            Route::get('schedule/shifts', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getEmployeeShifts']);
            Route::post('training-modules/unlock', [App\Http\Controllers\Api\V1\EmployeeController::class, 'unlockTrainingModule']);
            Route::get('training-modules/{moduleId}/content', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getTrainingContent']);
            Route::post('training-modules/{moduleId}/complete', [App\Http\Controllers\Api\V1\EmployeeController::class, 'completeTraining']);

            Route::post('logout', [App\Http\Controllers\Api\V1\EmployeeController::class, 'logout']);

            // Profile image routes
            Route::post('profile-image', [App\Http\Controllers\Api\V1\EmployeeController::class, 'uploadProfileImage']);
            Route::delete('profile-image', [App\Http\Controllers\Api\V1\EmployeeController::class, 'deleteProfileImage']);
            Route::get('profile-image', [App\Http\Controllers\Api\V1\EmployeeController::class, 'getProfileImage']);

        // Department structure for employees (read-only)
        Route::get('department-structure', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'index']);

        // Time Tracking routes
        Route::post('clock-in', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'clockIn']);
        Route::post('clock-out', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'clockOut']);
        Route::get('clock-status', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getClockStatus']);
        Route::get('time-entries', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getTimeEntries']);
        Route::get('current-time-entry', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getCurrentTimeEntry']);
    });
});

// Admin dashboard and profile routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\Api\V1\AdminController::class, 'dashboard']);
    Route::get('profile', [App\Http\Controllers\Api\V1\AdminController::class, 'profile']);
    Route::post('profile-image', [App\Http\Controllers\Api\V1\AdminController::class, 'uploadProfileImage']);
    Route::delete('profile-image', [App\Http\Controllers\Api\V1\AdminController::class, 'deleteProfileImage']);
    Route::get('profile-image', [App\Http\Controllers\Api\V1\AdminController::class, 'getProfileImage']);
    Route::post('logout', [App\Http\Controllers\Api\V1\AdminController::class, 'logout']);

    // Notification preferences (Expo only)
    Route::get('notification-preferences', [App\Http\Controllers\Api\V1\AdminController::class, 'getNotificationPreferences']);
    Route::put('notification-preferences', [App\Http\Controllers\Api\V1\AdminController::class, 'toggleNotifications']);

    // Onboarding notification preferences
    Route::put('toggle-onboarding-notifications', [App\Http\Controllers\Api\V1\AdminController::class, 'toggleOnboardingNotifications']);

    // Welcome message settings
    Route::get('welcome-message', [App\Http\Controllers\Api\V1\AdminController::class, 'getWelcomeMessage']);
    Route::put('welcome-message', [App\Http\Controllers\Api\V1\AdminController::class, 'updateWelcomeMessage']);

    // Admin FAQ management routes
    Route::prefix('faqs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\FaqController::class, 'adminIndex']);
        Route::post('/', [App\Http\Controllers\Api\V1\FaqController::class, 'store']);
        Route::get('/categories', [App\Http\Controllers\Api\V1\FaqController::class, 'categories']);
        Route::put('/order', [App\Http\Controllers\Api\V1\FaqController::class, 'updateOrder']);
        Route::get('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\V1\FaqController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\V1\FaqController::class, 'toggleActive']);
    });

            // Admin user management routes (role-based permissions)
            Route::prefix('users')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getAdminUsers']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createAdminUser']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateAdminUser']);
                Route::put('/{id}/permissions', [App\Http\Controllers\Api\V1\AdminController::class, 'updateAdminPermissions']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deactivateAdminUser']);
            });

            // Roles and permissions reference
            Route::get('roles-permissions', [App\Http\Controllers\Api\V1\AdminController::class, 'getRolesAndPermissions']);

            // Location management routes
            Route::prefix('locations')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getLocations']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createLocation']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateLocation']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deleteLocation']);
            });

            // Admin customer management routes
            Route::get('customers', [App\Http\Controllers\Api\V1\CustomerController::class, 'index']);
            Route::post('customers', [App\Http\Controllers\Api\V1\AdminController::class, 'createCustomer']);
            Route::get('customers/statistics', [App\Http\Controllers\Api\V1\CustomerController::class, 'statistics']);
            Route::get('customers/{id}', [App\Http\Controllers\Api\V1\CustomerController::class, 'show']);
            Route::put('customers/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateCustomer']);
            Route::delete('customers/{id}', [App\Http\Controllers\Api\V1\CustomerController::class, 'destroy']);

            // Admin employee management routes
            Route::prefix('employees')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createEmployee']);
                Route::get('/pending', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'pendingApproval']);
                Route::get('/statistics', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'statistics']);
                Route::post('/{id}/approve', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'approve']);
                Route::post('/{id}/reject', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'reject']);
                Route::put('/{id}/personal-info', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'updatePersonalInfo']);
                Route::put('/{id}/assignments', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'updateRoleAssignments']);
                // File management
                Route::post('/{id}/files', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'uploadFile']);
                // Lifecycle actions
                Route::post('/{id}/pause', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'pause']);
                Route::post('/{id}/resume', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'resume']);
                Route::post('/{id}/deactivate', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'deactivate']);
                Route::post('/{id}/activate', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'activate']);
                // Assign interviewer
                Route::post('/{id}/assign-interviewer', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'assignInterviewer']);
                // Toggle interview access
                Route::post('/{id}/toggle-interview-access', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'toggleInterviewAccess']);
                // Delete employee - must come before GET /{id} to avoid conflicts
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'destroy']);
                // Get single employee - must come last among /{id} routes
                Route::get('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'show']);
            });

            // File management routes (separate from employee prefix for clarity)
            Route::get('/files/{id}/download', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'downloadFile']);
            Route::delete('/files/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'deleteFile']);

            // Department structure management routes
            Route::prefix('department-structure')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'index']);
                Route::post('/area', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'updateArea']);
                Route::delete('/area', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'deleteArea']);
                Route::post('/role', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'addRole']);
                Route::delete('/role', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'removeRole']);
                Route::post('/role', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'addRole']);
                Route::delete('/role', [App\Http\Controllers\Api\V1\DepartmentStructureController::class, 'removeRole']);
            });

            // Admin questionnaire management routes
            Route::prefix('questionnaires')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'questionnaires']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'createQuestionnaire']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'showQuestionnaire']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'updateQuestionnaire']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'deleteQuestionnaire']);
                Route::post('/{id}/toggle', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'toggleQuestionnaireStatus']);
            });

            // Admin onboarding page management routes
            Route::prefix('onboarding-pages')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\AdminController::class, 'getOnboardingPages']);
                Route::get('/pending-count', [App\Http\Controllers\Api\V1\AdminController::class, 'getPendingOnboardingCount']);
                Route::post('/', [App\Http\Controllers\Api\V1\AdminController::class, 'createOnboardingPage']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'updateOnboardingPage']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\AdminController::class, 'deleteOnboardingPage']);
                Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\V1\AdminController::class, 'toggleOnboardingPageStatus']);
                Route::post('/{id}/approve', [App\Http\Controllers\Api\V1\AdminController::class, 'approveOnboardingPage']);
                Route::post('/{id}/reject', [App\Http\Controllers\Api\V1\AdminController::class, 'rejectOnboardingPage']);
                Route::get('/{id}/test-results', [App\Http\Controllers\Api\V1\AdminController::class, 'getOnboardingPageTestResults']);
            });

            // Admin training module management routes
            Route::prefix('training-modules')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'store']);
                Route::get('/categories', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getCategories']);
                Route::get('/analytics', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getAnalytics']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'show']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'update']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'destroy']);

                // Assignment management
                Route::post('/{id}/assign', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'assignToEmployees']);
                Route::get('/{id}/assignments', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'getAssignments']);
                Route::post('/{id}/reset-progress', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'resetProgress']);
                Route::post('/{id}/remove-assignments', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'removeAssignments']);

                // QR Code management
                Route::get('/{id}/qr-code', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'generateQrCode']);
                Route::post('/{id}/regenerate-qr', [App\Http\Controllers\Api\V1\TrainingModuleController::class, 'regenerateQrCode']);
            });

            // Admin ticket management routes
            Route::prefix('tickets')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TicketController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\TicketController::class, 'storeAdminTicket']);
                Route::get('/statistics', [App\Http\Controllers\Api\V1\TicketController::class, 'statistics']);
                Route::get('/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'show']);
                Route::put('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'update']);
                Route::post('/{id}/archive', [App\Http\Controllers\Api\V1\TicketController::class, 'archive']);
                Route::post('/{id}/unarchive', [App\Http\Controllers\Api\V1\TicketController::class, 'unarchive']);
                Route::delete('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'destroy']);
                Route::post('/{id}/responses', [App\Http\Controllers\Api\V1\TicketController::class, 'addResponse']);
            });

            // Admin time-off management routes
            Route::prefix('time-off')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'adminIndex']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TimeOffController::class, 'show']);
                Route::post('/{id}/status', [App\Http\Controllers\Api\V1\TimeOffController::class, 'updateStatus']);
            });

            // Admin availability management routes
            Route::prefix('availability')->group(function () {
                Route::get('/reasons', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getReasons']);
                Route::post('/reasons', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'storeReason']);
                Route::put('/reasons/{id}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'updateReason']);
                Route::delete('/reasons/{id}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'deleteReason']);
                
                Route::get('/requests', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getRequests']);
                Route::get('/requests/new', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getNewRequests']);
                Route::post('/requests', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'storeRequest']);
                Route::put('/requests/{id}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'updateRequest']);
                Route::post('/requests/{id}/status', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'updateRequestStatus']);
                Route::delete('/requests/{id}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'deleteRequest']);

                // Effective availability endpoints (temporary overrides recurring)
                Route::get('/effective/{employeeId}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getEffectiveAvailability']);
                Route::get('/effective-range/{employeeId}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getEffectiveAvailabilityRange']);
                Route::get('/summary/{employeeId}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getAvailabilitySummary']);
                Route::post('/check/{employeeId}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'checkAvailability']);
                Route::get('/employee/{employeeId}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getEmployeeAvailability']);
            });

            // Admin table tracking management routes
            Route::prefix('table-tracking')->group(function () {
                Route::get('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAllOrders']);
                Route::post('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'addOrder']);
                Route::put('/orders/{orderNumber}/status', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'updateOrderStatus']);
                Route::put('/orders/{orderNumber}/delivered', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'markDelivered']);
                Route::delete('/orders/{orderNumber}', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'deleteOrder']);

                Route::get('/mappings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAllMappings']);
                Route::post('/manual-mapping', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'submitManualMapping']);
                Route::put('/mappings/{orderNumber}/clear', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'clearMapping']);

                Route::get('/analytics', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getAnalytics']);

                Route::get('/notifications', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'getAdminNotifications']);
                Route::put('/notifications/{notificationId}/read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markAdminNotificationAsRead']);
                Route::delete('/notifications/{notificationId}', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'deleteAdminNotification']);
            });

            // Admin communication routes
            Route::prefix('communication')->group(function () {
                Route::get('/conversations', [App\Http\Controllers\Api\V1\ConversationController::class, 'index']);
                Route::post('/conversations/group', [App\Http\Controllers\Api\V1\ConversationController::class, 'createGroup']);
                Route::post('/conversations/private', [App\Http\Controllers\Api\V1\ConversationController::class, 'getOrCreatePrivateConversation']);
                Route::post('/conversations/{id}/participants', [App\Http\Controllers\Api\V1\ConversationController::class, 'addParticipants']);
                Route::post('/conversations/{id}/read', [App\Http\Controllers\Api\V1\ConversationController::class, 'markAsRead']);
                Route::get('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'index']);
                Route::post('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'store']);
                Route::post('/messages/group', [App\Http\Controllers\Api\V1\MessageController::class, 'sendGroupMessage']);
                Route::post('/messages/private', [App\Http\Controllers\Api\V1\MessageController::class, 'sendPrivateMessage']);

                // Announcements management
                Route::prefix('announcements')->group(function () {
                    Route::get('/', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'index']);
                    Route::post('/', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'store']);
                    Route::get('/{id}', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'show']);
                    Route::put('/{id}', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'update']);
                    Route::delete('/{id}', [App\Http\Controllers\Api\V1\AnnouncementController::class, 'destroy']);
                });

                // Employee messages monitoring
                Route::prefix('employee-messages')->group(function () {
                    Route::get('/', [App\Http\Controllers\Api\V1\EmployeeMessagesController::class, 'getEmployeeConversations']);
                    Route::get('/{conversationId}/messages', [App\Http\Controllers\Api\V1\EmployeeMessagesController::class, 'getConversationMessages']);
                });
            });

            // Admin feed routes
            Route::prefix('feed')->group(function () {
                Route::get('/posts', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'getPosts']);
                Route::post('/posts', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'createPost']);
                Route::delete('/posts/{post}', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'deletePost']);
                Route::post('/posts/{post}/like', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'likePost']);
                Route::delete('/posts/{post}/like', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'unlikePost']);
                Route::post('/posts/{post}/comments', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'addComment']);
                Route::delete('/comments/{comment}', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'deleteComment']);
                Route::get('/posts/{post}/comments', [App\Http\Controllers\Api\V1\AdminFeedController::class, 'getComments']);
            });

            // Admin employee recognition routes
            Route::prefix('recognition')->group(function () {
                Route::get('/stats', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getStats']);
                Route::get('/activity', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getRecentActivity']);
                Route::get('/top-performers', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getTopPerformers']);

                // Shoutouts
                Route::get('/shoutouts', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getAllShoutouts']);
                Route::post('/shoutouts', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'createShoutout']);
                Route::delete('/shoutouts/{id}', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'deleteShoutout']);

                // Rewards
                Route::get('/rewards', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getAllRewards']);
                Route::post('/rewards', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'giveReward']);

                // Reward Types
                Route::get('/reward-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getRewardTypes']);
                Route::post('/reward-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'createRewardType']);
                Route::put('/reward-types/{id}', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'updateRewardType']);
                Route::delete('/reward-types/{id}', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'deleteRewardType']);

                // Badges
                Route::get('/badges', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getAllBadges']);
                Route::post('/badges', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'awardBadge']);

                // Badge Types
                Route::get('/badge-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getBadgeTypes']);
                Route::post('/badge-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'createBadgeType']);
                Route::put('/badge-types/{id}', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'updateBadgeType']);
                Route::delete('/badge-types/{id}', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'deleteBadgeType']);
            });

            // Admin time tracking routes
            Route::prefix('time-tracking')->group(function () {
                Route::get('/live-roster', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getLiveRoster']);
                Route::get('/time-entries', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getAllTimeEntries']);
                Route::get('/time-entries/{employeeId}', [App\Http\Controllers\Api\V1\TimeTrackingController::class, 'getEmployeeTimeEntries']);
            });

            // Admin performance management routes
            Route::prefix('performance')->group(function () {
                // Overview and employee listing
                Route::get('/employees', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'index']);

                // Performance Reports
                Route::get('/reports', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getAllReports']);
                Route::post('/reports', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'createReport']);
                Route::get('/reports/{id}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getEmployeeReports']);
                Route::put('/reports/{id}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'updateReport']);
                Route::delete('/reports/{id}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'deleteReport']);

                // Performance Interactions/Feedback
                Route::get('/interactions', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getAllInteractions']);
                Route::post('/interactions', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'createInteraction']);
                Route::get('/interactions/{id}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getEmployeeInteractions']);
                Route::delete('/interactions/{id}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'deleteInteraction']);

                // Performance Review Schedules
                Route::get('/pending-reviews', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getPendingReviews']);
                Route::get('/review-notifications', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getReviewNotificationCount']);
                Route::get('/schedules/{employeeId}', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getEmployeeSchedules']);
            });

            // Admin schedule/events routes
            Route::prefix('schedule')->group(function () {
                // Departments and employees for schedule management
                Route::get('/departments', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getDepartments']);
                Route::get('/roles', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getRolesForDepartment']);
                Route::get('/employees-by-department', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getEmployeesByDepartmentGrouped']);
                Route::get('/employees/{department}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getEmployeesByDepartment']);
                Route::get('/employee/{employeeId}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getEmployeeDetails']);
                
                // Fill from template endpoint
                Route::post('/fill-from-template', [App\Http\Controllers\Api\V1\ScheduleController::class, 'fillFromTemplate']);
                
                // Publish schedule
                Route::post('/publish', [App\Http\Controllers\Api\V1\ScheduleController::class, 'publishSchedule']);
                Route::post('/clear', [App\Http\Controllers\Api\V1\ScheduleController::class, 'clearSchedule']);
                Route::post('/import', [App\Http\Controllers\Api\V1\ScheduleController::class, 'importSchedule']);
                
                // Shift CRUD operations
                Route::get('/shifts', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getShiftsForWeek']);
                Route::post('/shifts', [App\Http\Controllers\Api\V1\ScheduleController::class, 'createShift']);
                Route::put('/shifts/{shiftId}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'updateShift']);
                Route::delete('/shifts/{shiftId}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'deleteShift']);

                // Team lead management
                Route::get('/team-leads', [App\Http\Controllers\Api\V1\TeamLeadController::class, 'getTeamLeads']);
                Route::post('/team-leads', [App\Http\Controllers\Api\V1\TeamLeadController::class, 'assignTeamLead']);
                Route::delete('/team-leads/{assignmentId}', [App\Http\Controllers\Api\V1\TeamLeadController::class, 'removeTeamLead']);

                // Template management routes
                Route::post('/templates/save', [App\Http\Controllers\Api\V1\ScheduleController::class, 'saveAsTemplate']);
                Route::get('/templates', [App\Http\Controllers\Api\V1\ScheduleController::class, 'getTemplates']);
                Route::put('/templates/{templateId}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'updateTemplate']);
                Route::delete('/templates/{templateId}', [App\Http\Controllers\Api\V1\ScheduleController::class, 'deleteTemplate']);
                Route::post('/templates/{templateId}/duplicate', [App\Http\Controllers\Api\V1\ScheduleController::class, 'duplicateTemplate']);
                Route::post('/templates/fill', [App\Http\Controllers\Api\V1\ScheduleController::class, 'fillFromSavedTemplate']);

                Route::prefix('events')->group(function () {
                    // Date-specific routes must come before {id} routes
                    Route::get('/date/single', [App\Http\Controllers\Api\V1\EventController::class, 'getForDate']);
                    Route::get('/date/range', [App\Http\Controllers\Api\V1\EventController::class, 'getForDateRange']);
                    Route::get('/upcoming', [App\Http\Controllers\Api\V1\EventController::class, 'getUpcoming']);
                    // Generic routes
                    Route::get('/', [App\Http\Controllers\Api\V1\EventController::class, 'index']);
                    Route::post('/', [App\Http\Controllers\Api\V1\EventController::class, 'store']);
                    Route::get('/{id}', [App\Http\Controllers\Api\V1\EventController::class, 'show']);
                    Route::put('/{id}', [App\Http\Controllers\Api\V1\EventController::class, 'update']);
                    Route::delete('/{id}', [App\Http\Controllers\Api\V1\EventController::class, 'destroy']);
                });
            });
        });

        // Employee ticket routes
        Route::prefix('employee')->group(function () {
            Route::prefix('tickets')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TicketController::class, 'employeeTickets']);
                Route::post('/', [App\Http\Controllers\Api\V1\TicketController::class, 'store']);
                Route::get('/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TicketController::class, 'show']);
                Route::post('/{id}/responses', [App\Http\Controllers\Api\V1\TicketController::class, 'employeeAddResponse']);
            });

            // Employee time-off routes
            Route::prefix('time-off')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'employeeIndex']);
                Route::post('/', [App\Http\Controllers\Api\V1\TimeOffController::class, 'store']);
                Route::get('/{id}', [App\Http\Controllers\Api\V1\TimeOffController::class, 'show']);
                Route::post('/{id}/cancel', [App\Http\Controllers\Api\V1\TimeOffController::class, 'cancel']);
            });

            // Employee availability routes
            Route::prefix('availability')->group(function () {
                Route::get('/reasons', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getReasons']);
                Route::get('/requests', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getRequests']);
                Route::get('/requests/updated', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getUpdatedRequests']);
                Route::post('/requests', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'storeRequest']);
                Route::delete('/requests/{id}', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'deleteRequest']);

                // Effective availability endpoints (temporary overrides recurring)
                Route::get('/effective', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getEffectiveAvailability']);
                Route::get('/effective-range', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getEffectiveAvailabilityRange']);
                Route::get('/summary', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'getAvailabilitySummary']);
                Route::post('/check', [App\Http\Controllers\Api\V1\AvailabilityController::class, 'checkAvailability']);
            });

            // Employee notifications routes
            Route::get('/notifications', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'getEmployeeNotifications']);
            Route::put('/notifications/{notificationId}/read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markEmployeeNotificationAsRead']);
            Route::put('/notifications/mark-all-read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markAllEmployeeNotificationsAsRead']);
            Route::delete('/notifications/{notificationId}', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'deleteEmployeeNotification']);

            // Employee table tracking routes
            Route::prefix('table-tracking')->group(function () {
                Route::get('/orders', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getEmployeeOrders']);
                Route::get('/mappings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getEmployeeMappings']);

                // Employee can update order status and mark delivered
                Route::put('/orders/{orderNumber}/status', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'updateEmployeeOrderStatus']);
                Route::put('/orders/{orderNumber}/delivered', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'markEmployeeDelivered']);

                Route::get('/notifications', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'getEmployeeNotifications']);
                Route::put('/notifications/{notificationId}/read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markEmployeeNotificationAsRead']);
                Route::put('/notifications/mark-all-read', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'markAllEmployeeNotificationsAsRead']);
                Route::delete('/notifications/{notificationId}', [App\Http\Controllers\Api\V1\TableNotificationController::class, 'deleteEmployeeNotification']);
            });

            // Employee communication routes
            Route::prefix('communication')->group(function () {
                Route::get('/conversations', [App\Http\Controllers\Api\V1\ConversationController::class, 'index']);
                Route::post('/conversations/private', [App\Http\Controllers\Api\V1\ConversationController::class, 'getOrCreatePrivateConversation']);
                Route::post('/conversations/{id}/read', [App\Http\Controllers\Api\V1\ConversationController::class, 'markAsRead']);
                Route::get('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'index']);
                Route::post('/conversations/{id}/messages', [App\Http\Controllers\Api\V1\MessageController::class, 'store']);
                Route::post('/messages/private', [App\Http\Controllers\Api\V1\MessageController::class, 'sendPrivateMessage']);
                Route::get('/employees', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getEmployeesList']);
            });

            // Employee feed routes
            Route::prefix('feed')->group(function () {
                Route::get('/posts', [App\Http\Controllers\Api\V1\FeedController::class, 'getPosts']);
                Route::post('/posts', [App\Http\Controllers\Api\V1\FeedController::class, 'createPost']);
                Route::delete('/posts/{post}', [App\Http\Controllers\Api\V1\FeedController::class, 'deletePost']);
                Route::post('/posts/{post}/like', [App\Http\Controllers\Api\V1\FeedController::class, 'likePost']);
                Route::delete('/posts/{post}/like', [App\Http\Controllers\Api\V1\FeedController::class, 'unlikePost']);
                Route::post('/posts/{post}/comments', [App\Http\Controllers\Api\V1\FeedController::class, 'addComment']);
                Route::delete('/comments/{comment}', [App\Http\Controllers\Api\V1\FeedController::class, 'deleteComment']);
                Route::get('/posts/{post}/comments', [App\Http\Controllers\Api\V1\FeedController::class, 'getComments']);
            });

            // Employee recognition routes
            Route::prefix('recognition')->group(function () {
                Route::get('/my-shoutouts', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getMyShoutouts']);
                Route::get('/my-rewards', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getMyRewards']);
                Route::get('/my-badges', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getMyBadges']);
                Route::get('/my-performance', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getMyPerformance']);
                Route::post('/rewards/{id}/redeem', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'redeemReward']);

                // Public reference data
                Route::get('/reward-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getRewardTypes']);
                Route::get('/badge-types', [App\Http\Controllers\Api\V1\EmployeeRecognitionController::class, 'getBadgeTypes']);
            });

            // Employee performance routes
            Route::prefix('performance')->group(function () {
                Route::get('/dashboard', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getMyPerformanceDashboard']);
                Route::get('/reports', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getMyReports']);
                Route::get('/interactions', [App\Http\Controllers\Api\V1\PerformanceManagementController::class, 'getMyInteractions']);
            });

            // Employee schedule/events routes (read-only)
            Route::prefix('schedule')->group(function () {
                Route::prefix('events')->group(function () {
                    // Date-specific routes must come before {id} routes
                    Route::get('/date/single', [App\Http\Controllers\Api\V1\EventController::class, 'getForDate']);
                    Route::get('/date/range', [App\Http\Controllers\Api\V1\EventController::class, 'getForDateRange']);
                    Route::get('/upcoming', [App\Http\Controllers\Api\V1\EventController::class, 'getUpcoming']);
                    // Generic routes
                    Route::get('/', [App\Http\Controllers\Api\V1\EventController::class, 'index']);
                    Route::get('/{id}', [App\Http\Controllers\Api\V1\EventController::class, 'show']);
                });
            });
        });

        // OneSignal push notification routes (authenticated)
        Route::prefix('notifications')->group(function () {
            Route::post('/register', [App\Http\Controllers\Api\V1\OneSignalController::class, 'registerUser']);
            Route::put('/update-tags', [App\Http\Controllers\Api\V1\OneSignalController::class, 'updateUserTags']);
            
            // Admin only routes
            Route::middleware(['admin'])->group(function () {
                Route::post('/send-test', [App\Http\Controllers\Api\V1\OneSignalController::class, 'sendTestNotification']);
            });
        });

        // File download routes
        Route::get('files/{id}/download', [App\Http\Controllers\Api\V1\AdminEmployeeController::class, 'downloadFile']);

        // Example CRUD routes
        Route::apiResource('posts', App\Http\Controllers\Api\V1\PostController::class);
    });

    // Public routes (no auth required)
    Route::get('tickets/configuration', [App\Http\Controllers\Api\V1\TicketController::class, 'configuration']);

    // Table tracking public routes (no auth required)
    Route::post('table-tracking/submit', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'submitTableMapping']);
    Route::get('table-tracking/settings', [App\Http\Controllers\Api\V1\TableTrackingController::class, 'getTableSettings']);
});