<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadgeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'criteria',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Badge type has many employee badges
     */
    public function employeeBadges()
    {
        return $this->hasMany(EmployeeBadge::class);
    }

    /**
     * Scope: Only active badge types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
