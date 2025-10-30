<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeOnboardingProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'onboarding_page_id',
        'status',
        'signature',
        'completed_at',
        'test_status',
        'test_score',
        'test_attempts',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'test_score' => 'integer',
        'test_attempts' => 'integer',
    ];

    /**
     * Get the employee that owns the progress.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the onboarding page for this progress.
     */
    public function onboardingPage()
    {
        return $this->belongsTo(OnboardingPage::class);
    }

    /**
     * Scope a query to only include completed progress.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include in progress.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to only include not started.
     */
    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
    }

    /**
     * Check if this progress is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Mark this progress as completed.
     */
    public function markAsCompleted($signature = null)
    {
        $this->update([
            'status' => 'completed',
            'signature' => $signature,
            'completed_at' => now(),
        ]);
    }

    public function requiresTest()
    {
        return $this->test_status === 'pending';
    }

    public function isTestPassed()
    {
        return $this->test_status === 'passed';
    }

    public function canRetakeTest()
    {
        return $this->test_status === 'failed' || $this->test_status === 'pending';
    }
}
