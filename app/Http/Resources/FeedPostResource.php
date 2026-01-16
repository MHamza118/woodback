<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->first_name . ' ' . $this->author->last_name,
                'avatar_url' => $this->author->avatar_url ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $this->author->id,
            ],
            'content' => $this->content,
            'image_url' => $this->image_url,
            'likes' => $this->likes_count,
            'comments' => $this->comments_count,
            'created_at' => $this->created_at->diffForHumans(),
            'is_liked' => $currentEmployee ? $this->isLikedBy($currentEmployee) : false,
            'comment_list' => FeedCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
