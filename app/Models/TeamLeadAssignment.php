<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamLeadAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'assigned_date',
        'department',
        'assigned_by_admin_id',
    ];

    protected $casts = [
        'assigned_date' => 'date',
    ];

    /**
     * Get the employee associated with this team lead assignment
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the admin who made this assignment
     */
    public function assignedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'assigned_by_admin_id');
    }

    /**
     * Scope to get team leads for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('assigned_date', $date);
    }

    /**
     * Scope to get team leads for a specific department
     */
    public function scopeForDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to get team leads for a specific date and department
     */
    public function scopeForDateAndDepartment($query, $date, $department)
    {
        return $query->whereDate('assigned_date', $date)->where('department', $department);
    }
}
