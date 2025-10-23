<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'employeeName' => $this->employee ? 
                trim(($this->employee->personal_info['firstName'] ?? '') . ' ' . ($this->employee->personal_info['lastName'] ?? '')) :
                'Unknown Employee',
            'type' => $this->type,
            'reviewPeriod' => $this->review_period,
            'ratings' => [
                'punctuality' => (float) $this->punctuality,
                'workQuality' => (float) $this->work_quality,
                'teamwork' => (float) $this->teamwork,
                'communication' => (float) $this->communication,
                'customerService' => (float) $this->customer_service,
                'initiative' => (float) $this->initiative,
            ],
            'overallRating' => (float) $this->overall_rating,
            'strengths' => $this->strengths,
            'areasForImprovement' => $this->areas_for_improvement,
            'goals' => $this->goals,
            'notes' => $this->notes,
            'createdBy' => $this->created_by_name,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

