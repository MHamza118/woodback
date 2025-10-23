<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'badge_type_id',
        'awarded_by',
        'reason',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Badge belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Badge belongs to badge type
     */
    public function badgeType()
    {
        return $this->belongsTo(BadgeType::class);
    }

    /**
     * Relationship: Badge awarded by admin/user
     */
    public function awardedBy()
    {
        return $this->belongsTo(Admin::class, 'awarded_by');
    }

    /**
     * Scope: Filter by employee
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: Recent badges
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('awarded_at', 'desc')->limit($limit);
    }
}
