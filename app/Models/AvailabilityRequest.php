<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'status',
        'effective_from',
        'effective_to',
        'availability_data',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected $casts = [
        'availability_data' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
