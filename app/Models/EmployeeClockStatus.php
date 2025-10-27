<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeClockStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'is_currently_clocked',
        'current_time_entry_id',
        'last_clock_in',
        'last_clock_out',
    ];

    protected $casts = [
        'is_currently_clocked' => 'boolean',
    ];

    /**
     * Get the employee that owns the clock status
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the current active time entry
     */
    public function currentTimeEntry()
    {
        return $this->belongsTo(TimeEntry::class, 'current_time_entry_id');
    }

    /**
     * Scope to get currently clocked in employees
     */
    public function scopeClockedIn($query)
    {
        return $query->where('is_currently_clocked', true);
    }
}
