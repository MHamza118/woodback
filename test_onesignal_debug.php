<?php

/**
 * OneSignal Complete Debug Script
 * 
 * This script tests ALL OneSignal notification methods to identify the exact issue
 * Run: php test_onesignal_debug.php
 */

require __DIR__ . '/vendor/autoload.php';

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
echo "OneSignal Complete Debug Script\n";
echo "===========================================\n\n";

echo "App ID: " . substr($appId, 0, 10) . "...\n";
echo "API Key: " . substr($restApiKey, 0, 10) . "...\n\n";

// Function to send notification
function sendNotification($appId, $restApiKey, $payload, $testName) {
    echo "\n===========================================\n";
    echo "TEST: $testName\n";
    echo "===========================================\n";
    echo "Payload:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $restApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Response (HTTP $httpCode):\n";
    $result = json_decode($response, true);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    if (isset($result['recipients'])) {
        echo "✅ SUCCESS: Notification sent to {$result['recipients']} device(s)\n";
        if ($result['recipients'] == 0) {
            echo "⚠️  WARNING: 0 recipients means no devices matched the filter\n";
        }
    } else {
        echo "❌ FAILED: " . ($result['errors'][0] ?? 'Unknown error') . "\n";
    }
    
    return $result;
}

// TEST 1: Send to ALL devices (included_segments)
echo "\n";
echo "TEST 1: Send to ALL devices\n";
echo "-------------------------------------------\n";
echo "This tests if ANY device can receive notifications\n";

$payload1 = [
    'app_id' => $appId,
    'included_segments' => ['All'],
    'headings' => ['en' => 'Test 1: All Devices'],
    'contents' => ['en' => 'If you see this, OneSignal is working!'],
];

$result1 = sendNotification($appId, $restApiKey, $payload1, 'Send to All Devices');

if (isset($result1['recipients']) && $result1['recipients'] > 0) {
    echo "\n✅ GOOD: At least some devices are registered and can receive notifications\n";
} else {
    echo "\n❌ PROBLEM: No devices received notification. Check:\n";
    echo "   1. Are devices actually subscribed in OneSignal dashboard?\n";
    echo "   2. Is push permission granted in browser?\n";
    echo "   3. Is service worker registered?\n";
}

// TEST 2: Send to specific external user ID
echo "\n\nEnter the user ID to test (e.g., 1): ";
$userId = trim(fgets(STDIN));

if (empty($userId)) {
    echo "Skipping user ID tests\n";
    exit(0);
}

echo "\n";
echo "TEST 2: Send to external_user_id = '$userId'\n";
echo "-------------------------------------------\n";
echo "This tests if external user ID is set correctly\n";

$payload2 = [
    'app_id' => $appId,
    'include_external_user_ids' => [$userId],
    'headings' => ['en' => 'Test 2: External User ID'],
    'contents' => ['en' => "This targets external_user_id = $userId"],
];

$result2 = sendNotification($appId, $restApiKey, $payload2, "Send to External User ID: $userId");

if (isset($result2['recipients']) && $result2['recipients'] > 0) {
    echo "\n✅ GOOD: External user ID '$userId' is set correctly\n";
    echo "   Devices with this external ID: {$result2['recipients']}\n";
} else {
    echo "\n❌ PROBLEM: No devices have external_user_id = '$userId'\n";
    echo "   This means OneSignal.login('$userId') is NOT working\n";
    echo "   Check frontend console for login errors\n";
}

// TEST 3: Send to user_id tag
echo "\n";
echo "TEST 3: Send to tag user_id = '$userId'\n";
echo "-------------------------------------------\n";
echo "This tests if tags are set correctly\n";

$payload3 = [
    'app_id' => $appId,
    'filters' => [
        [
            'field' => 'tag',
            'key' => 'user_id',
            'relation' => '=',
            'value' => $userId
        ]
    ],
    'headings' => ['en' => 'Test 3: User ID Tag'],
    'contents' => ['en' => "This targets tag user_id = $userId"],
];

$result3 = sendNotification($appId, $restApiKey, $payload3, "Send to Tag user_id = $userId");

if (isset($result3['recipients']) && $result3['recipients'] > 0) {
    echo "\n✅ GOOD: Tag user_id = '$userId' is set correctly\n";
    echo "   Devices with this tag: {$result3['recipients']}\n";
} else {
    echo "\n❌ PROBLEM: No devices have tag user_id = '$userId'\n";
    echo "   This means OneSignal.User.addTags() is NOT working\n";
    echo "   Check frontend console for tag setting errors\n";
}

// TEST 4: Send to role tag
echo "\n";
echo "TEST 4: Send to tag role = 'owner'\n";
echo "-------------------------------------------\n";
echo "This tests if role tags are set correctly\n";

$payload4 = [
    'app_id' => $appId,
    'filters' => [
        [
            'field' => 'tag',
            'key' => 'role',
            'relation' => '=',
            'value' => 'owner'
        ]
    ],
    'headings' => ['en' => 'Test 4: Role Tag'],
    'contents' => ['en' => 'This targets tag role = owner'],
];

$result4 = sendNotification($appId, $restApiKey, $payload4, "Send to Tag role = owner");

if (isset($result4['recipients']) && $result4['recipients'] > 0) {
    echo "\n✅ GOOD: Tag role = 'owner' is set correctly\n";
    echo "   Devices with this tag: {$result4['recipients']}\n";
} else {
    echo "\n❌ PROBLEM: No devices have tag role = 'owner'\n";
    echo "   This means role tag is not being set\n";
}

// SUMMARY
echo "\n\n===========================================\n";
echo "SUMMARY & DIAGNOSIS\n";
echo "===========================================\n\n";

$allDevices = $result1['recipients'] ?? 0;
$externalIdDevices = $result2['recipients'] ?? 0;
$userIdTagDevices = $result3['recipients'] ?? 0;
$roleTagDevices = $result4['recipients'] ?? 0;

echo "Total devices registered: $allDevices\n";
echo "Devices with external_user_id = '$userId': $externalIdDevices\n";
echo "Devices with tag user_id = '$userId': $userIdTagDevices\n";
echo "Devices with tag role = 'owner': $roleTagDevices\n\n";

if ($allDevices == 0) {
    echo "❌ CRITICAL: No devices are registered at all\n";
    echo "   Fix: Check OneSignal initialization in frontend\n\n";
} elseif ($externalIdDevices == 0) {
    echo "❌ CRITICAL: OneSignal.login() is NOT working\n";
    echo "   Fix: Check frontend console for login errors\n";
    echo "   Fix: Verify OneSignal SDK version compatibility\n\n";
} elseif ($userIdTagDevices == 0) {
    echo "❌ CRITICAL: Tags are NOT being set\n";
    echo "   Fix: Check frontend console for tag setting errors\n\n";
} else {
    echo "✅ Everything looks good!\n";
    echo "   If notifications still don't arrive, check:\n";
    echo "   1. Browser notification permissions\n";
    echo "   2. Service worker registration\n";
    echo "   3. Foreground notification display logic\n\n";
}

echo "===========================================\n";
echo "NEXT STEPS\n";
echo "===========================================\n";
echo "1. Check frontend console logs for detailed errors\n";
echo "2. Verify external user ID in OneSignal dashboard\n";
echo "3. Test notification from OneSignal dashboard directly\n";
echo "4. Check browser DevTools > Application > Service Workers\n";
echo "5. Verify push permission is 'granted' in browser\n\n";
