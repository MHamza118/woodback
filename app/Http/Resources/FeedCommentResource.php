<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = null;
        
        // Try to get author from loaded relationships first
        if ($this->author_type === 'admin' && $this->relationLoaded('adminAuthor')) {
            $author = $this->adminAuthor;
        } elseif ($this->author_type === 'employee' && $this->relationLoaded('employeeAuthor')) {
            $author = $this->employeeAuthor;
        } else {
            // Fallback to getAuthor if relationships not loaded
            $author = $this->getAuthor();
        }

        $profileImageUrl = null;
        if ($author && $author->profile_image) {
            $profileImageUrl = asset('storage/' . $author->profile_image);
        }

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'author' => $author ? [
                'id' => $author->id,
                'first_name' => $author->first_name ?? '',
                'last_name' => $author->last_name ?? '',
                'name' => ($author->first_name ?? '') . ' ' . ($author->last_name ?? ''),
                'avatar_url' => $profileImageUrl,
                'profile_image' => $profileImageUrl,
                'role' => $this->author_type === 'admin' ? ($author->role ?? 'admin') : 'employee',
            ] : [
                'id' => $this->author_id,
                'first_name' => 'Unknown',
                'last_name' => 'User',
                'name' => 'Unknown User',
                'avatar_url' => null,
                'profile_image' => null,
                'role' => $this->author_type,
            ],
            'content' => $this->content,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
