<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->first_name . ' ' . $this->author->last_name,
                'avatar_url' => $this->author->avatar_url ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $this->author->id,
            ],
            'content' => $this->content,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
