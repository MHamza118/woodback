<?php

namespace App\Repositories;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRepository implements EventRepositoryInterface
{
    protected $model;

    public function __construct(Event $model)
    {
        $this->model = $model;
    }

    /**
     * Find event by ID
     */
    public function findById(string $id): ?Event
    {
        return $this->model->with(['createdBy'])->find($id);
    }

    /**
     * Create new event
     */
    public function create(array $data): Event
    {
        return $this->model->create($data);
    }

    /**
     * Update event
     */
    public function update(string $id, array $data): ?Event
    {
        $event = $this->findById($id);
        if (!$event) {
            return null;
        }

        $event->update($data);
        return $event->fresh();
    }

    /**
     * Delete event
     */
    public function delete(string $id): bool
    {
        $event = $this->findById($id);
        if (!$event) {
            return false;
        }

        return $event->delete();
    }

    /**
     * Get all events with filters
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['createdBy']);

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['repeat_type'])) {
            $query->where('repeat_type', $filters['repeat_type']);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        $sortBy = $filters['sort_by'] ?? 'start_date';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Get events for a specific date
     */
    public function getForDate(string $date): Collection
    {
        return $this->model->forDate($date)->get();
    }

    /**
     * Get events for a date range
     */
    public function getForDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->forDateRange($startDate, $endDate)->get();
    }

    /**
     * Get events created by admin
     */
    public function getByAdmin(int $adminId): Collection
    {
        return $this->model->where('created_by', $adminId)
                           ->orderBy('start_date', 'asc')
                           ->get();
    }

    /**
     * Get upcoming events
     */
    public function getUpcoming(int $limit = 10): Collection
    {
        return $this->model->upcoming()
                           ->orderBy('start_date', 'asc')
                           ->limit($limit)
                           ->get();
    }
}
