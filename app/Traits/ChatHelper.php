<?php

namespace App\Traits;

/**
 * ChatHelper Trait
 * 
 * Provides common helper methods for chat/message operations
 * Used by services and controllers to determine user type, ID, and sender name
 */
trait ChatHelper
{
    /**
     * Determine user type (admin or employee)
     */
    protected function getUserType($user): string
    {
        if ($user instanceof \App\Models\Admin) {
            return 'admin';
        } elseif ($user instanceof \App\Models\Employee) {
            return 'employee';
        }
        
        // Fallback: check the user's class name
        if (get_class($user) === 'App\\Models\\Admin') {
            return 'admin';
        }
        
        return 'employee';
    }

    /**
     * Get user ID based on user type
     */
    protected function getUserId($user, $userType): string
    {
        if ($userType === 'admin') {
            return 'admin'; // Use 'admin' as the standard admin ID
        }
        
        return (string)$user->id;
    }

    /**
     * Get sender name based on user type
     */
    protected function getSenderName($user, $userType): string
    {
        if ($userType === 'admin') {
            return 'Management';
        }
        
        // For employees, get name from direct fields or profile_data
        if ($user) {
            $firstName = $user->first_name ?? null;
            $lastName = $user->last_name ?? null;
            
            // Fallback to profile_data if direct fields are empty
            if (empty($firstName) && !empty($user->profile_data)) {
                $profileData = is_array($user->profile_data) 
                    ? $user->profile_data 
                    : json_decode($user->profile_data, true);
                
                $firstName = $profileData['firstName'] ?? $profileData['first_name'] ?? 'Employee';
                $lastName = $profileData['lastName'] ?? $profileData['last_name'] ?? '';
            }
            
            if (!empty($firstName) || !empty($lastName)) {
                return trim(($firstName ?? 'Employee') . ' ' . ($lastName ?? ''));
            }
        }
        
        return 'Employee';
    }

    /**
     * Format message data for API response
     */
    protected function formatMessageData($message): array
    {
        return [
            'id' => $message->id,
            'senderId' => $message->sender_id,
            'senderName' => $message->sender_name,
            'senderRole' => $message->sender_type === 'admin' ? 'Admin' : 'Employee',
            'content' => $message->content,
            'attachments' => $message->attachments,
            'hasAttachments' => $message->has_attachments,
            'textContent' => $message->content,
            'timestamp' => $message->created_at->toISOString()
        ];
    }
}
