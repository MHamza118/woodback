<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer' => new CustomerResource($this->resource['customer']),
            'announcements' => AnnouncementResource::collection($this->resource['announcements'] ?? []),
            'event_notifications' => AnnouncementResource::collection($this->resource['event_notifications'] ?? []),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'dashboard_type' => 'customer',
            ],
        ];
    }
}
