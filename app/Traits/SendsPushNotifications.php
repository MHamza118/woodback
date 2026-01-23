<?php

namespace App\Traits;

use App\Services\OneSignalService;
use App\Models\TimeOffRequest;

trait SendsPushNotifications
{
    /**
     * Send push notification for new employee signup
     */
    public function sendNewEmployeeSignupNotification($employee)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Employee Signup';
        $message = "{$employee->first_name} {$employee->last_name} has signed up and is awaiting approval";
        
        $data = [
            'type' => 'employee_signup',
            'employee_id' => $employee->id,
            'employee_name' => "{$employee->first_name} {$employee->last_name}",
            'url' => '/admin/dashboard#employees'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#employees');
    }

    /**
     * Send push notification for new ticket
     */
    public function sendNewTicketNotification($ticket)
    {
        $oneSignal = new OneSignalService();
        
        // Get employee name
        $employeeName = $ticket->employee ? $ticket->employee->name : 'Employee';
        
        $title = 'New Support Ticket';
        $message = "New ticket from {$employeeName}: {$ticket->title}";
        
        $data = [
            'type' => 'new_ticket',
            'ticket_id' => $ticket->id,
            'employee_name' => $employeeName,
            'ticket_title' => $ticket->title,
            'url' => '/admin/dashboard#tickets'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#tickets');
    }

    /**
     * Send push notification for ticket response to employee
     */
    public function sendTicketResponseToEmployee($ticket, $employeeId, $message)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Response from Management';
        $notificationMessage = 'You received a response on your ticket "' . $ticket->title . '"';
        
        $data = [
            'type' => 'ticket_response',
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'url' => '/employee/dashboard#tickets'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employeeId, $title, $notificationMessage, $data, config('app.url') . '/employee/dashboard#tickets');
    }

    /**
     * Send push notification for employee ticket response to admins
     */
    public function sendEmployeeTicketResponseToAdmins($ticket, $employeeName)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Employee Response';
        $message = $employeeName . ' responded to ticket: ' . $ticket->title;
        
        $data = [
            'type' => 'ticket_response',
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'employee_name' => $employeeName,
            'url' => '/admin/dashboard#tickets'
        ];

