<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'author_type',
        'author_id',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post that the comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    /**
     * Get the author (employee or admin)
     */
    public function getAuthor()
    {
        if ($this->author_type === 'admin') {
            return Admin::find($this->author_id);
        }
        return Employee::find($this->author_id);
    }
}
