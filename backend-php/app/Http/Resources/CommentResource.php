<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->relationLoaded('user') ? $this->user?->username : null,
            'full_name' => $this->relationLoaded('user') ? $this->user?->full_name : null,
            'user_full_name' => $this->relationLoaded('user') ? $this->user?->full_name : null,
            'product_id' => $this->product_id,
            'content' => $this->content,
            'is_staff_reply' => (bool) $this->is_staff_reply,
            'parent_comment_id' => $this->parent_comment_id,
            'replies' => $this->relationLoaded('replies') ? CommentResource::collection($this->replies)->resolve($request) : [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
