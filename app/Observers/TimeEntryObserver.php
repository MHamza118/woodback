<?php

namespace App\Observers;

use App\Models\TimeEntry;
use App\Models\Employee;
use App\Models\PerformanceReviewSchedule;

class TimeEntryObserver
{
    /**
     * Handle the TimeEntry "created" event.
     */
    public function created(TimeEntry $timeEntry): void
    {
        // Check if this is the employee's first time entry
        $employee = Employee::find($timeEntry->employee_id);
        
        if (!$employee) {
            return;
        }
        
        // If employee already has a first_shift_date, skip
        if ($employee->first_shift_date) {
            return;
        }
        
        // Check if this is truly the first time entry
        $previousEntries = TimeEntry::where('employee_id', $timeEntry->employee_id)
                                    ->where('id', '!=', $timeEntry->id)
                                    ->exists();
        
        if ($previousEntries) {
            return;
        }
        
        // Set first_shift_date on employee
        $employee->first_shift_date = $timeEntry->date;
        $employee->save();
        
        // Create initial performance review schedules
        PerformanceReviewSchedule::createInitialSchedules(
            $employee->id,
            $timeEntry->date
        );
    }
}
