<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FeedCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Generate profile image URL from database
        $profileImageUrl = null;
        if ($this->author->profile_image) {
            $profileImageUrl = Storage::disk('public')->url($this->author->profile_image);
        }

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->first_name . ' ' . $this->author->last_name,
                'avatar_url' => $profileImageUrl ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $this->author->id,
            ],
            'content' => $this->content,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
