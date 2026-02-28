<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_start_timestamp',
        'department',
        'shifts_data',
        'action',
        'created_by'
    ];

    protected $casts = [
        'shifts_data' => 'json',
    ];

    /**
     * Get the admin who created this snapshot
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
