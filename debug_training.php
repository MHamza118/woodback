<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Models\TrainingModule;
use App\Services\EmployeeTrainingService;

echo "=== TRAINING ASSIGNMENT DEBUG ===\n";

// Check total counts
echo "Total Training Modules: " . TrainingModule::count() . "\n";
echo "Total Training Assignments: " . TrainingAssignment::count() . "\n";
echo "Active Training Modules: " . TrainingModule::where('active', true)->count() . "\n";

// Find first approved employee
$employee = Employee::where('status', 'approved')->first();

if (!$employee) {
    echo "ERROR: No approved employee found!\n";
    exit;
}

echo "\n=== EMPLOYEE INFO ===\n";
echo "Employee ID: " . $employee->id . "\n";
echo "Employee Name: " . $employee->full_name . "\n";
echo "Employee Email: " . $employee->email . "\n";
echo "Employee Status: " . $employee->status . "\n";

// Direct assignments query
echo "\n=== DIRECT ASSIGNMENTS QUERY ===\n";
$assignments = TrainingAssignment::where('employee_id', $employee->id)
    ->with('module')
    ->get();
    
echo "Direct Assignments Count: " . $assignments->count() . "\n";

foreach ($assignments as $assignment) {
    echo "  - Assignment " . $assignment->id . "\n";
    echo "    Module: " . ($assignment->module ? $assignment->module->title : 'No Module') . "\n";
    echo "    Status: " . $assignment->status . "\n";
    echo "    Created: " . $assignment->created_at . "\n";
    echo "    Employee ID: " . $assignment->employee_id . "\n";
    echo "\n";
}

// Try the service
echo "\n=== EMPLOYEE TRAINING SERVICE TEST ===\n";
$service = new EmployeeTrainingService();
try {
    $result = $service->getAssignedTrainingModules($employee->id);
    echo "Service returned assignments count: " . count($result['assignments']) . "\n";
    echo "Service returned modules count: " . count($result['modules']) . "\n";
    
    if (count($result['assignments']) > 0) {
        echo "Assignments from service:\n";
        foreach ($result['assignments'] as $assignment) {
            echo "  - Assignment " . $assignment['id'] . " for " . $assignment['module']['title'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Service ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== END DEBUG ===\n";
