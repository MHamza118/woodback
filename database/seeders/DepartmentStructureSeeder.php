<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DepartmentStructure;

class DepartmentStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DepartmentStructure::truncate();

        // FOH Areas
        DepartmentStructure::create([
            'department_id' => 'FOH',
            'area_id' => 'dining_room',
            'area_name' => 'Dining Room',
            'area_description' => 'Main dining area service',
            'roles' => [
                ['id' => 'server', 'name' => 'Server', 'description' => 'Takes orders and serves customers'],
                ['id' => 'busser', 'name' => 'Busser', 'description' => 'Clears and sets tables'],
                ['id' => 'food_runner', 'name' => 'Food Runner', 'description' => 'Delivers food to tables']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'FOH',
            'area_id' => 'bar',
            'area_name' => 'Bar Area',
            'area_description' => 'Bar and beverage service',
            'roles' => [
                ['id' => 'bartender', 'name' => 'Bartender', 'description' => 'Prepares and serves beverages'],
                ['id' => 'barback', 'name' => 'Barback', 'description' => 'Supports bartender operations']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'FOH',
            'area_id' => 'host_station',
            'area_name' => 'Host Station',
            'area_description' => 'Guest reception and seating',
            'roles' => [
                ['id' => 'host', 'name' => 'Host/Hostess', 'description' => 'Greets and seats customers'],
                ['id' => 'cashier', 'name' => 'Cashier', 'description' => 'Handles payments and transactions']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'FOH',
            'area_id' => 'patio',
            'area_name' => 'Patio/Outdoor',
            'area_description' => 'Outdoor dining area service',
            'roles' => [
                ['id' => 'server', 'name' => 'Server', 'description' => 'Takes orders and serves customers'],
                ['id' => 'busser', 'name' => 'Busser', 'description' => 'Clears and sets tables']
            ]
        ]);

        // BOH Areas
        DepartmentStructure::create([
            'department_id' => 'BOH',
            'area_id' => 'kitchen',
            'area_name' => 'Main Kitchen',
            'area_description' => 'Main cooking and food preparation',
            'roles' => [
                ['id' => 'line_cook', 'name' => 'Line Cook', 'description' => 'Prepares food on the cooking line'],
                ['id' => 'sous_chef', 'name' => 'Sous Chef', 'description' => 'Assists head chef and manages kitchen'],
                ['id' => 'head_chef', 'name' => 'Head Chef', 'description' => 'Manages kitchen operations'],
                ['id' => 'expo', 'name' => 'Expo', 'description' => 'Coordinates food orders and quality']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'BOH',
            'area_id' => 'prep_area',
            'area_name' => 'Prep Area',
            'area_description' => 'Food preparation and ingredient prep',
            'roles' => [
                ['id' => 'prep_cook', 'name' => 'Prep Cook', 'description' => 'Prepares ingredients and components'],
                ['id' => 'food_prep', 'name' => 'Food Prep', 'description' => 'General food preparation duties']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'BOH',
            'area_id' => 'dish_pit',
            'area_name' => 'Dish Pit',
            'area_description' => 'Cleaning and sanitation',
            'roles' => [
                ['id' => 'dishwasher', 'name' => 'Dishwasher', 'description' => 'Washes dishes and maintains cleanliness']
            ]
        ]);

        DepartmentStructure::create([
            'department_id' => 'BOH',
            'area_id' => 'storage',
            'area_name' => 'Storage/Receiving',
            'area_description' => 'Inventory and receiving operations',
            'roles' => [
                ['id' => 'kitchen_manager', 'name' => 'Kitchen Manager', 'description' => 'Manages kitchen operations and inventory'],
                ['id' => 'receiving', 'name' => 'Receiving', 'description' => 'Handles inventory and deliveries']
            ]
        ]);

        $this->command->info('Department structure seeded successfully!');
    }
}