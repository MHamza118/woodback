<?php

namespace App\Services;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventService
{
    protected $eventRepository;

    public function __construct(EventRepositoryInterface $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * Get all events with filters
     */
    public function getAllEvents(array $filters = [])
    {
        try {
            return $this->eventRepository->getAllWithFilters($filters);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get event by ID
     */
    public function getEventById(string $id): ?Event
    {
        try {
            return $this->eventRepository->findById($id);
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create new event with repeat handling
     */
    public function createEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            try {
                $event = $this->eventRepository->create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'start_date' => $data['start_date'],
                    'start_time' => $data['start_time'],
                    'end_date' => $data['end_date'],
                    'end_time' => $data['end_time'],
                    'color' => $data['color'] ?? '#3B82F6',
                    'repeat_type' => $data['repeat_type'] ?? 'none',
                    'repeat_end_date' => $data['repeat_end_date'] ?? null,
                    'created_by' => $data['created_by'],
                ]);

                Log::info('Event created successfully', [
                    'event_id' => $event->id,
                    'title' => $event->title,
                    'created_by' => $event->created_by,
                ]);

                return $event;
            } catch (\Exception $e) {
                Log::error('Error creating event: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Update event
     */
    public function updateEvent(string $id, array $data): ?Event
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                $event = $this->eventRepository->update($id, [
                    'title' => $data['title'] ?? null,
                    'description' => $data['description'] ?? null,
                    'start_date' => $data['start_date'] ?? null,
                    'start_time' => $data['start_time'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                    'color' => $data['color'] ?? null,
                    'repeat_type' => $data['repeat_type'] ?? null,
                    'repeat_end_date' => $data['repeat_end_date'] ?? null,
                ]);

                if ($event) {
                    Log::info('Event updated successfully', [
                        'event_id' => $event->id,
                        'title' => $event->title,
                    ]);
                }

                return $event;
            } catch (\Exception $e) {
                Log::error('Error updating event: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Delete event
     */
    public function deleteEvent(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            try {
                $event = $this->eventRepository->findById($id);
                if (!$event) {
                    return false;
                }

                $deleted = $this->eventRepository->delete($id);

                if ($deleted) {
                    Log::info('Event deleted successfully', [
                        'event_id' => $id,
                        'title' => $event->title,
                    ]);
                }

                return $deleted;
            } catch (\Exception $e) {
                Log::error('Error deleting event: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get events for a specific date
     */
    public function getEventsForDate(string $date): array
    {
        try {
            $events = $this->eventRepository->getForDate($date);
            $occurrences = [];

            foreach ($events as $event) {
                $eventOccurrences = $event->getOccurrences(
                    Carbon::parse($date),
                    Carbon::parse($date)
                );
                $occurrences = array_merge($occurrences, $eventOccurrences);
            }

            return $occurrences;
        } catch (\Exception $e) {
            Log::error('Error fetching events for date: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get events for a date range
     */
    public function getEventsForDateRange(string $startDate, string $endDate): array
    {
        try {
            $events = $this->eventRepository->getForDateRange($startDate, $endDate);
            $occurrences = [];

            foreach ($events as $event) {
                $eventOccurrences = $event->getOccurrences(
                    Carbon::parse($startDate),
                    Carbon::parse($endDate)
                );
                $occurrences = array_merge($occurrences, $eventOccurrences);
            }

            return $occurrences;
        } catch (\Exception $e) {
            Log::error('Error fetching events for date range: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get events created by admin
     */
    public function getEventsByAdmin(int $adminId): array
    {
        try {
            return $this->eventRepository->getByAdmin($adminId)->toArray();
        } catch (\Exception $e) {
            Log::error('Error fetching admin events: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(int $limit = 10): array
    {
        try {
            return $this->eventRepository->getUpcoming($limit)->toArray();
        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());
            throw $e;
        }
    }
}
