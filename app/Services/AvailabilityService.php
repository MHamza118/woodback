<?php

namespace App\Services;

use App\Models\AvailabilityRequest;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Get effective availability for an employee on a specific date
     * Temporary availability overrides recurring availability during its date range
     * 
     * @param int $employeeId
     * @param Carbon|string|null $date If null, uses today's date
     * @return array|null The availability data for that date, or null if no availability set
     */
    public function getEffectiveAvailability($employeeId, $date = null)
    {
        if ($date === null) {
            $date = Carbon::today();
        } elseif (is_string($date)) {
            $date = Carbon::parse($date);
        }

        // First, check for approved temporary availability that covers this date
        $temporaryAvailability = AvailabilityRequest::where('employee_id', $employeeId)
            ->where('type', 'temporary')
            ->where('status', 'approved')
            ->where('effective_from', '<=', $date)
            ->where('effective_to', '>=', $date)
            ->latest('created_at')
            ->first();

        if ($temporaryAvailability) {
            return [
                'type' => 'temporary',
                'availability_data' => $temporaryAvailability->availability_data,
                'effective_from' => $temporaryAvailability->effective_from,
                'effective_to' => $temporaryAvailability->effective_to,
                'request_id' => $temporaryAvailability->id
            ];
        }

        // If no temporary availability, fall back to recurring availability
        $recurringAvailability = AvailabilityRequest::where('employee_id', $employeeId)
            ->where('type', 'recurring')
            ->where('status', 'approved')
            ->latest('created_at')
            ->first();

        if ($recurringAvailability) {
            return [
                'type' => 'recurring',
                'availability_data' => $recurringAvailability->availability_data,
                'request_id' => $recurringAvailability->id
            ];
        }

        // No availability set
        return null;
    }

    /**
     * Get effective availability for a date range
     * Returns availability for each day in the range
     * 
     * @param int $employeeId
     * @param Carbon|string $startDate
     * @param Carbon|string $endDate
     * @return array Array of dates with their effective availability
     */
    public function getEffectiveAvailabilityRange($employeeId, $startDate, $endDate)
    {
        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate);
        }
        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate);
        }

        $availabilityRange = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $availability = $this->getEffectiveAvailability($employeeId, $currentDate);
            $availabilityRange[$currentDate->toDateString()] = $availability;
            $currentDate->addDay();
        }

        return $availabilityRange;
    }

    /**
     * Get availability for a specific day of week
     * Considers temporary overrides for today
     * 
     * @param int $employeeId
     * @param string $dayOfWeek (monday, tuesday, etc.)
     * @param Carbon|string|null $date If null, uses today
     * @return array|null The day's availability or null
     */
    public function getDayAvailability($employeeId, $dayOfWeek, $date = null)
    {
        $availability = $this->getEffectiveAvailability($employeeId, $date);

        if (!$availability) {
            return null;
        }

        $dayOfWeek = strtolower($dayOfWeek);
        return $availability['availability_data'][$dayOfWeek] ?? null;
    }

    /**
     * Check if employee is available on a specific date
     * 
     * @param int $employeeId
     * @param Carbon|string $date
     * @return bool
     */
    public function isAvailableOnDate($employeeId, $date)
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        // Get day of week in lowercase (monday, tuesday, etc.)
        $dayOfWeek = strtolower($date->format('l'));

        $dayAvailability = $this->getDayAvailability($employeeId, $dayOfWeek, $date);

        if (!$dayAvailability) {
            return false;
        }

        return $dayAvailability['enabled'] && $dayAvailability['status'] === 'available';
    }

    /**
     * Get all active availability requests for an employee
     * Separates recurring and temporary (active and expired)
     * 
     * @param int $employeeId
     * @return array
     */
    public function getEmployeeAvailabilitySummary($employeeId)
    {
        $today = Carbon::today();

        $recurringAvailability = AvailabilityRequest::where('employee_id', $employeeId)
            ->where('type', 'recurring')
            ->where('status', 'approved')
            ->latest('created_at')
            ->first();

        $activeTemporaryAvailability = AvailabilityRequest::where('employee_id', $employeeId)
            ->where('type', 'temporary')
            ->where('status', 'approved')
            ->where('effective_from', '<=', $today)
            ->where('effective_to', '>=', $today)
            ->latest('created_at')
            ->first();

        $upcomingTemporaryAvailability = AvailabilityRequest::where('employee_id', $employeeId)
            ->where('type', 'temporary')
            ->where('status', 'approved')
            ->where('effective_from', '>', $today)
            ->orderBy('effective_from', 'asc')
            ->get();

        return [
            'recurring' => $recurringAvailability,
            'active_temporary' => $activeTemporaryAvailability,
            'upcoming_temporary' => $upcomingTemporaryAvailability
        ];
    }
}
