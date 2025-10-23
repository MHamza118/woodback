<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceInteraction extends Model
{
    use HasFactory;

    protected $table = 'performance_interactions';

    protected $fillable = [
        'employee_id',
        'type',
        'subject',
        'message',
        'priority',
        'follow_up_required',
        'follow_up_date',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'follow_up_required' => 'boolean',
        'follow_up_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Interaction belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Interaction created by admin
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

