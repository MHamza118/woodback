<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OneSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OneSignalController extends Controller
{
    protected $oneSignalService;

    public function __construct(OneSignalService $oneSignalService)
    {
        $this->oneSignalService = $oneSignalService;
    }

    /**
     * Register user for push notifications
     */
    public function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_user_id' => 'required|string',
            'tags' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $tags = $request->get('tags', []);
        
        // Add user role as tag
        if ($user) {
            $tags['role'] = $user->role ?? $user->userType ?? 'employee';
            $tags['user_id'] = $user->id;
        }

        $result = $this->oneSignalService->createUser(
            $request->external_user_id,
            $tags
        );

        return response()->json($result);
    }

    /**
     * Update user tags
     */
    public function updateUserTags(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_user_id' => 'required|string',
            'tags' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->oneSignalService->updateUserTags(
            $request->external_user_id,
            $request->tags
        );

        return response()->json($result);
    }

    /**
     * Send test notification (admin only)
     */
    public function sendTestNotification(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->role ?? $user->userType, ['admin', 'owner'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'recipients' => 'required|string|in:all,admins,employees,expo',
            'url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'type' => 'test_notification',
            'sent_by' => $user->first_name . ' ' . $user->last_name,
            'timestamp' => now()->toISOString()
        ];

        switch ($request->recipients) {
            case 'all':
                $result = $this->oneSignalService->sendToAll(
                    $request->title,
                    $request->message,
                    $data,
                    $request->url
                );
                break;
            case 'admins':
                $result = $this->oneSignalService->sendToTags(
                    ['role' => 'admin'],
                    $request->title,
                    $request->message,
                    $data,
                    $request->url
                );
                break;
            case 'employees':
                $result = $this->oneSignalService->sendToTags(
                    ['role' => 'employee'],
                    $request->title,
                    $request->message,
                    $data,
                    $request->url
                );
                break;
            case 'expo':
                $result = $this->oneSignalService->sendToTags(
                    ['role' => 'expo'],
                    $request->title,
                    $request->message,
                    $data,
                    $request->url
                );
                break;
        }

        return response()->json($result);
    }

    /**
     * Get OneSignal app configuration for frontend
     */
    public function getConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('onesignal.app_id'),
                'safari_web_id' => config('onesignal.safari_web_id', null)
            ]
        ]);
    }
}