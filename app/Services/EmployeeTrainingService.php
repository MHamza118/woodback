<?php

namespace App\Services;

use App\Models\TrainingModule;
use App\Models\TrainingAssignment;
use App\Models\TrainingProgress;
use App\Models\Employee;
use App\Models\TableNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeTrainingService
{
    /**
     * Get training modules assigned to employee
     */
    public function getAssignedTrainingModules(string $employeeId): array
    {
        // Ensure employee ID is properly cast to integer for database queries
        $employeeIdInt = (int) $employeeId;
        
        \Log::info('Retrieving training modules for employee', [
            'employee_id_original' => $employeeId,
            'employee_id_cast' => $employeeIdInt,
            'employee_id_type' => gettype($employeeIdInt)
        ]);
        
        $employee = Employee::find($employeeIdInt);
        if (!$employee) {
            \Log::error('Employee not found during training module retrieval', [
                'employee_id' => $employeeIdInt
            ]);
            throw new \Exception('Employee not found');
        }
        
        \Log::info('Found employee for training module retrieval', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name
        ]);

        $assignments = TrainingAssignment::where('employee_id', $employeeIdInt)
            ->with(['module'])
            ->whereHas('module', function ($query) {
                $query->where('active', true);
            })
            ->whereNotIn('status', ['removed'])
            ->orderBy('assigned_at', 'desc')
            ->get();
            
        \Log::info('Retrieved training assignments for employee', [
            'employee_id' => $employeeIdInt,
            'total_assignments' => $assignments->count(),
            'assignment_details' => $assignments->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'module_id' => $assignment->module_id,
                    'module_title' => $assignment->module->title ?? 'No Module',
                    'employee_id' => $assignment->employee_id,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at
                ];
            })->toArray()
        ]);

        $formattedAssignments = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'module' => [
                    'id' => $assignment->module->id,
                    'title' => $assignment->module->title,
                    'description' => $assignment->module->description,
                    'category' => $assignment->module->category,
                    'duration' => $assignment->module->duration,
                    'video_url' => $assignment->module->video_url,
                    'qr_code' => $assignment->module->qr_code
                ],
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at->toISOString(),
                'due_date' => $assignment->due_date?->toISOString(),
                'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                'started_at' => $assignment->started_at?->toISOString(),
                'completed_at' => $assignment->completed_at?->toISOString(),
                'progress' => $this->calculateProgress($assignment),
                'is_overdue' => $this->isOverdue($assignment),
                'can_unlock' => $this->canUnlock($assignment),
                'notes' => $assignment->notes
            ];
        });

        $stats = $this->calculateEmployeeStats($assignments);

        // Only return training modules that have been assigned to the employee
        // This ensures modules are hidden until admin explicitly assigns them
        $allModules = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->module->id,
                'title' => $assignment->module->title,
                'description' => $assignment->module->description,
                'category' => $assignment->module->category,
                'duration' => $assignment->module->duration,
                'video_url' => $assignment->module->video_url,
                'qr_code' => $assignment->module->qr_code,
                'content' => $assignment->module->content,
                // Assignment information
                'assignment_id' => $assignment->id,
                'assignment_status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at?->toISOString(),
                'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                'started_at' => $assignment->started_at?->toISOString(),
                'completed_at' => $assignment->completed_at?->toISOString(),
                'progress' => $this->calculateProgress($assignment),
                'is_overdue' => $this->isOverdue($assignment),
                'can_unlock' => $this->canUnlock($assignment),
            ];
        });

        return [
            'assignments' => $formattedAssignments,
            'modules' => $allModules,
            'stats' => $stats,
            'statistics' => $stats // Alias for compatibility
        ];
    }

    /**
     * Unlock training module via QR code
     */
    public function unlockTrainingViaQR(string $employeeId, string $qrCode): array
    {
        // Ensure employee ID is properly cast to integer
        $employeeIdInt = (int) $employeeId;
        
        $employee = Employee::find($employeeIdInt);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Find the training module with this QR code
        $module = TrainingModule::where('qr_code', $qrCode)
            ->where('active', true)
            ->first();

        if (!$module) {
            throw new \Exception('Invalid QR code or training module not found');
        }

        // Check if employee has this training assigned
        \Log::info('Checking for training assignment during unlock', [
            'employee_id' => $employeeIdInt,
            'module_id' => $module->id
        ]);
        
        $assignment = TrainingAssignment::where('employee_id', $employeeIdInt)
            ->where('module_id', $module->id)
            ->whereNotIn('status', ['removed', 'completed'])
            ->first();
            
        \Log::info('Assignment check result', [
            'employee_id' => $employeeIdInt,
            'module_id' => $module->id,
            'assignment_found' => $assignment ? true : false,
            'assignment_id' => $assignment ? $assignment->id : null,
            'assignment_status' => $assignment ? $assignment->status : null
        ]);
        
        // Also do a broader search to see if any assignments exist for this employee
        $allAssignments = TrainingAssignment::where('employee_id', $employeeIdInt)->get();
        \Log::info('All assignments for employee', [
            'employee_id' => $employeeIdInt,
            'total_assignments' => $allAssignments->count(),
            'all_assignments' => $allAssignments->map(function($a) {
                return [
                    'id' => $a->id,
                    'module_id' => $a->module_id,
                    'employee_id' => $a->employee_id,
                    'status' => $a->status
                ];
            })->toArray()
        ]);

        if (!$assignment) {
            \Log::error('Training module not assigned to employee', [
                'employee_id' => $employeeIdInt,
                'module_id' => $module->id,
                'qr_code' => $qrCode
            ]);
            throw new \Exception('Training module not assigned to this employee');
        }

        // Check if already unlocked
        if ($assignment->status === 'unlocked' || $assignment->status === 'in_progress') {
            return [
                'message' => 'Training module already unlocked',
                'assignment' => $this->formatAssignment($assignment),
                'module_content' => $this->formatModuleContent($module)
            ];
        }

        return DB::transaction(function () use ($assignment, $module) {
            // Update assignment status to unlocked
            $assignment->update([
                'status' => 'unlocked',
                'unlocked_at' => now()
            ]);

            return [
                'message' => 'Training module successfully unlocked',
                'assignment' => $this->formatAssignment($assignment->fresh()),
                'module_content' => $this->formatModuleContent($module)
            ];
        });
    }

    /**
     * Get training module content for employee
     */
    public function getModuleContent(string $employeeId, string $moduleId): array
    {
        // Ensure employee ID is properly cast to integer
        $employeeIdInt = (int) $employeeId;
        
        $employee = Employee::find($employeeIdInt);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        $module = TrainingModule::where('id', $moduleId)
            ->where('active', true)
            ->first();

        if (!$module) {
            throw new \Exception('Training module not found');
        }

        // Check if employee has access to this module
        $assignment = TrainingAssignment::where('employee_id', $employeeIdInt)
            ->where('module_id', $moduleId)
            ->whereIn('status', ['unlocked', 'in_progress', 'completed'])
            ->first();

        if (!$assignment) {
            throw new \Exception('Access denied. Training module must be unlocked first.');
        }

        // Update status to in_progress if not already
        if ($assignment->status === 'unlocked') {
            $assignment->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);
        }
        
        // Always create/update progress session when content is accessed
        // This ensures real progress tracking in the database
        $activeProgress = TrainingProgress::where('assignment_id', $assignment->id)
            ->where('employee_id', $employeeIdInt)
            ->active()
            ->first();
            
        if (!$activeProgress) {
            TrainingProgress::startSession(
                $assignment->id,
                $employeeIdInt,
                $moduleId,
                ['training_started' => true, 'access_time' => now()->toISOString()]
            );
        }

        return [
            'assignment' => $this->formatAssignment($assignment->fresh()),
            'module_content' => $this->formatModuleContent($module)
        ];
    }

    /**
     * Complete training module
     */
    public function completeTraining(string $employeeId, string $moduleId, array $completionData = []): array
    {
        // Ensure employee ID is properly cast to integer
        $employeeIdInt = (int) $employeeId;
        
        $employee = Employee::find($employeeIdInt);
        if (!$employee) {
            throw new \Exception('Employee not found');
        }

        // Check if module exists and is active
        $module = TrainingModule::where('id', $moduleId)->where('active', true)->first();
        if (!$module) {
            throw new \Exception('Training module not found or inactive');
        }

        $assignment = TrainingAssignment::where('employee_id', $employeeIdInt)
            ->where('module_id', $moduleId)
            ->whereIn('status', ['unlocked', 'in_progress'])
            ->first();

        if (!$assignment) {
            throw new \Exception('Training assignment not found or not in progress');
        }

        return DB::transaction(function () use ($assignment, $completionData, $employeeIdInt, $moduleId, $employee, $module) {
            $updatedAssignment = $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completion_data' => $completionData
            ]);
            
            if (!$updatedAssignment) {
                throw new \Exception('Failed to update training assignment');
            }
            
            // Ensure at least one progress session exists; if none, create one now so DB reflects completion
            $activeProgress = TrainingProgress::where('assignment_id', $assignment->id)
                ->where('employee_id', $employeeIdInt)
                ->active()
                ->get();

            if ($activeProgress->isEmpty()) {
                // Create a progress session so training completion is recorded in progress table
                $newProgress = TrainingProgress::startSession(
                    $assignment->id,
                    $employeeIdInt,
                    $moduleId,
                    array_merge($completionData, ['created_on_completion' => true])
                );
                $activeProgress = collect([$newProgress]);
            }
                
            foreach ($activeProgress as $progress) {
                $progress->endSession($completionData['time_spent_minutes'] ?? null);
                $progress->updateProgress(array_merge(
                    $completionData,
                    ['completion_time' => now()->toISOString()]
                ));
            }

            // Create notification for admin about training completion
            try {
                $employeeName = $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name);
                
                TableNotification::create([
                    'type' => TableNotification::TYPE_TRAINING_COMPLETED,
                    'title' => 'Training Module Completed',
                    'message' => $employeeName . ' has completed the "' . $module->title . '" training module.',
                    'order_number' => null,
                    'recipient_type' => TableNotification::RECIPIENT_ADMIN,
                    'recipient_id' => null, // Notify all admins
                    'priority' => TableNotification::PRIORITY_MEDIUM,
                    'data' => [
                        'module_id' => $moduleId,
                        'module_title' => $module->title,
                        'employee_id' => $employeeIdInt,
                        'employee_name' => $employeeName,
                        'assignment_id' => $assignment->id,
                        'completed_at' => now()->toISOString()
                    ],
                    'is_read' => false
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create training completion notification', [
                    'employee_id' => $employeeIdInt,
                    'module_id' => $moduleId,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'message' => 'Training completed successfully',
                'assignment' => $this->formatAssignment($assignment->fresh())
            ];
        });
    }

    /**
     * Get employee training statistics
     */
    public function getEmployeeTrainingStats(string $employeeId): array
    {
        // Ensure employee ID is properly cast to integer
        $employeeIdInt = (int) $employeeId;
        
        $assignments = TrainingAssignment::where('employee_id', $employeeIdInt)
            ->whereHas('module', function ($query) {
                $query->where('active', true);
            })
            ->get();

        return $this->calculateEmployeeStats($assignments);
    }

    /**
     * Generate QR code for a training module
     */
    public function generateTrainingQR(string $moduleId): string
    {
        $module = TrainingModule::find($moduleId);
        if (!$module) {
            throw new \Exception('Training module not found');
        }

        // Generate new QR code if it doesn't exist
        if (empty($module->qr_code)) {
            $qrCode = $this->generateUniqueQRCode();
            $module->update(['qr_code' => $qrCode]);
        }

        $qrData = [
            'module_id' => $module->id,
            'qr_code' => $module->qr_code,
            'title' => $module->title,
            'timestamp' => now()->toISOString()
        ];

        return QrCode::size(300)->generate(json_encode($qrData));
    }

    /**
     * Format assignment for API response
     */
    private function formatAssignment(TrainingAssignment $assignment): array
    {
        $assignment->load('module');
        
        return [
            'id' => $assignment->id,
            'module' => [
                'id' => $assignment->module->id,
                'title' => $assignment->module->title,
                'description' => $assignment->module->description,
                'category' => $assignment->module->category,
                'duration' => $assignment->module->duration,
                'video_url' => $assignment->module->video_url,
                'qr_code' => $assignment->module->qr_code
            ],
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at->toISOString(),
            'due_date' => $assignment->due_date?->toISOString(),
            'unlocked_at' => $assignment->unlocked_at?->toISOString(),
            'started_at' => $assignment->started_at?->toISOString(),
            'completed_at' => $assignment->completed_at?->toISOString(),
            'progress' => $this->calculateProgress($assignment),
            'is_overdue' => $this->isOverdue($assignment),
            'notes' => $assignment->notes
        ];
    }

    /**
     * Get module content for display
     */
    private function formatModuleContent(TrainingModule $module): array
    {
        return [
            'id' => $module->id,
            'title' => $module->title,
            'description' => $module->description,
            'content' => $module->content,
            'video_url' => $module->video_url,
            'duration' => $module->duration,
            'category' => $module->category
        ];
    }

    /**
     * Calculate assignment progress
     */
    private function calculateProgress(TrainingAssignment $assignment): int
    {
        // Completed is always 100%
        if ($assignment->status === 'completed') {
            return 100;
        }

        // Ensure module is loaded
        if (!$assignment->relationLoaded('module')) {
            $assignment->load('module');
        }

        // Derive progress from actual TrainingProgress records relative to module duration
        $totalMinutes = TrainingProgress::forAssignment($assignment->id)->sum('time_spent_minutes');
        $moduleDuration = (int) ($assignment->module->duration ?? 0);

        if ($moduleDuration > 0 && $totalMinutes > 0) {
            // Map time spent to 95% max (reserve last 5% for completion action)
            $percent = (int) round(min(95, ($totalMinutes / $moduleDuration) * 95));
            return max($percent, $assignment->status === 'in_progress' ? 15 : ($assignment->status === 'unlocked' ? 5 : 0));
        }

        // If there is at least one progress row but no duration available, show minimal progress
        $hasProgress = TrainingProgress::forAssignment($assignment->id)->exists();
        if ($hasProgress) {
            return $assignment->status === 'in_progress' ? 15 : 5; // minimal non-zero indicator
        }

        // Fallback based on status
        if ($assignment->status === 'unlocked') return 5;
        if ($assignment->status === 'in_progress') return 15;
        return 0;
    }

    /**
     * Check if assignment is overdue
     */
    private function isOverdue(TrainingAssignment $assignment): bool
    {
        if (!$assignment->due_date) {
            return false;
        }

        return $assignment->due_date->isPast() && $assignment->status !== 'completed';
    }

    /**
     * Check if training can be unlocked
     */
    private function canUnlock(TrainingAssignment $assignment): bool
    {
        return in_array($assignment->status, ['assigned', 'overdue']);
    }

    /**
     * Calculate employee training statistics using real progress data
     */
    private function calculateEmployeeStats(Collection $assignments): array
    {
        $total = $assignments->count();
        $completed = $assignments->where('status', 'completed')->count();
        $inProgress = $assignments->where('status', 'in_progress')->count();
        $overdue = $assignments->where('status', 'overdue')->count();
        $assigned = $assignments->where('status', 'assigned')->count();
        
        // Calculate overall completion percentage using actual progress from each assignment
        $totalProgressSum = 0;
        foreach ($assignments as $assignment) {
            $totalProgressSum += $this->calculateProgress($assignment);
        }
        
        $completionRate = $total > 0 ? round($totalProgressSum / $total, 1) : 0;

        return [
            'total_assigned' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'assigned' => $assigned,
            'completion_rate' => $completionRate
        ];
    }

    /**
     * Generate unique QR code
     */
    private function generateUniqueQRCode(): string
    {
        do {
            $qrCode = 'TRN-' . strtoupper(substr(uniqid(), -8));
        } while (TrainingModule::where('qr_code', $qrCode)->exists());

        return $qrCode;
    }
}