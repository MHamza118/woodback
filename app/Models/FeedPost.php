<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeedPost extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'content',
        'image_url',
        'likes_count',
        'comments_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee that created the post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the comments for the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'post_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the likes for the post.
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'feed_likes',
            'post_id',
            'employee_id'
        )->withTimestamps();
    }

    /**
     * Check if a post is liked by a specific employee.
     */
    public function isLikedBy(Employee $employee): bool
    {
        return $this->likes()->where('employee_id', $employee->id)->exists();
    }

    /**
     * Increment the likes count.
     */
    public function incrementLikesCount(): void
    {
        $this->increment('likes_count');
    }

    /**
     * Decrement the likes count.
     */
    public function decrementLikesCount(): void
    {
        $this->decrement('likes_count');
    }

    /**
     * Increment the comments count.
     */
    public function incrementCommentsCount(): void
    {
        $this->increment('comments_count');
    }

    /**
     * Decrement the comments count.
     */
    public function decrementCommentsCount(): void
    {
        $this->decrement('comments_count');
    }
}
