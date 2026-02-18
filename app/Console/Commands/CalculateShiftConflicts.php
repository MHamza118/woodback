<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Schedule;

class CalculateShiftConflicts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shifts:calculate-conflicts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update is_conflict for all shifts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Calculating conflicts for all shifts...');

        // Get all active shifts
        $shifts = Schedule::where('status', 'active')->get();

        $updated = 0;

        foreach ($shifts as $shift) {
            $createdFrom = $shift->created_from ?? 'manual';
            $isConflict = false;

            // Only open_shift type can have conflicts
            if ($shift->employee_id && $createdFrom === 'open_shift') {
                // Count all shifts for this employee on this date
                $count = Schedule::where('employee_id', $shift->employee_id)
                    ->whereDate('date', $shift->date)
                    ->where('status', 'active')
                    ->count();

                $isConflict = $count > 1;
            }

            // Update the shift
            if ($shift->is_conflict !== $isConflict) {
                $shift->update(['is_conflict' => $isConflict]);
                $updated++;
            }
        }

        $this->info("Updated {$updated} shifts with conflict information.");
    }
}
