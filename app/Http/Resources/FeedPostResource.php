<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->getAuthor();
        $currentUser = auth('sanctum')->user();

        $profileImageUrl = null;
        if ($author && $author->profile_image) {
            $profileImageUrl = asset('storage/' . $author->profile_image);
        }

        $isLiked = false;
        if ($currentUser) {
            $isLiked = $this->isLikedBy($currentUser);
        }

        return [
            'id' => $this->id,
            'author' => $author ? [
                'id' => $author->id,
                'first_name' => $author->first_name,
                'last_name' => $author->last_name,
                'name' => $author->first_name . ' ' . $author->last_name,
                'avatar_url' => $profileImageUrl,
                'profile_image' => $profileImageUrl,
                'role' => $this->author_type === 'admin' ? ($author->role ?? 'admin') : 'employee',
            ] : null,
            'content' => $this->content,
            'image_url' => $this->image_url ? asset('storage/' . $this->image_url) : null,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'created_at' => $this->created_at->toIso8601String(),
            'is_liked' => $isLiked,
            'comments' => FeedCommentResource::collection($this->comments),
        ];
    }
}
