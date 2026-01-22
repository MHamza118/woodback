<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    /**
     * Find event by ID
     */
    public function findById(string $id): ?Event;

    /**
     * Create new event
     */
    public function create(array $data): Event;

    /**
     * Update event
     */
    public function update(string $id, array $data): ?Event;

    /**
     * Delete event
     */
    public function delete(string $id): bool;

    /**
     * Get all events with filters
     */
    public function getAllWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get events for a specific date
     */
    public function getForDate(string $date): Collection;

    /**
     * Get events for a date range
     */
    public function getForDateRange(string $startDate, string $endDate): Collection;

    /**
     * Get events created by admin
     */
    public function getByAdmin(int $adminId): Collection;

    /**
     * Get upcoming events
     */
    public function getUpcoming(int $limit = 10): Collection;
}
