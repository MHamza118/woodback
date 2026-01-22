<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'color',
        'repeat_type',
        'repeat_end_date',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'repeat_end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid();
            }
        });
    }

    /**
     * Get the admin who created this event
     */
    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Scope: Get active events
     */
    public function scopeActive($query)
    {
        return $query->where('start_date', '<=', now()->toDateString())
                     ->where('end_date', '>=', now()->toDateString());
    }

    /**
     * Scope: Get upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now()->toDateString());
    }

    /**
     * Scope: Get events for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
                     ->where('end_date', '>=', $date);
    }

    /**
     * Scope: Get events for a date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '<=', $endDate)
                     ->where('end_date', '>=', $startDate);
    }

    /**
     * Get all occurrences of this event within a date range
     */
    public function getOccurrences($startDate, $endDate)
    {
        $occurrences = [];
        $currentDate = $this->start_date;

        while ($currentDate <= $endDate && $currentDate <= ($this->repeat_end_date ?? $this->end_date)) {
            if ($currentDate >= $startDate) {
                $occurrences[] = [
                    'id' => $this->id,
                    'title' => $this->title,
                    'description' => $this->description,
                    'date' => $currentDate->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'color' => $this->color,
                    'repeat_type' => $this->repeat_type,
                    'created_by' => $this->created_by,
                ];
            }

            // Calculate next occurrence based on repeat type
            switch ($this->repeat_type) {
                case 'daily':
                    $currentDate = $currentDate->addDay();
                    break;
                case 'weekly':
                    $currentDate = $currentDate->addWeek();
                    break;
                case 'monthly':
                    $currentDate = $currentDate->addMonth();
                    break;
                default:
                    break 2; // Exit loop if no repeat
            }
        }

        return $occurrences;
    }
}
