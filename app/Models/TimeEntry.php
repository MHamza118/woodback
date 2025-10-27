<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in_time',
        'clock_out_time',
        'total_hours',
        'location_info',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'location_info' => 'array',
        'total_hours' => 'decimal:2',
    ];

    /**
     * Get the employee that owns the time entry
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the clock status that references this time entry
     */
    public function clockStatus()
    {
        return $this->hasOne(EmployeeClockStatus::class, 'current_time_entry_id');
    }

    /**
     * Calculate total hours worked
     */
    public function calculateTotalHours()
    {
        if (!$this->clock_in_time || !$this->clock_out_time) {
            return null;
        }

        $clockIn = \Carbon\Carbon::parse($this->clock_in_time);
        $clockOut = \Carbon\Carbon::parse($this->clock_out_time);

        // Handle overnight shifts
        if ($clockOut < $clockIn) {
            $clockOut->addDay();
        }

        return round($clockIn->diffInMinutes($clockOut) / 60, 2);
    }

    /**
     * Get formatted total hours as HH:MM:SS
     */
    public function getFormattedTotalHoursAttribute()
    {
        if (!$this->total_hours) {
            return '00:00:00';
        }

        $totalMinutes = round($this->total_hours * 60);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $seconds = 0; // Since we're working with hourly data, seconds are always 0

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Scope to get entries for a specific employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to get entries within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get currently active (not clocked out) entries
     */
    public function scopeActive($query)
    {
        return $query->whereNull('clock_out_time');
    }

    /**
     * Scope to get today's entries
     */
    public function scopeToday($query)
    {
        return $query->where('date', now()->toDateString());
    }
}
