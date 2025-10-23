<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReport extends Model
{
    use HasFactory;

    protected $table = 'performance_reports';

    protected $fillable = [
        'employee_id',
        'type',
        'review_period',
        'punctuality',
        'work_quality',
        'teamwork',
        'communication',
        'customer_service',
        'initiative',
        'overall_rating',
        'strengths',
        'areas_for_improvement',
        'goals',
        'notes',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'punctuality' => 'decimal:1',
        'work_quality' => 'decimal:1',
        'teamwork' => 'decimal:1',
        'communication' => 'decimal:1',
        'customer_service' => 'decimal:1',
        'initiative' => 'decimal:1',
        'overall_rating' => 'decimal:1',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Report belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Report created by admin
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get ratings as an associative array
     */
    public function getRatingsAttribute()
    {
        return [
            'punctuality' => (float) $this->punctuality,
            'workQuality' => (float) $this->work_quality,
            'teamwork' => (float) $this->teamwork,
            'communication' => (float) $this->communication,
            'customerService' => (float) $this->customer_service,
            'initiative' => (float) $this->initiative,
        ];
    }

    /**
     * Calculate and update overall rating based on individual ratings
     */
    public function calculateOverallRating()
    {
        $ratings = [
            $this->punctuality,
            $this->work_quality,
            $this->teamwork,
            $this->communication,
            $this->customer_service,
            $this->initiative,
        ];

        $average = array_sum($ratings) / count($ratings);
        $this->overall_rating = round($average, 1);
        
        return $this->overall_rating;
    }

    /**
     * Boot method to auto-calculate overall rating
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($report) {
            $report->calculateOverallRating();
        });
    }
}