        // Send to all admin roles
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#tickets');
    }

    /**
     * Send push notification for order updates (for expo users)
     */
    public function sendOrderUpdateNotification($order, $type = 'new_order')
    {
        $oneSignal = new OneSignalService();
        
        $titles = [
            'new_order' => 'New Order',
            'order_updated' => 'Order Updated',
            'order_ready' => 'Order Ready',
            'order_delivered' => 'Order Delivered',
            'table_changed' => 'Table Changed'
        ];

        $messages = [
            'new_order' => "New order #{$order->id} for table {$order->table_number}",
            'order_updated' => "Order #{$order->id} has been updated",
            'order_ready' => "Order #{$order->id} is ready for pickup",
            'order_delivered' => "Order #{$order->id} has been delivered",
            'table_changed' => "Order #{$order->id} moved to table {$order->table_number}"
        ];
        
        $title = $titles[$type] ?? 'Order Update';
        $message = $messages[$type] ?? "Order #{$order->id} has been updated";
        
        $data = [
            'type' => $type,
            'order_id' => $order->id,
            'table_number' => $order->table_number,
            'url' => '/expo/dashboard'
        ];

        // Send to expo users only
        return $oneSignal->sendToTags(['role' => 'expo'], $title, $message, $data, config('app.url') . '/expo/dashboard');
    }

    /**
     * Send push notification for training assignments
     */
    public function sendTrainingAssignedNotification($employee, $training)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Training Assigned';
        $message = "You have been assigned training: {$training->title}";
        
        $data = [
            'type' => 'training_assigned',
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'url' => '/employee/training'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employee->id, $title, $message, $data, config('app.url') . '/employee/training');
    }

    /**
     * Send push notification for performance review reminders
     */
    public function sendPerformanceReviewNotification($employee, $type = 'due_soon')
    {
        $oneSignal = new OneSignalService();
        
        $titles = [
            'due_soon' => 'Performance Review Due Soon',
            'overdue' => 'Performance Review Overdue'
        ];

        $messages = [
            'due_soon' => "Performance review for {$employee->first_name} {$employee->last_name} is due soon",
            'overdue' => "Performance review for {$employee->first_name} {$employee->last_name} is overdue"
        ];
        
        $title = $titles[$type] ?? 'Performance Review Reminder';
        $message = $messages[$type] ?? "Performance review reminder for {$employee->first_name} {$employee->last_name}";
        
        $data = [
            'type' => "performance_review_{$type}",
            'employee_id' => $employee->id,
            'employee_name' => "{$employee->first_name} {$employee->last_name}",
            'url' => '/admin/dashboard#performance'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#performance');
    }

    /**
     * Send push notification when admin creates performance report for employee
     */
    public function sendPerformanceReportCreatedNotification($employee, $reportType, $overallRating)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Performance Review';
        $message = "Your {$reportType} has been completed. Overall rating: {$overallRating}/5";
        
        $data = [
            'type' => 'performance_report_created',
            'employee_id' => $employee->id,
            'report_type' => $reportType,
            'overall_rating' => $overallRating,
            'url' => '/employee/dashboard#performance'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employee->id, $title, $message, $data, config('app.url') . '/employee/dashboard#performance');
    }

    /**
     * Send push notification when admin gives feedback/interaction to employee
     */
    public function sendPerformanceFeedbackNotification($employee, $feedbackType, $subject, $priority)
    {
        $oneSignal = new OneSignalService();
        
        // Customize title based on feedback type
        $titles = [
            'recognition' => 'Recognition from Management',
            'coaching' => 'Coaching Feedback',
            'correction' => 'Performance Feedback',
            'development' => 'Development Opportunity',
            'general' => 'New Feedback'
        ];
        
        $title = $titles[$feedbackType] ?? 'New Feedback';
        $message = "You received {$feedbackType} feedback: {$subject}";
        
        $data = [
            'type' => 'performance_feedback',
            'employee_id' => $employee->id,
            'feedback_type' => $feedbackType,
            'subject' => $subject,
            'priority' => $priority,
            'url' => '/employee/dashboard#performance'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employee->id, $title, $message, $data, config('app.url') . '/employee/dashboard#performance');
    }

    /**
     * Send push notification for time off requests
     */
    public function sendTimeOffRequestNotification($employee, $timeOffRequest)
    {
        $oneSignal = new OneSignalService();
        
        $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        if (empty($employeeName)) {
            $employeeName = $employee->email;
        }

        $startDate = $timeOffRequest->start_date->format('M d, Y');
        $endDate = $timeOffRequest->end_date->format('M d, Y');
        $dateRange = $startDate === $endDate ? $startDate : "{$startDate} - {$endDate}";
        
        $typeLabel = TimeOffRequest::TYPES[$timeOffRequest->type] ?? $timeOffRequest->type;
        
        $title = 'New Time Off Request';
        $message = "{$employeeName} requested {$typeLabel} for {$dateRange}";
        
        $data = [
            'type' => 'time_off_request',
            'request_id' => $timeOffRequest->id,
            'employee_id' => $employee->id,
            'employee_name' => $employeeName,
            'time_off_type' => $timeOffRequest->type,
            'start_date' => $timeOffRequest->start_date->toDateString(),
            'end_date' => $timeOffRequest->end_date->toDateString(),
            'url' => '/admin/dashboard#time-off'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#time-off');
    }

    /**
     * Send push notification when admin responds to time off request
     */
    public function sendTimeOffResponseNotification($employee, $timeOffRequest, $status)
    {
        $oneSignal = new OneSignalService();
        
        $startDate = $timeOffRequest->start_date->format('M d, Y');
        $endDate = $timeOffRequest->end_date->format('M d, Y');
        $dateRange = $startDate === $endDate ? $startDate : "{$startDate} - {$endDate}";
        
        $typeLabel = TimeOffRequest::TYPES[$timeOffRequest->type] ?? $timeOffRequest->type;
        
        $titles = [
            'approved' => 'Time Off Request Approved',
            'rejected' => 'Time Off Request Rejected'
        ];
        
        $messages = [
            'approved' => "Your {$typeLabel} request for {$dateRange} has been approved",
            'rejected' => "Your {$typeLabel} request for {$dateRange} has been rejected"
        ];
        
        $title = $titles[$status] ?? 'Time Off Request Updated';
        $message = $messages[$status] ?? "Your time off request status has been updated to {$status}";
        
        $data = [
            'type' => 'time_off_response',
            'request_id' => $timeOffRequest->id,
            'status' => $status,
            'time_off_type' => $timeOffRequest->type,
            'start_date' => $timeOffRequest->start_date->toDateString(),
            'end_date' => $timeOffRequest->end_date->toDateString(),
            'url' => '/employee/dashboard#time-off'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employee->id, $title, $message, $data, config('app.url') . '/employee/dashboard#time-off');
    }

    /**
     * Send push notification for role assignments
     */
    public function sendRoleAssignmentNotification($employee, $assignments)
    {
        $oneSignal = new OneSignalService();
        
        $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        if (empty($employeeName)) {
            $employeeName = $employee->email;
        }

        // Build department names
        $departmentNames = [];
        foreach ($assignments['departments'] as $deptId) {
            $departmentNames[] = $deptId === 'FOH' ? 'Front of House' : 'Back of House';
        }
        $departmentText = implode(' and ', $departmentNames);

        // Count roles
        $roleCount = count($assignments['roles']);
        $roleText = $roleCount === 1 ? '1 role' : "{$roleCount} roles";

        $title = 'Role Assignment Updated';
        $message = "You have been assigned {$roleText} in {$departmentText}. Check your dashboard for details.";
        
        $data = [
            'type' => 'role_assignment',
            'employee_id' => $employee->id,
            'departments' => $assignments['departments'],
            'roles' => $assignments['roles'],
            'role_count' => $roleCount,
            'url' => '/employee/dashboard#roles'
        ];

        // Create database notification
        \App\Models\TableNotification::create([
            'type' => \App\Models\TableNotification::TYPE_ROLE_ASSIGNMENT,
            'title' => $title,
            'message' => $message,
            'recipient_type' => \App\Models\TableNotification::RECIPIENT_EMPLOYEE,
            'recipient_id' => $employee->id,
            'priority' => \App\Models\TableNotification::PRIORITY_MEDIUM,
            'data' => $data,
            'is_read' => false
        ]);

        // Send push notification to specific employee
        return $oneSignal->sendToEmployee($employee->id, $title, $message, $data, config('app.url') . '/employee/dashboard#roles');
    }

    /**
     * Send custom push notification
     */
    public function sendCustomPushNotification($title, $message, $recipients = 'all', $data = [], $url = null)
    {
        $oneSignal = new OneSignalService();
        
        switch ($recipients) {
            case 'all':
                return $oneSignal->sendToAll($title, $message, $data, $url);
            case 'admins':
                return $oneSignal->sendToTags(['role' => 'owner'], $title, $message, $data, $url);
            case 'employees':
                return $oneSignal->sendToTags(['role' => 'employee'], $title, $message, $data, $url);
            case 'expo':
                return $oneSignal->sendToTags(['role' => 'expo'], $title, $message, $data, $url);
            default:
                if (is_array($recipients)) {
                    return $oneSignal->sendToUsers($recipients, $title, $message, $data, $url);
                }
                return ['success' => false, 'error' => 'Invalid recipients'];
        }
    }
    /**
     * Send push notification for new availability request
     */
    public function sendNewAvailabilityRequestNotification($employee, $availabilityRequest)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Availability Request';
        $message = "{$employee->first_name} {$employee->last_name} submitted a new availability request effective from {$availabilityRequest->effective_from}";
        
        $data = [
            'type' => 'new_availability_request',
            'request_id' => $availabilityRequest->id,
            'employee_id' => $employee->id,
            'employee_name' => "{$employee->first_name} {$employee->last_name}",
            'url' => '/admin/dashboard#availability'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#availability');
    }

    /**
     * Send push notification for new chat message to admin
     */
    public function sendNewMessageNotificationToAdmin($senderName, $messageContent, $conversationId)
    {
        $oneSignal = new OneSignalService();
        
        // Truncate message if too long
        $displayMessage = strlen($messageContent) > 80 ? substr($messageContent, 0, 80) . '...' : $messageContent;
        
        $title = 'New Message from ' . $senderName;
        $message = $displayMessage;
        
        $data = [
            'type' => 'chat_message',
            'conversation_id' => $conversationId,
            'sender_name' => $senderName,
            'url' => '/admin/dashboard#communication'
        ];

        // Send to all admin roles (owner, admin, manager, hiring_manager)
        return $oneSignal->sendToMultipleRoles(['owner', 'admin', 'manager', 'hiring_manager'], $title, $message, $data, config('app.url') . '/admin/dashboard#communication');
    }

    /**
     * Send push notification for new chat message to employee
     */
    public function sendNewMessageNotificationToEmployee($employeeId, $senderName, $messageContent, $conversationId)
    {
        $oneSignal = new OneSignalService();
        
        // Truncate message if too long
        $displayMessage = strlen($messageContent) > 80 ? substr($messageContent, 0, 80) . '...' : $messageContent;
        
        $title = 'New Message from ' . $senderName;
        $message = $displayMessage;
        
        $data = [
            'type' => 'chat_message',
            'conversation_id' => $conversationId,
            'sender_name' => $senderName,
            'url' => '/employee/dashboard#communication'
        ];

        // Send to specific employee
        return $oneSignal->sendToEmployee($employeeId, $title, $message, $data, config('app.url') . '/employee/dashboard#communication');
    }
}