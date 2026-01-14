<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Extract phone from questionnaire_responses if phone field is empty
        $phone = $this->phone;
        if (empty($phone) && !empty($this->questionnaire_responses)) {
            foreach ($this->questionnaire_responses as $response) {
                if (is_array($response) && isset($response['question']) && isset($response['answer'])) {
                    $question = strtolower($response['question']);
                    if (str_contains($question, 'phone') && !empty($response['answer'])) {
                        $phone = $response['answer'];
                        break;
                    }
                }
            }
        }

        // Prepare personal info and calculate age
        $personalInfo = $this->profile_data['personal_info'] ?? [];
        if (isset($this->personal_info)) {
            $personalInfo = $this->personal_info;
        }

        if (is_array($personalInfo) && !empty($personalInfo['dob'])) {
            try {
                $personalInfo['age'] = \Carbon\Carbon::parse($personalInfo['dob'])->age;
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $phone,
            'position' => $this->position,
            'department' => $this->department,
            'stage' => $this->stage,
            'status' => $this->status,
            'location' => $this->cleanLocationName($this->location),
            'can_access_dashboard' => $this->canAccessDashboard(),
            'next_stage' => $this->getNextStage(),
            'questionnaire_responses' => $this->questionnaire_responses,
            'profile_data' => $this->profile_data,
            'assignments' => $this->assignments,
            'personal_info' => $personalInfo ?: null,
            'file_uploads' => $this->when($this->fileUploads, function () {
                return $this->fileUploads->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_filename' => $file->original_filename,
                        'file_path' => $file->file_path,
                        'mime_type' => $file->mime_type,
                        'file_size' => $file->file_size,
                        'file_extension' => $file->file_extension,
                        'field_name' => $file->field_name,
                        'upload_status' => $file->upload_status,
                        'question_index' => $file->question_index ?? null,
                        'uploaded_at' => $file->created_at ? $file->created_at->toISOString() : null,
                    ];
                });
            }),
            'questionnaire_files' => $this->when($this->fileUploads, function () {
                return $this->fileUploads->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_filename' => $file->original_filename,
                        'file_path' => $file->file_path,
                        'mime_type' => $file->mime_type,
                        'file_size' => $file->file_size,
                        'file_extension' => $file->file_extension,
                        'field_name' => $file->field_name,
                        'question_index' => $file->question_index ?? null,
                        'uploaded_at' => $file->created_at ? $file->created_at->toISOString() : null,
                    ];
                });
            }),
            'onboarding_progress' => $this->getOnboardingProgress(),
            'onboarding_pages_progress' => $this->getOnboardingPageProgress(),
            'is_personal_info_complete' => $this->isPersonalInfoComplete(),
            'approved_at' => $this->approved_at?->toISOString(),
            'onboarding_pages_completed_at' => $this->onboarding_pages_completed_at?->toISOString(),
            'approved_by' => $this->when($this->approvedBy, function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name ?? $this->approvedBy->full_name,
                    'email' => $this->approvedBy->email,
                ];
            }),
            'assigned_interviewer_id' => $this->assigned_interviewer_id,
            'assigned_interviewer_type' => $this->assigned_interviewer_type,
            'assigned_employee_interviewer_id' => $this->assigned_employee_interviewer_id,
            'assigned_interviewer' => $this->when($this->assignedInterviewer, function () {
                return [
                    'id' => 'admin_' . $this->assignedInterviewer->id,
                    'actual_id' => $this->assignedInterviewer->id,
                    'name' => $this->assignedInterviewer->name ?? $this->assignedInterviewer->full_name,
                    'email' => $this->assignedInterviewer->email,
                    'type' => 'admin'
                ];
            }),
            'assigned_employee_interviewer' => $this->when($this->assignedEmployeeInterviewer, function () {
                return [
                    'id' => 'employee_' . $this->assignedEmployeeInterviewer->id,
                    'actual_id' => $this->assignedEmployeeInterviewer->id,
                    'name' => $this->assignedEmployeeInterviewer->first_name . ' ' . $this->assignedEmployeeInterviewer->last_name,
                    'email' => $this->assignedEmployeeInterviewer->email,
                    'type' => 'employee'
                ];
            }),
            'interview_access' => $this->interview_access ?? false,
            'is_interviewer' => $this->is_interviewer ?? false,
            'profile_image' => $this->profile_image,
            'rejection_reason' => $this->when($this->status === 'rejected', $this->rejection_reason),
            'status_reason' => $this->getStatusReason(),
            // Training-related data
            'training_assignments' => $this->when($this->trainingAssignments, function () {
                return $this->trainingAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'module_id' => $assignment->module_id,
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at?->toISOString(),
                        'due_date' => $assignment->due_date?->toISOString(),
                        'unlocked_at' => $assignment->unlocked_at?->toISOString(),
                        'started_at' => $assignment->started_at?->toISOString(),
                        'completed_at' => $assignment->completed_at?->toISOString(),
                        'completion_data' => $assignment->completion_data,
                        'reset_count' => $assignment->reset_count,
                        'last_reset_at' => $assignment->last_reset_at?->toISOString(),
                        'notes' => $assignment->notes,
                        'module' => $assignment->module ? [
                            'id' => $assignment->module->id,
                            'title' => $assignment->module->title,
                            'description' => $assignment->module->description,
                            'category' => $assignment->module->category,
                            'duration' => $assignment->module->duration,
                            'qr_code' => $assignment->module->qr_code,
                        ] : null,
                    ];
                });
            }),
            'training_progress' => $this->when($this->trainingProgress, function () {
                return $this->trainingProgress->map(function ($progress) {
                    return [
                        'id' => $progress->id,
                        'assignment_id' => $progress->assignment_id,
                        'module_id' => $progress->module_id,
                        'session_start' => $progress->session_start?->toISOString(),
                        'session_end' => $progress->session_end?->toISOString(),
                        'time_spent_minutes' => $progress->time_spent_minutes,
                        'progress_data' => $progress->progress_data,
                        'is_active' => $progress->isActive(),
                        'session_duration' => $progress->getSessionDuration(),
                    ];
                });
            }),
            'completed_training' => $this->when($this->trainingAssignments, function () {
                return $this->trainingAssignments->where('status', 'completed')->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'module_id' => $assignment->module_id,
                        'completed_at' => $assignment->completed_at?->toISOString(),
                        'completion_data' => $assignment->completion_data,
                        'module_title' => $assignment->module?->title,
                    ];
                });
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * Clean location name by removing "- Woodfire.food" suffix
     */
    private function cleanLocationName($location)
    {
        if (is_string($location)) {
            return preg_replace('/ - Woodfire\.food$/i', '', $location);
        }
        return $location;
    }
}
