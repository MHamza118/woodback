<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected $client;
    protected $appId;
    protected $restApiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->appId = config('onesignal.app_id');
        $this->restApiKey = config('onesignal.rest_api_key');
        $this->apiUrl = config('onesignal.api_url');
    }

    /**
     * Send notification to all users
     */
    public function sendToAll($title, $message, $data = [], $url = null)
    {
        $payload = [
            'app_id' => $this->appId,
            'included_segments' => ['All'],
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        if ($url) {
            $payload['url'] = $url;
        }

        return $this->sendNotification($payload);
    }

    /**
     * Send notification to specific users by external user IDs
     * This will send to ALL devices of each user (using tags instead of external_user_ids)
     */
    public function sendToUsers($userIds, $title, $message, $data = [], $url = null)
    {
        // Ensure all user IDs are strings (OneSignal requirement)
        $userIds = array_map(function($id) {
            return (string)$id;
        }, $userIds);

        // Build filters to target all devices with matching user_id tags
        // This allows multiple devices per user to receive notifications
        $filters = [];
        foreach ($userIds as $index => $userId) {
            if ($index > 0) {
                $filters[] = ['operator' => 'OR'];
            }
            $filters[] = [
                'field' => 'tag',
                'key' => 'user_id',
                'relation' => '=',
                'value' => $userId
            ];
        }

        $payload = [
            'app_id' => $this->appId,
            'filters' => $filters,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        if ($url) {
            $payload['url'] = $url;
        }

        return $this->sendNotification($payload);
    }

    /**
     * Send notification to users with specific tags
     */
    public function sendToTags($tags, $title, $message, $data = [], $url = null)
    {
        $payload = [
            'app_id' => $this->appId,
            'filters' => $this->buildTagFilters($tags),
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        if ($url) {
            $payload['url'] = $url;
        }

        return $this->sendNotification($payload);
    }

    /**
     * Send notification to admin users
     */
    public function sendToAdmins($title, $message, $data = [], $url = null)
    {
        return $this->sendToTags(['role' => 'owner'], $title, $message, $data, $url);
    }

    /**
     * Send notification to specific employee
     */
    public function sendToEmployee($employeeId, $title, $message, $data = [], $url = null)
    {
        return $this->sendToUsers([$employeeId], $title, $message, $data, $url);
    }

    /**
     * Build tag filters for OneSignal
     */
    protected function buildTagFilters($tags)
    {
        $filters = [];
        $index = 0;

        foreach ($tags as $key => $value) {
            if ($index > 0) {
                $filters[] = ['operator' => 'AND'];
            }
            
            $filters[] = [
                'field' => 'tag',
                'key' => $key,
                'relation' => '=',
                'value' => $value
            ];
            
            $index++;
        }

        return $filters;
    }

    /**
     * Send the actual notification to OneSignal API
     */
    protected function sendNotification($payload)
    {
        try {
            $response = $this->client->post($this->apiUrl . '/notifications', [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->restApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('OneSignal notification sent successfully', [
                'payload' => $payload,
                'response' => $result
            ]);

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $errorResponse = null;

            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
                $errorMessage = $errorResponse['errors'][0] ?? $errorMessage;
            }

            Log::error('OneSignal notification failed', [
                'payload' => $payload,
                'error' => $errorMessage,
                'response' => $errorResponse
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'response' => $errorResponse
            ];
        }
    }

    /**
     * Create or update a user in OneSignal
     */
    public function createUser($externalUserId, $tags = [])
    {
        try {
            $payload = [
                'app_id' => $this->appId,
                'external_user_id' => $externalUserId,
            ];

            if (!empty($tags)) {
                $payload['tags'] = $tags;
            }

            $response = $this->client->post($this->apiUrl . '/players', [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->restApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'data' => $result
            ];

        } catch (RequestException $e) {
            Log::error('OneSignal create user failed', [
                'external_user_id' => $externalUserId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update user tags
     */
    public function updateUserTags($externalUserId, $tags)
    {
        try {
            $response = $this->client->put($this->apiUrl . '/apps/' . $this->appId . '/users/' . $externalUserId, [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->restApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tags' => $tags
                ]
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody()->getContents(), true)
            ];

        } catch (RequestException $e) {
            Log::error('OneSignal update user tags failed', [
                'external_user_id' => $externalUserId,
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}