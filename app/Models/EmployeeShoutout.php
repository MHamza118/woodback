<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeShoutout extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'recognized_by',
        'category',
        'message',
        'likes',
    ];

    protected $casts = [
        'likes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Shoutout belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Shoutout recognized by admin/user
     */
    public function recognizedBy()
    {
        return $this->belongsTo(Admin::class, 'recognized_by');
    }

    /**
     * Scope: Recent shoutouts
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, $category)
    {
        if ($category && $category !== 'all') {
            return $query->where('category', $category);
        }
        return $query;
    }

    /**
     * Scope: Filter by employee
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
