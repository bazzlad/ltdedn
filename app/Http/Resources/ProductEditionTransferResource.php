<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductEditionTransferResource extends JsonResource
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
            'token' => $this->token,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'product_edition' => new ProductEditionResource($this->whenLoaded('productEdition')),
            'sender' => $this->whenLoaded('sender'),
            'recipient' => $this->whenLoaded('recipient'),
        ];
    }
}
