<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Services\EmployeeTrainingService;
use App\Http\Controllers\Api\V1\EmployeeController;

echo "=== DIRECT API TEST ===\n";

$employee = Employee::where('status', 'approved')->first();

if (!$employee) {
    echo "ERROR: No approved employee found!\n";
    exit;
}

echo "Testing with Employee ID: " . $employee->id . "\n";

// Test the service directly
$service = new EmployeeTrainingService();
$serviceResult = $service->getAssignedTrainingModules($employee->id);

echo "\n=== SERVICE RESULT ===\n";
echo "Assignments count: " . count($serviceResult['assignments']) . "\n";
echo "Modules count: " . count($serviceResult['modules']) . "\n";
echo "Service result keys: " . implode(', ', array_keys($serviceResult)) . "\n";

// Test the controller response (simulate)
// Skip controller instantiation, just simulate response

// Simulate the response
$apiResponse = [
    'success' => true,
    'data' => $serviceResult,
    'message' => 'Training modules retrieved successfully'
];

echo "\n=== SIMULATED API RESPONSE ===\n";
echo "API Response keys: " . implode(', ', array_keys($apiResponse)) . "\n";
echo "API Data keys: " . implode(', ', array_keys($apiResponse['data'])) . "\n";
echo "Assignments in API data: " . count($apiResponse['data']['assignments']) . "\n";
echo "Modules in API data: " . count($apiResponse['data']['modules']) . "\n";

echo "\n=== FULL API RESPONSE STRUCTURE ===\n";
echo json_encode([
    'success' => $apiResponse['success'],
    'message' => $apiResponse['message'],
    'data_keys' => array_keys($apiResponse['data']),
    'assignments_count' => count($apiResponse['data']['assignments']),
    'modules_count' => count($apiResponse['data']['modules']),
    'first_assignment' => $apiResponse['data']['assignments'][0] ?? null,
    'first_module_basic_info' => [
        'id' => $apiResponse['data']['modules'][0]['id'] ?? null,
        'title' => $apiResponse['data']['modules'][0]['title'] ?? null,
        'assignment_status' => $apiResponse['data']['modules'][0]['assignment_status'] ?? null
    ]
], JSON_PRETTY_PRINT);

echo "\n=== END TEST ===\n";
