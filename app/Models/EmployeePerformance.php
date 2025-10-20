<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePerformance extends Model
{
    use HasFactory;

    protected $table = 'employee_performance';

    protected $fillable = [
        'employee_id',
        'total_points',
        'total_shoutouts',
        'total_rewards',
        'total_badges',
        'last_updated',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'total_shoutouts' => 'integer',
        'total_rewards' => 'integer',
        'total_badges' => 'integer',
        'last_updated' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Performance belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Update performance counters
     */
    public function incrementShoutout()
    {
        $this->increment('total_shoutouts');
        $this->increment('total_points', 25); // Base points for shoutout
        $this->update(['last_updated' => now()]);
    }

    public function incrementReward($points = 0)
    {
        $this->increment('total_rewards');
        if ($points > 0) {
            $this->increment('total_points', $points);
        }
        $this->update(['last_updated' => now()]);
    }

    public function incrementBadge()
    {
        $this->increment('total_badges');
        $this->increment('total_points', 100); // Base points for badge
        $this->update(['last_updated' => now()]);
    }
}
