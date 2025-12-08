<?php

namespace App\Traits;

use App\Services\OneSignalService;

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

        // Send to admins only
        return $oneSignal->sendToTags(['role' => 'owner'], $title, $message, $data, config('app.url') . '/admin/dashboard#employees');
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

        // Send to admins only
        return $oneSignal->sendToTags(['role' => 'owner'], $title, $message, $data, config('app.url') . '/admin/dashboard#tickets');
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

        // Send to admins and managers
        return $oneSignal->sendToTags(['role' => 'admin'], $title, $message, $data, config('app.url') . '/admin/dashboard#performance');
    }

    /**
     * Send push notification for time off requests
     */
    public function sendTimeOffRequestNotification($employee, $request)
    {
        $oneSignal = new OneSignalService();
        
        $title = 'New Time Off Request';
        $message = "{$employee->first_name} {$employee->last_name} has requested time off from {$request->start_date} to {$request->end_date}";
        
        $data = [
            'type' => 'time_off_request',
            'request_id' => $request->id,
            'employee_id' => $employee->id,
            'employee_name' => "{$employee->first_name} {$employee->last_name}",
            'url' => '/admin/dashboard#time-off'
        ];

        // Send to admins and managers
        return $oneSignal->sendToTags(['role' => 'admin'], $title, $message, $data, config('app.url') . '/admin/dashboard#time-off');
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
}