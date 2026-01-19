<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_type',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post that was liked.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    /**
     * Get the user (employee or admin) that liked the post.
     */
    public function getUser()
    {
        if ($this->user_type === 'admin') {
            return Admin::find($this->user_id);
        }
        return Employee::find($this->user_id);
    }
}
