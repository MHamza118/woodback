<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'department',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
        'role',
        'shift_type',
        'requirements',
        'week_start',
        'week_end',
        'status',
        'published',
        'published_at',
        'published_by'
    ];

    protected $casts = [
        'date' => 'date',
        'week_start' => 'date',
        'week_end' => 'date',
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Get the employee associated with this schedule
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Scope to get schedules for a specific week
     */
    public function scopeForWeek($query, $weekStart, $weekEnd)
    {
        return $query->whereBetween('date', [$weekStart, $weekEnd]);
    }

    /**
     * Scope to get schedules for a specific department
     */
    public function scopeForDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to get schedules for a specific employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to get active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
