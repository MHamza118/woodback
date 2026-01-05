<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'type',
        'start_date',
        'end_date',
        'created_by',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Type constants
    const TYPE_GENERAL = 'general';
    const TYPE_URGENT = 'urgent';
    const TYPE_EVENT = 'event';
    const TYPE_POLICY = 'policy';

    public static function getValidTypes()
    {
        return [
            self::TYPE_GENERAL,
            self::TYPE_URGENT,
            self::TYPE_EVENT,
            self::TYPE_POLICY
        ];
    }

    /**
     * Get the admin who created this announcement
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Scope for active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if announcement is currently active
     */
    public function isCurrentlyActive()
    {
        return $this->is_active 
            && $this->start_date <= now() 
            && ($this->end_date === null || $this->end_date >= now());
    }
}
