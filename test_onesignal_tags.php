<?php

/**
 * OneSignal Tag Testing Script
 * 
 * This script helps debug OneSignal tag-based notifications
 * Run: php test_onesignal_tags.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Load environment
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get OneSignal credentials
$appId = env('ONESIGNAL_APP_ID');
$restApiKey = env('ONESIGNAL_REST_API_KEY');

if (empty($appId) || empty($restApiKey)) {
    die("ERROR: OneSignal credentials not found in .env file\n");
}

echo "===========================================\n";
echo "OneSignal Tag Testing Script\n";
echo "===========================================\n\n";

echo "App ID: " . substr($appId, 0, 10) . "...\n";
echo "API Key: " . substr($restApiKey, 0, 10) . "...\n\n";

// Test 1: Send to role: owner
echo "TEST 1: Sending notification to role: owner\n";
echo "-------------------------------------------\n";

$payload1 = [
    'app_id' => $appId,
    'filters' => [
        [
            'field' => 'tag',
            'key' => 'role',
            'relation' => '=',
            'value' => 'owner'
        ]
    ],
    'headings' => ['en' => 'Test: Role Owner'],
    'contents' => ['en' => 'This notification targets devices with role=owner tag'],
];

echo "Payload:\n";
echo json_encode($payload1, JSON_PRETTY_PRINT) . "\n\n";

$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
curl_setopt($ch1, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $restApiKey
]);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($payload1));

$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "Response (HTTP $httpCode1):\n";
echo json_encode(json_decode($response1), JSON_PRETTY_PRINT) . "\n\n";

$result1 = json_decode($response1, true);
if (isset($result1['recipients'])) {
    echo "✅ SUCCESS: Notification sent to {$result1['recipients']} device(s)\n\n";
} else {
    echo "❌ FAILED: " . ($result1['errors'][0] ?? 'Unknown error') . "\n\n";
}

// Test 2: Send to specific user_id
echo "TEST 2: Sending notification to specific user_id\n";
echo "-------------------------------------------\n";
echo "Enter the admin user ID (from database): ";
$userId = trim(fgets(STDIN));

if (empty($userId)) {
    echo "Skipping Test 2 (no user ID provided)\n\n";
} else {
    $payload2 = [
        'app_id' => $appId,
        'filters' => [
            [
                'field' => 'tag',
                'key' => 'user_id',
                'relation' => '=',
                'value' => $userId
            ]
        ],
        'headings' => ['en' => 'Test: User ID ' . $userId],
        'contents' => ['en' => 'This notification targets devices with user_id=' . $userId . ' tag'],
    ];

    echo "Payload:\n";
    echo json_encode($payload2, JSON_PRETTY_PRINT) . "\n\n";

    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $restApiKey
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload2));

    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    echo "Response (HTTP $httpCode2):\n";
    echo json_encode(json_decode($response2), JSON_PRETTY_PRINT) . "\n\n";

    $result2 = json_decode($response2, true);
    if (isset($result2['recipients'])) {
        echo "✅ SUCCESS: Notification sent to {$result2['recipients']} device(s)\n\n";
        echo "This shows how many devices have user_id=$userId tag set.\n";
        echo "If this is less than your actual devices, those devices didn't set tags properly.\n\n";
    } else {
        echo "❌ FAILED: " . ($result2['errors'][0] ?? 'Unknown error') . "\n\n";
    }
}

echo "===========================================\n";
echo "DEBUGGING TIPS:\n";
echo "===========================================\n";
echo "1. Check browser console on each device for:\n";
echo "   - 'Setting OneSignal tags: {user_id: X, role: owner}'\n";
echo "   - 'OneSignal user identity set successfully'\n\n";
echo "2. If Test 1 shows 0 recipients:\n";
echo "   - No devices have role=owner tag\n";
echo "   - Check frontend OneSignal initialization\n\n";
echo "3. If Test 2 shows fewer recipients than devices:\n";
echo "   - Some devices didn't set user_id tag\n";
echo "   - Check if OneSignal.login() is being called\n\n";
echo "4. Common issues:\n";
echo "   - Browser blocking notifications\n";
echo "   - Service worker not registered\n";
echo "   - OneSignal SDK not loaded\n";
echo "   - Tags not set after login\n\n";
