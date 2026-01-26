<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'artist_id' => $this->artist_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null,
            'sell_through_ltdedn' => $this->sell_through_ltdedn,
            'is_limited' => $this->is_limited,
            'edition_size' => $this->edition_size,
            'base_price' => $this->base_price,
            'is_public' => $this->is_public,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'artist' => $this->whenLoaded('artist'),
            'editions' => $this->whenLoaded('editions'),
            'editions_count' => $this->when(isset($this->editions_count), $this->editions_count),
        ];
    }
}
