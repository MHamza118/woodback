<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department',
        'location',
        'description',
        'shifts_data',
        'created_by'
    ];

    protected $casts = [
        'shifts_data' => 'json',
    ];

    /**
     * Get the admin who created this template
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
