<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_type',
        'author_id',
        'content',
        'image_url',
        'likes_count',
        'comments_count',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the author (employee or admin)
     */
    public function getAuthor()
    {
        try {
            if ($this->author_type === 'admin') {
                return Admin::find($this->author_id);
            }
            return Employee::find($this->author_id);
        } catch (\Exception $e) {
            \Log::error('Error getting author for post ' . $this->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the admin author
     */
    public function adminAuthor()
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    /**
     * Get the employee author
     */
    public function employeeAuthor()
    {
        return $this->belongsTo(Employee::class, 'author_id');
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
    public function likes(): HasMany
    {
        return $this->hasMany(FeedLike::class, 'post_id');
    }

    /**
     * Check if a post is liked by a specific user.
     */
    public function isLikedBy($user): bool
    {
        $userType = $user instanceof Admin ? 'admin' : 'employee';
        return $this->likes()->where('user_id', $user->id)->where('user_type', $userType)->exists();
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
