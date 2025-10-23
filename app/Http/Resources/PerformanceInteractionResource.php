<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceInteractionResource extends JsonResource
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
            'subject' => $this->subject,
            'message' => $this->message,
            'priority' => $this->priority,
            'followUpRequired' => $this->follow_up_required,
            'followUpDate' => $this->follow_up_date ? $this->follow_up_date->format('Y-m-d') : null,
            'createdBy' => $this->created_by_name,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

