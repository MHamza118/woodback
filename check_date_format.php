<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Schedule;
use Carbon\Carbon;

echo "=== Checking Date Format Issue ===\n\n";

$weekStart = Carbon::parse('2026-02-09');
$weekEnd = Carbon::parse('2026-02-15');

echo "Week start: {$weekStart->toDateString()} (type: " . gettype($weekStart) . ")\n";
echo "Week end: {$weekEnd->toDateString()} (type: " . gettype($weekEnd) . ")\n\n";

// Get ALL shifts in the week
$allShifts = Schedule::forWeek($weekStart, $weekEnd)->get();

echo "All shifts in forWeek: {$allShifts->count()}\n\n";

foreach ($allShifts as $shift) {
    $dateObj = $shift->date;
    echo "ID {$shift->id}: date={$dateObj->toDateString()}, raw_date=" . var_export($dateObj, true) . ", employee_id={$shift->employee_id}\n";
}

echo "\n=== Check Monday shift specifically ===\n";

$mondayShift = Schedule::where('date', '2026-02-09')->where('employee_id', 1)->first();

if ($mondayShift) {
    echo "Monday shift found:\n";
    echo "  ID: {$mondayShift->id}\n";
    echo "  Date: {$mondayShift->date->toDateString()}\n";
    echo "  Date raw: " . var_export($mondayShift->date, true) . "\n";
    echo "  Date >= weekStart: " . ($mondayShift->date >= $weekStart ? 'YES' : 'NO') . "\n";
    echo "  Date <= weekEnd: " . ($mondayShift->date <= $weekEnd ? 'YES' : 'NO') . "\n";
}

echo "\n=== Check if forWeek includes Monday ===\n";

$mondayInForWeek = Schedule::forWeek($weekStart, $weekEnd)
    ->where('id', 84)
    ->first();

if ($mondayInForWeek) {
    echo "Monday shift IS in forWeek results\n";
} else {
    echo "Monday shift NOT in forWeek results\n";
}
