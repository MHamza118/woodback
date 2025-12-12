<?php

namespace App\Events;

use App\Models\AvailabilityRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvailabilityRequestStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $availabilityRequest;
    public $employeeId;
    public $status;
    public $adminNotes;

    /**
     * Create a new event instance.
     */
    public function __construct(AvailabilityRequest $availabilityRequest, int $employeeId, string $status, ?string $adminNotes = null)
    {
        $this->availabilityRequest = $availabilityRequest;
        $this->employeeId = $employeeId;
        $this->status = $status;
        $this->adminNotes = $adminNotes;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('employee-availability-updates'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->availabilityRequest->id,
            'employee_id' => $this->employeeId,
            'status' => $this->status,
            'admin_notes' => $this->adminNotes,
            'effective_from' => $this->availabilityRequest->effective_from,
            'effective_to' => $this->availabilityRequest->effective_to,
            'type' => $this->availabilityRequest->type,
            'updated_at' => $this->availabilityRequest->updated_at,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'availability-request-status-updated';
    }
}
