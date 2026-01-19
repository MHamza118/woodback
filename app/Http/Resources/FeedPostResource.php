<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FeedPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentEmployee = auth('sanctum')->user();

        // Generate full image URL if image exists
        $imageUrl = null;
        if ($this->image_url) {
            $imageUrl = Storage::disk('public')->url($this->image_url);
        }

        // Generate profile image URL from database
        $profileImageUrl = null;
        if ($this->author->profile_image) {
            $profileImageUrl = Storage::disk('public')->url($this->author->profile_image);
        }

        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->author->id,
                'first_name' => $this->author->first_name,
                'last_name' => $this->author->last_name,
                'name' => $this->author->first_name . ' ' . $this->author->last_name,
                'avatar_url' => $profileImageUrl ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $this->author->id,
                'profile_image' => $this->author->profile_image,
                'role' => 'employee',
            ],
            'content' => $this->content,
            'image_url' => $imageUrl,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'created_at' => $this->created_at->toIso8601String(),
            'is_liked' => $currentEmployee ? $this->isLikedBy($currentEmployee) : false,
            'comments' => FeedCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
