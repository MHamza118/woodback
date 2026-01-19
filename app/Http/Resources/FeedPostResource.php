<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        try {
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

            $imageUrl = null;
            if ($this->image_url) {
                $imageUrl = asset('storage/' . $this->image_url);
            }

            $isLiked = false;
            $currentUser = auth('sanctum')->user();
            if ($currentUser && $author) {
                try {
                    $isLiked = $this->isLikedBy($currentUser);
                } catch (\Exception $e) {
                    \Log::warning('Error checking if post is liked: ' . $e->getMessage());
                    $isLiked = false;
                }
            }

            $authorData = $author ? [
                'id' => $author->id,
                'first_name' => $author->first_name ?? '',
                'last_name' => $author->last_name ?? '',
                'name' => trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')),
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
            ];

            return [
                'id' => $this->id,
                'author' => $authorData,
                'content' => $this->content ?? '',
                'image_url' => $imageUrl,
                'likes_count' => $this->likes_count ?? 0,
                'comments_count' => $this->comments_count ?? 0,
                'created_at' => $this->created_at ? $this->created_at->toIso8601String() : now()->toIso8601String(),
                'is_liked' => $isLiked,
                'comments' => FeedCommentResource::collection($this->comments ?? []),
            ];
        } catch (\Exception $e) {
            \Log::error('Error transforming FeedPost resource: ' . $e->getMessage(), ['post_id' => $this->id]);
            throw $e;
        }
    }
}
