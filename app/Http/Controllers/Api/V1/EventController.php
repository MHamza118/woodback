<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Services\EventService;
use App\Http\Requests\CreateEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    use ApiResponseTrait;

    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * Get all events with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'created_by',
                'repeat_type',
                'search',
                'start_date',
                'end_date',
                'sort_by',
                'sort_direction'
            ]);

            $perPage = $request->input('per_page', 15);
            $events = $this->eventService->getAllEvents($filters);

            return $this->successResponse([
                'events' => $events->items(),
                'pagination' => [
                    'total' => $events->total(),
                    'per_page' => $events->perPage(),
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'from' => $events->firstItem(),
                    'to' => $events->lastItem(),
                ]
            ], 'Events retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch events', 500);
        }
    }

    /**
     * Get event by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $event = $this->eventService->getEventById($id);

            if (!$event) {
                return $this->notFoundResponse('Event not found');
            }

            return $this->successResponse($event->toArray(), 'Event retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch event', 500);
        }
    }

    /**
     * Create new event
     */
    public function store(CreateEventRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->user()->id;

            $event = $this->eventService->createEvent($data);

            return $this->successResponse($event->toArray(), 'Event created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating event: ' . $e->getMessage());
            return $this->errorResponse('Failed to create event', 500);
        }
    }

    /**
     * Update event
     */
    public function update(string $id, UpdateEventRequest $request): JsonResponse
    {
        try {
            $event = $this->eventService->getEventById($id);

            if (!$event) {
                return $this->notFoundResponse('Event not found');
            }

            $data = $request->validated();
            $updatedEvent = $this->eventService->updateEvent($id, $data);

            return $this->successResponse($updatedEvent->toArray(), 'Event updated successfully');
        } catch (\Exception $e) {
            Log::error('Error updating event: ' . $e->getMessage());
            return $this->errorResponse('Failed to update event', 500);
        }
    }

    /**
     * Delete event
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $event = $this->eventService->getEventById($id);

            if (!$event) {
                return $this->notFoundResponse('Event not found');
            }

            $deleted = $this->eventService->deleteEvent($id);

            if (!$deleted) {
                return $this->errorResponse('Failed to delete event', 500);
            }

            return $this->successResponse(null, 'Event deleted successfully');
        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete event', 500);
        }
    }

    /**
     * Get events for a specific date
     */
    public function getForDate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date_format:Y-m-d'
            ]);

            $date = $request->input('date');
            $events = $this->eventService->getEventsForDate($date);

            return $this->successResponse($events, 'Events retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching events for date: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch events', 500);
        }
    }

    /**
     * Get events for a date range
     */
    public function getForDateRange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date'
            ]);

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $events = $this->eventService->getEventsForDateRange($startDate, $endDate);

            return $this->successResponse($events, 'Events retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching events for date range: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch events', 500);
        }
    }

    /**
     * Get upcoming events
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            $events = $this->eventService->getUpcomingEvents($limit);

            return $this->successResponse($events, 'Upcoming events retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch upcoming events', 500);
        }
    }
}
