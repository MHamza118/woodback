<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    protected $customerRepository;
    protected $announcementRepository;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AnnouncementRepositoryInterface $announcementRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->announcementRepository = $announcementRepository;
    }

    /**
     * Get all customers with filtering and pagination
     */
    public function getAllCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->customerRepository->getAllWithFilters($filters, $perPage);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(string $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }

    /**
     * Register new customer
     */
    public function registerCustomer(array $data): Customer
    {
        // Validate unique email
        if ($this->customerRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['A customer with this email already exists.']
            ]);
        }

        // Generate full name from first_name and last_name
        $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        // Set default values
        $data['status'] = 'ACTIVE';
        $data['preferences'] = [
            'notifications' => true,
            'marketing' => false
        ];
        $data['last_visit'] = now();

        // Set home location from locations array
        if (isset($data['locations']) && is_array($data['locations'])) {
            $homeLocation = collect($data['locations'])->firstWhere('is_home', true);
            $data['home_location'] = $homeLocation['name'] ?? '';
        }

        return $this->customerRepository->create($data);
    }

    /**
     * Update customer profile
     */
    public function updateCustomerProfile(string $id, array $data): ?Customer
    {
        $customer = $this->customerRepository->findById($id);
        if (!$customer) {
            return null;
        }

        // Set home location from locations array if provided
        if (isset($data['locations']) && is_array($data['locations'])) {
            $homeLocation = collect($data['locations'])->firstWhere('is_home', true);
            $data['home_location'] = $homeLocation['name'] ?? $customer->home_location;
        }

        return $this->customerRepository->update($id, $data);
    }

    /**
     * Get customer dashboard data
     */
    public function getCustomerDashboard(string $customerId): array
    {
        // Update last visit
        $this->customerRepository->updateLastVisit($customerId);

        $dashboardData = $this->customerRepository->getDashboardData($customerId);
        
        if (empty($dashboardData)) {
            return [];
        }

        // Get customer-specific announcements
        $customer = $dashboardData['customer'];
        $announcements = $this->getCustomerAnnouncements($customer);
        $dashboardData['announcements'] = $announcements['all'] ?? [];
        $dashboardData['event_notifications'] = $announcements['events'] ?? [];

        return $dashboardData;
    }

    /**
     * Get customer announcements
     */
    public function getCustomerAnnouncements(Customer $customer): array
    {
        $allAnnouncements = $this->announcementRepository->getActiveAnnouncementsForCustomer($customer);
        
        // Separate events from other announcements
        $events = $allAnnouncements->where('type', 'event');
        $others = $allAnnouncements->where('type', '!=', 'event');

        return [
            'all' => $allAnnouncements->values(), // Reset keys
            'events' => $events->values(), // Reset keys
            'others' => $others->values() // Reset keys
        ];
    }

    /**
     * Dismiss announcement for customer
     */
    public function dismissAnnouncement(string $customerId, string $announcementId): bool
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return false;
        }

        // Check if announcement exists and is dismissible
        $announcement = $this->announcementRepository->findById($announcementId);
        if (!$announcement || !$announcement->is_dismissible) {
            return false;
        }

        // Add to dismissed announcements
        $customer->dismissedAnnouncements()->syncWithoutDetaching([$announcementId]);

        return true;
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(): array
    {
        return $this->customerRepository->getStatistics();
    }

    /**
     * Search customers
     */
    public function searchCustomers(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->customerRepository->getAllWithFilters([
            'search' => $query
        ], $perPage);
    }

    /**
     * Get customers by location
     */
    public function getCustomersByLocation(string $location): Collection
    {
        return $this->customerRepository->getByLocation($location);
    }

    /**
     * Soft delete customer
     */
    public function deleteCustomer(string $id): bool
    {
        return $this->customerRepository->delete($id);
    }

    /**
     * Get customer profile with all relations
     */
    public function getCustomerProfile(string $customerId): ?Customer
    {
        return $this->customerRepository->getProfileWithRelations($customerId);
    }

    /**
     * Update customer preferences
     */
    public function updateCustomerPreferences(string $customerId, array $preferences): ?Customer
    {
        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return null;
        }

        $currentPreferences = $customer->preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $preferences);

        return $this->customerRepository->update($customerId, [
            'preferences' => $updatedPreferences
        ]);
    }
}
