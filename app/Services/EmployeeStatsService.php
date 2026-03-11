<?php

namespace App\Services;

use App\Models\TimeEntry;
use Carbon\Carbon;

class EmployeeStatsService
{
    /**
     * Get work statistics for an employee
     * 
     * @param int $employeeId
     * @return array
     */
    public function getWorkStats(int $employeeId): array
    {
        $today = today();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();
        $startOfLastWeek = $startOfWeek->copy()->subWeek();
        $endOfLastWeek = $startOfLastWeek->copy()->endOfWeek();

        // Hours this week - sum of actual worked hours (clocked in/out)
        $hoursThisWeek = $this->calculateWorkedHours($employeeId, $startOfWeek, $endOfWeek);
        
        // Shifts completed this month - count of completed time entries (with clock_out_time)
        $shiftsCompletedThisMonth = $this->countCompletedShifts($employeeId, $startOfMonth, $today);
        
        // Hours change from last week
        $hoursLastWeek = $this->calculateWorkedHours($employeeId, $startOfLastWeek, $endOfLastWeek);
        $hoursChange = $hoursThisWeek - $hoursLastWeek;

        return [
            'hours_this_week' => $hoursThisWeek,
            'shifts_completed_this_month' => $shiftsCompletedThisMonth,
            'hours_change_from_last_week' => $hoursChange
        ];
    }

    /**
     * Calculate total worked hours for a date range
     * Only counts time entries where employee has clocked out (completed shifts)
     * 
     * @param int $employeeId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    private function calculateWorkedHours(int $employeeId, Carbon $startDate, Carbon $endDate): float
    {
        $timeEntries = TimeEntry::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_out_time')
            ->get();

        $totalHours = 0;
        foreach ($timeEntries as $entry) {
            if ($entry->total_hours) {
                $totalHours += $entry->total_hours;
            }
        }

        return round($totalHours, 2);
    }

    /**
     * Count completed shifts for a date range
     * Only counts time entries where employee has clocked out
     * 
     * @param int $employeeId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int
     */
    private function countCompletedShifts(int $employeeId, Carbon $startDate, Carbon $endDate): int
    {
        return TimeEntry::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_out_time')
            ->count();
    }
}
