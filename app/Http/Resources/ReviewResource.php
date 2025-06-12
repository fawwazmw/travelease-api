<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'images' => $this->review_image_public_urls, // Menggunakan accessor dari model
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Memuat relasi lain jika sudah di-load oleh controller
            'user' => new UserResource($this->whenLoaded('user')),
            'destination' => new DestinationResource($this->whenLoaded('destination')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
        ];
    }
}
