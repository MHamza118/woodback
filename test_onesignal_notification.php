<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\OneSignalService;

echo "Testing OneSignal Notification...\n\n";

$oneSignal = new OneSignalService();

// Get user ID from command line argument
$userId = isset($argv[1]) ? (int)$argv[1] : 1;

echo "Sending to user ID: $userId\n\n";

$result = $oneSignal->sendToUsers(
    [$userId],
    'Test Notification',
    "This is a test message to user $userId",
    ['type' => 'test'],
    'https://woodfire.food'
);

echo "Result:\n";
print_r($result);
echo "\n";

if ($result['success']) {
    echo "✅ Notification sent successfully!\n";
    echo "Check your device for the notification.\n";
} else {
    echo "❌ Failed to send notification\n";
    echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
}
