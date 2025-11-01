<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message', 
        'order_number',
        'table_number',
        'customer_name',
        'location',
        'priority',
        'recipient_type', // 'admin' or 'employee'
        'recipient_id',   // admin_id or employee_id
        'data',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Type constants
    const TYPE_NEW_ORDER = 'new_order';
    const TYPE_ORDER_UPDATED = 'order_updated';
    const TYPE_ORDER_READY = 'order_ready';
    const TYPE_ORDER_DELIVERED = 'order_delivered';
    const TYPE_TABLE_CHANGED = 'table_changed';
    const TYPE_NEW_SIGNUP = 'NEW_SIGNUP';
    const TYPE_ONBOARDING_COMPLETE = 'ONBOARDING_COMPLETE';
    const TYPE_SHOUTOUT_RECEIVED = 'shoutout_received';
    const TYPE_REWARD_RECEIVED = 'reward_received';
    const TYPE_BADGE_RECEIVED = 'badge_received';
    const TYPE_TRAINING_ASSIGNED = 'training_assigned';
    const TYPE_TRAINING_COMPLETED = 'training_completed';
    const TYPE_TIME_OFF_REQUEST = 'time_off_request';
    const TYPE_NEW_TICKET = 'new_ticket';
    const TYPE_TICKET_STATUS_UPDATE = 'ticket_status_update';
    const TYPE_TICKET_RESPONSE = 'ticket_response';
    const TYPE_TICKET_ARCHIVED = 'ticket_archived';
    const TYPE_PERFORMANCE_REVIEW_OVERDUE = 'performance_review_overdue';
    const TYPE_PERFORMANCE_REVIEW_DUE_SOON = 'performance_review_due_soon';

    // Priority constants
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    // Recipient type constants
    const RECIPIENT_ADMIN = 'admin';
    const RECIPIENT_EMPLOYEE = 'employee';

    public static function getValidTypes()
    {
        return [
            self::TYPE_NEW_ORDER,
            self::TYPE_ORDER_UPDATED,
            self::TYPE_ORDER_READY,
            self::TYPE_ORDER_DELIVERED,
            self::TYPE_TABLE_CHANGED,
            self::TYPE_NEW_SIGNUP,
            self::TYPE_ONBOARDING_COMPLETE,
            self::TYPE_SHOUTOUT_RECEIVED,
            self::TYPE_REWARD_RECEIVED,
            self::TYPE_BADGE_RECEIVED,
            self::TYPE_TRAINING_ASSIGNED,
            self::TYPE_TRAINING_COMPLETED,
            self::TYPE_TIME_OFF_REQUEST,
            self::TYPE_NEW_TICKET,
            self::TYPE_TICKET_STATUS_UPDATE,
            self::TYPE_TICKET_RESPONSE,
            self::TYPE_TICKET_ARCHIVED,
            self::TYPE_PERFORMANCE_REVIEW_OVERDUE,
            self::TYPE_PERFORMANCE_REVIEW_DUE_SOON
        ];
    }

    public static function getValidPriorities()
    {
        return [
            self::PRIORITY_HIGH,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_LOW
        ];
    }

    public static function getValidRecipientTypes()
    {
        return [
            self::RECIPIENT_ADMIN,
            self::RECIPIENT_EMPLOYEE
        ];
    }

    // Relationships
    public function mapping()
    {
        return $this->belongsTo(TableMapping::class, 'order_number', 'order_number');
    }

    public function order()
    {
        return $this->belongsTo(TableOrder::class, 'order_number', 'order_number');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query) 
    {
        return $query->where('is_read', true);
    }

    public function scopeForAdmin($query, $adminId = null)
    {
        $query->where('recipient_type', self::RECIPIENT_ADMIN);
        
        if ($adminId) {
            $query->where('recipient_id', $adminId);
        }
        
        return $query;
    }

    public function scopeForEmployee($query, $employeeId = null)
    {
        $query->where('recipient_type', self::RECIPIENT_EMPLOYEE);
        
        if ($employeeId) {
            $query->where('recipient_id', $employeeId);
        }
        
        return $query;
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Helper methods
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function isUnread()
    {
        return !$this->is_read;
    }

    public function isHighPriority()
    {
        return $this->priority === self::PRIORITY_HIGH;
    }

    public function isMediumPriority()
    {
        return $this->priority === self::PRIORITY_MEDIUM;
    }

    public function isLowPriority()
    {
        return $this->priority === self::PRIORITY_LOW;
    }

    public function getFormattedTimeAttribute()
    {
        $diffInMinutes = $this->created_at->diffInMinutes(now());
        
        if ($diffInMinutes < 1) {
            return 'Just now';
        }
        
        if ($diffInMinutes < 60) {
            return $diffInMinutes . 'm ago';
        }
        
        if ($diffInMinutes < 1440) { // 24 hours
            return floor($diffInMinutes / 60) . 'h ago';
        }
        
        return $this->created_at->toDateString();
    }
}
