# Customer Loyalty & Rewards Cleanup Guide

## Overview
This document outlines the changes made to remove loyalty points, rewards, and order tracking from the customer system, and how to apply these changes to production.

## Changes Made

### 1. Backend Code Changes (Already Applied)

#### Controllers
- **CustomerController.php**: Removed `loyalty_tier` filter from the `index()` method

#### Resources
- **CustomerDashboardResource.php**: 
  - Removed `loyalty_tier` field
  - Removed `available_rewards` field
  - Removed `stats` object with tier calculations
  - Removed helper methods for tier calculations

- **CustomerResource.php**:
  - Removed `loyalty_points` field
  - Removed `loyalty_tier` field
  - Removed `total_orders` field
  - Removed `total_spent` field
  - Removed conditional includes for `favorite_items`, `recent_orders`, and `rewards`

#### Models
- **Customer.php**:
  - Removed `rewards()` relationship
  - Removed `scopeByLoyaltyTier()` scope method
  - Fillable array already cleaned

#### Services
- **CustomerService.php**: No changes needed (already clean)

#### Repositories
- **CustomerRepository.php**:
  - Removed `loyalty_tier` filter logic from `getAllWithFilters()`
  - Removed deprecated `updateLoyaltyPoints()` method
  - Removed deprecated `getByLoyaltyTier()` method

- **CustomerRepositoryInterface.php**:
  - Removed `updateLoyaltyPoints()` method signature
  - Removed `getByLoyaltyTier()` method signature

### 2. Frontend Changes (Already Applied)

#### Components
- **CustomerDashboard.jsx**:
  - Removed footer section
  - Removed "Recent Orders" card
  - Removed "Favorite Items" card

### 3. Database Migration (Created)

**File**: `backend/database/migrations/2026_01_15_000001_remove_loyalty_and_orders_from_customers_table.php`

This migration removes the following columns from the `customers` table:
- `loyalty_points` (integer)
- `total_orders` (integer)
- `total_spent` (decimal)

It also drops the index on `loyalty_points`.

## Production Deployment Steps

### Step 1: Deploy Code Changes
1. Pull the latest code from your repository
2. All backend and frontend code changes are already in place

### Step 2: Run Database Migration
```bash
# SSH into your production server
ssh your-production-server

# Navigate to your backend directory
cd /path/to/backend

# Run the migration
php artisan migrate

# Verify the migration was successful
php artisan migrate:status
```

### Step 3: Clear Application Cache
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart queue workers if applicable
php artisan queue:restart
```

### Step 4: Verify Changes
1. Test customer login and dashboard access
2. Verify no errors in logs: `tail -f storage/logs/laravel.log`
3. Check that customer data loads correctly without loyalty fields

## Rollback Instructions (If Needed)

If you need to rollback these changes:

```bash
# Rollback the migration
php artisan migrate:rollback --step=1

# This will restore the columns:
# - loyalty_points
# - total_orders
# - total_spent
```

## API Response Changes

### Before
```json
{
  "customer": {
    "id": 1,
    "name": "John Doe",
    "loyalty_points": 500,
    "loyalty_tier": "silver",
    "total_orders": 10,
    "total_spent": "250.00"
  },
  "loyalty_tier": "silver",
  "available_rewards": [],
  "stats": {
    "points_to_next_tier": 500,
    "tier_progress": 50.0
  }
}
```

### After
```json
{
  "customer": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "123-456-7890",
    "home_location": "Downtown",
    "preferences": {
      "notifications": true,
      "marketing": false
    },
    "status": "ACTIVE",
    "last_visit": "2026-01-15T10:30:00Z",
    "created_at": "2025-01-01T00:00:00Z"
  },
  "announcements": [],
  "event_notifications": []
}
```

## Database Schema Changes

### Columns Removed from `customers` table
| Column | Type | Default |
|--------|------|---------|
| loyalty_points | integer | 0 |
| total_orders | integer | 0 |
| total_spent | decimal(10,2) | 0.00 |

### Indexes Removed
- Index on `loyalty_points`

## Testing Checklist

- [ ] Customer registration works
- [ ] Customer login works
- [ ] Customer dashboard loads without errors
- [ ] Customer profile page displays correctly
- [ ] No console errors in browser
- [ ] API endpoints return correct data structure
- [ ] No database errors in logs
- [ ] Customer announcements display correctly
- [ ] Customer can update profile
- [ ] Customer can upload profile image

## Notes

- The `profile_image` column was added in a separate migration (2026_01_14_000003)
- Customer locations are still fully functional
- Customer announcements and dismissals are still functional
- All customer authentication and authorization remains unchanged
- The `preferences` field is still used for notification and marketing preferences

## Support

If you encounter any issues during deployment:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify the migration ran successfully: `php artisan migrate:status`
3. Clear caches and try again
4. If critical issues occur, rollback using the instructions above
