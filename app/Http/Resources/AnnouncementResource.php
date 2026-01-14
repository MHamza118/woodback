<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'start_date' => $this->start_date->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'created_by' => $this->createdBy?->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'view_count' => $this->views()->count(),
            'viewed_by' => $this->viewedByEmployees()->get()->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'email' => $employee->email,
                    'viewed_at' => $employee->pivot->viewed_at
                ];
            })->toArray()
        ];
    }
}
