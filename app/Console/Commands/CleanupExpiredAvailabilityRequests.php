<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AvailabilityRequest;
use Carbon\Carbon;

class CleanupExpiredAvailabilityRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'availability:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired temporary availability requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired temporary availability requests...');

        // Find temporary requests that have passed their effective_to date
        $expiredRequests = AvailabilityRequest::where('type', 'temporary')
            ->where('status', 'approved')
            ->whereNotNull('effective_to')
            ->where('effective_to', '<', Carbon::now()->toDateString())
            ->get();

        $count = $expiredRequests->count();

        if ($count === 0) {
            $this->info('No expired temporary availability requests found.');
            return;
        }

        // Delete expired requests
        foreach ($expiredRequests as $request) {
            $this->line("Deleting expired request ID {$request->id} for employee {$request->employee->email} (expired: {$request->effective_to})");
            $request->delete();
        }

        $this->info("Successfully cleaned up {$count} expired temporary availability requests.");
    }
}