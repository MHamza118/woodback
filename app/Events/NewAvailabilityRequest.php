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

class NewAvailabilityRequest implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $availabilityRequest;
    public $employeeName;
    public $employeeId;

    /**
     * Create a new event instance.
     */
    public function __construct(AvailabilityRequest $availabilityRequest, string $employeeName, int $employeeId)
    {
        $this->availabilityRequest = $availabilityRequest;
        $this->employeeName = $employeeName;
        $this->employeeId = $employeeId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin-availability-requests'),
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
            'employee_name' => $this->employeeName,
            'effective_from' => $this->availabilityRequest->effective_from,
            'effective_to' => $this->availabilityRequest->effective_to,
            'type' => $this->availabilityRequest->type,
            'created_at' => $this->availabilityRequest->created_at,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'availability-request-submitted';
    }
}
