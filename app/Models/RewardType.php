<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'value',
        'description',
        'icon',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'value' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Reward type has many employee rewards
     */
    public function employeeRewards()
    {
        return $this->hasMany(EmployeeReward::class);
    }

    /**
     * Scope: Only active reward types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeByType($query, $type)
    {
        if ($type && $type !== 'all') {
            return $query->where('type', $type);
        }
        return $query;
    }
}
