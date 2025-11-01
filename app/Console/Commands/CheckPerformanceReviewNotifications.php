<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PerformanceReviewSchedule;
use App\Models\TableNotification;
use App\Models\Employee;
use Carbon\Carbon;

class CheckPerformanceReviewNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:check-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue and due soon performance reviews and create notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking performance review schedules...');

        // Get all pending (incomplete) reviews
        $pendingReviews = PerformanceReviewSchedule::with('employee')
            ->where('completed', false)
            ->get();

        $overdueCount = 0;
        $dueSoonCount = 0;

        foreach ($pendingReviews as $schedule) {
            $urgencyStatus = $schedule->urgency_status;
            
            if ($urgencyStatus === 'overdue') {
                $this->createNotificationIfNeeded($schedule, 'overdue');
                $overdueCount++;
            } elseif ($urgencyStatus === 'due_soon') {
                $this->createNotificationIfNeeded($schedule, 'due_soon');
                $dueSoonCount++;
            }
        }

        $this->info("Found {$overdueCount} overdue and {$dueSoonCount} due soon reviews.");
        $this->info('Performance review notifications check completed.');

        return Command::SUCCESS;
    }

    /**
     * Create notification if it doesn't already exist
     */
    protected function createNotificationIfNeeded($schedule, $urgencyType)
    {
        $employee = $schedule->employee;
        
        if (!$employee) {
            return;
        }

        $personalInfo = $employee->personal_info ?? [];
        $firstName = $personalInfo['firstName'] ?? $employee->first_name ?? '';
        $lastName = $personalInfo['lastName'] ?? $employee->last_name ?? '';
        $employeeName = trim($firstName . ' ' . $lastName) ?: $employee->email;

        $reviewTypeLabel = $this->getReviewTypeLabel($schedule->review_type);
        $daysOverdue = abs($schedule->days_overdue);

        // Check if notification already exists for this schedule
        $notificationType = $urgencyType === 'overdue' 
            ? TableNotification::TYPE_PERFORMANCE_REVIEW_OVERDUE 
            : TableNotification::TYPE_PERFORMANCE_REVIEW_DUE_SOON;

        $existingNotification = TableNotification::where('type', $notificationType)
            ->where('recipient_type', TableNotification::RECIPIENT_ADMIN)
            ->where('data->schedule_id', $schedule->id)
            ->where('is_read', false)
            ->where('created_at', '>=', now()->subDays(1)) // Only check last 24 hours
            ->first();

        if ($existingNotification) {
            // Notification already exists
            return;
        }

        // Create new notification
        $title = $urgencyType === 'overdue'
            ? "Performance Review Overdue"
            : "Performance Review Due Soon";

        $message = $urgencyType === 'overdue'
            ? "{$employeeName} - {$reviewTypeLabel} is overdue by {$daysOverdue} days"
            : "{$employeeName} - {$reviewTypeLabel} is due in {$daysOverdue} days";

        TableNotification::create([
            'type' => $notificationType,
            'title' => $title,
            'message' => $message,
            'recipient_type' => TableNotification::RECIPIENT_ADMIN,
            'recipient_id' => null, // Visible to all admins
            'priority' => $urgencyType === 'overdue' ? TableNotification::PRIORITY_HIGH : TableNotification::PRIORITY_MEDIUM,
            'data' => [
                'schedule_id' => $schedule->id,
                'employee_id' => $employee->id,
                'employee_name' => $employeeName,
                'review_type' => $schedule->review_type,
                'review_type_label' => $reviewTypeLabel,
                'scheduled_date' => $schedule->scheduled_date->toDateString(),
                'days_overdue' => $schedule->days_overdue,
                'urgency_status' => $urgencyType,
            ],
            'is_read' => false,
        ]);

        $this->line("Created {$urgencyType} notification for {$employeeName} - {$reviewTypeLabel}");
    }

    /**
     * Get readable review type label
     */
    protected function getReviewTypeLabel($reviewType): string
    {
        return match($reviewType) {
            'one_week' => '1 Week Review',
            'one_month' => '1 Month Review',
            'quarterly' => 'Quarterly Review',
            default => ucfirst(str_replace('_', ' ', $reviewType)),
        };
    }
}
