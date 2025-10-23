<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reward_type_id',
        'given_by',
        'reason',
        'status',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Reward belongs to employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Reward belongs to reward type
     */
    public function rewardType()
    {
        return $this->belongsTo(RewardType::class);
    }

    /**
     * Relationship: Reward given by admin/user
     */
    public function givenBy()
    {
        return $this->belongsTo(Admin::class, 'given_by');
    }

    /**
     * Scope: Pending rewards
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Redeemed rewards
     */
    public function scopeRedeemed($query)
    {
        return $query->where('status', 'redeemed');
    }

    /**
     * Scope: Filter by employee
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Mark reward as redeemed
     */
    public function markAsRedeemed()
    {
        return $this->update([
            'status' => 'redeemed',
            'redeemed_at' => now(),
        ]);
    }
}
