<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnboardingPageTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'onboarding_page_id',
        'attempt_number',
        'score',
        'answers',
        'passed',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'passed' => 'boolean',
        'score' => 'integer',
        'attempt_number' => 'integer',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function onboardingPage()
    {
        return $this->belongsTo(OnboardingPage::class);
    }

    public function hasPassed()
    {
        return $this->passed;
    }

    public function getAttemptNumber()
    {
        return $this->attempt_number;
    }
}
