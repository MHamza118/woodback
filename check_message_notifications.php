<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TableNotification;

try {
    echo "Checking message notifications...\n\n";
    
    $messageNotifs = TableNotification::where('type', 'new_message')->get();
    echo "Total message notifications: " . $messageNotifs->count() . "\n\n";
    
    if ($messageNotifs->count() > 0) {
        echo "Recent message notifications:\n";
        foreach($messageNotifs->take(5) as $notif) {
            echo "  ID: {$notif->id} | Recipient: {$notif->recipient_type} #{$notif->recipient_id} | Read: " . ($notif->is_read ? 'Yes' : 'No') . " | Created: {$notif->created_at}\n";
        }
    } else {
        echo "No message notifications found. The feature was just added, so notifications will only be created for NEW messages sent after this code change.\n";
    }
    
} catch(\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
