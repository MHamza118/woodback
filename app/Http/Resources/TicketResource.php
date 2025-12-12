<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdByAdmin = $this->created_by_admin ?? false;
        
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'created_by_admin' => $createdByAdmin,
            'employee_name' => $this->when(!$createdByAdmin && $this->relationLoaded('employee'), function () {
                return $this->employee ? trim("{$this->employee->first_name} {$this->employee->last_name}") : 'Unknown Employee';
            }),
            'admin_name' => $this->when($createdByAdmin && $this->relationLoaded('admin'), function () {
                if ($this->admin) {
                    $name = trim("{$this->admin->first_name} {$this->admin->last_name}");
                    $role = $this->admin->role;
                    return "{$name} ({$role})";
                }
                return 'Unknown Admin';
            }),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'location' => $this->location,
            'archived' => $this->archived,
            'archived_at' => $this->when($this->archived_at, $this->archived_at?->toISOString()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            // Unified responses for admin/employee
            'responses' => $this->relationLoaded('responses')
                ? TicketResponseResource::collection($this->responses)
                : ($this->relationLoaded('publicResponses')
                    ? TicketResponseResource::collection($this->publicResponses)
                    : []),
            // Include employee data when needed
            'employee' => $this->when(!$createdByAdmin && $this->relationLoaded('employee') && $this->employee, [
                'id' => $this->employee->id,
                'name' => trim("{$this->employee->first_name} {$this->employee->last_name}"),
                'email' => $this->employee->email,
                'location' => $this->employee->location
            ])
        ];
    }
}
