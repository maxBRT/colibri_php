<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
            'guid' => $this->guid,
            'pub_date' => $this->pub_date?->toAtomString(),
            'source_id' => $this->source_id,
            'category' => $this->category,
            'status' => $this->status,
        ];
    }
}
