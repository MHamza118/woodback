<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PerformanceReviewSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'first_shift_date',
        'review_type',
        'scheduled_date',
        'completed',
        'completed_at',
        'performance_report_id',
    ];

    protected $casts = [
        'first_shift_date' => 'date',
        'scheduled_date' => 'date',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Schedule belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Schedule linked to performance report when completed
     */
    public function performanceReport()
    {
        return $this->belongsTo(PerformanceReport::class);
    }

    /**
     * Scope to get pending (incomplete) reviews
     */
    public function scopePending($query)
    {
        return $query->where('completed', false);
    }

    /**
     * Scope to get completed reviews
     */
    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    /**
     * Scope to get overdue reviews
     */
    public function scopeOverdue($query)
    {
        return $query->where('completed', false)
                     ->where('scheduled_date', '<', now()->toDateString());
    }

    /**
     * Scope to get reviews due soon (within specified days)
     */
    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('completed', false)
                     ->whereBetween('scheduled_date', [
                         now()->toDateString(),
                         now()->addDays($days)->toDateString()
                     ]);
    }

    /**
     * Check if review is overdue
     */
    public function isOverdue()
    {
        return !$this->completed && $this->scheduled_date->isPast();
    }

    /**
     * Check if review is due soon (within 7 days)
     */
    public function isDueSoon($days = 7)
    {
        return !$this->completed && 
               $this->scheduled_date->isFuture() && 
               $this->scheduled_date->diffInDays(now()) <= $days;
    }

    /**
     * Get days overdue (negative if future)
     */
    public function getDaysOverdueAttribute()
    {
        if ($this->completed) {
            return 0;
        }
        
        return now()->diffInDays($this->scheduled_date, false);
    }

    /**
     * Get urgency status
     */
    public function getUrgencyStatusAttribute()
    {
        if ($this->completed) {
            return 'completed';
        }
        
        if ($this->isOverdue()) {
            return 'overdue';
        }
        
        if ($this->isDueSoon()) {
            return 'due_soon';
        }
        
        return 'scheduled';
    }

    /**
     * Mark schedule as completed
     */
    public function markCompleted($performanceReportId = null)
    {
        $this->completed = true;
        $this->completed_at = now();
        
        if ($performanceReportId) {
            $this->performance_report_id = $performanceReportId;
        }
        
        $this->save();
        
        // If this is a quarterly review, schedule the next one
        if ($this->review_type === 'quarterly') {
            $this->scheduleNextQuarterly();
        }
    }

    /**
     * Schedule next quarterly review (90 days from this one)
     */
    protected function scheduleNextQuarterly()
    {
        $nextScheduledDate = $this->scheduled_date->copy()->addDays(90);
        
        // Check if next quarterly already exists
        $exists = self::where('employee_id', $this->employee_id)
                      ->where('review_type', 'quarterly')
                      ->where('scheduled_date', $nextScheduledDate)
                      ->exists();
        
        if (!$exists) {
            self::create([
                'employee_id' => $this->employee_id,
                'first_shift_date' => $this->first_shift_date,
                'review_type' => 'quarterly',
                'scheduled_date' => $nextScheduledDate,
                'completed' => false,
            ]);
        }
    }

    /**
     * Create initial review schedules for an employee's first shift
     */
    public static function createInitialSchedules($employeeId, $firstShiftDate)
    {
        $firstShiftDate = Carbon::parse($firstShiftDate);
        
        $schedules = [
            [
                'employee_id' => $employeeId,
                'first_shift_date' => $firstShiftDate,
                'review_type' => 'one_week',
                'scheduled_date' => $firstShiftDate->copy()->addDays(7),
                'completed' => false,
            ],
            [
                'employee_id' => $employeeId,
                'first_shift_date' => $firstShiftDate,
                'review_type' => 'one_month',
                'scheduled_date' => $firstShiftDate->copy()->addDays(30),
                'completed' => false,
            ],
            [
                'employee_id' => $employeeId,
                'first_shift_date' => $firstShiftDate,
                'review_type' => 'quarterly',
                'scheduled_date' => $firstShiftDate->copy()->addDays(90),
                'completed' => false,
            ],
        ];
        
        foreach ($schedules as $schedule) {
            // Use updateOrCreate to prevent duplicates
            self::updateOrCreate(
                [
                    'employee_id' => $schedule['employee_id'],
                    'review_type' => $schedule['review_type'],
                    'scheduled_date' => $schedule['scheduled_date'],
                ],
                $schedule
            );
        }
    }
}
