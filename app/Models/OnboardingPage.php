<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OnboardingPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'icon',
        'order',
        'active',
        'approval_status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'has_test',
        'test_questions',
        'passing_score',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'approved_at' => 'datetime',
        'has_test' => 'boolean',
        'test_questions' => 'array',
        'passing_score' => 'integer',
    ];

    /**
     * Get the employee progress records for this page.
     */
    public function employeeProgress()
    {
        return $this->hasMany(EmployeeOnboardingProgress::class);
    }

    /**
     * Get the admin who created this page.
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the admin who approved this page.
     */
    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Scope a query to only include active pages.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include approved pages.
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope a query to only include pending pages.
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Scope a query to order by page order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    /**
     * Get the completion status for a specific employee.
     */
    public function getCompletionStatusFor($employeeId)
    {
        $progress = $this->employeeProgress()
            ->where('employee_id', $employeeId)
            ->first();

        return $progress ? $progress->status : 'not_started';
    }

    /**
     * Check if this page is completed by a specific employee.
     */
    public function isCompletedBy($employeeId)
    {
        return $this->employeeProgress()
            ->where('employee_id', $employeeId)
            ->where('status', 'completed')
            ->exists();
    }

    public function hasTest()
    {
        return $this->has_test && !empty($this->test_questions);
    }

    public function getTestQuestions()
    {
        return $this->test_questions ?? [];
    }

    public function getPassingScore()
    {
        return $this->passing_score ?? 80;
    }

    public function validateTestAnswers($answers)
    {
        if (!$this->hasTest()) {
            return ['passed' => true, 'score' => 100, 'total' => 0, 'correct' => 0];
        }

        $questions = $this->getTestQuestions();
        $totalQuestions = count($questions);
        $correctAnswers = 0;

        foreach ($questions as $index => $question) {
            $questionId = $question['id'] ?? $index;
            $correctAnswer = $question['correctAnswer'] ?? null;
            $userAnswer = $answers[$questionId] ?? null;

            if ($correctAnswer !== null && $userAnswer === $correctAnswer) {
                $correctAnswers++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
        $passed = $score >= $this->getPassingScore();

        return [
            'passed' => $passed,
            'score' => $score,
            'total' => $totalQuestions,
            'correct' => $correctAnswers
        ];
    }

}
