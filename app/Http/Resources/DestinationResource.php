<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location_address' => $this->location_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'ticket_price' => $this->ticket_price,
            'operational_hours' => $this->operational_hours,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => (int) $this->total_reviews,
            'is_active' => $this->is_active,
            'main_image_url' => $this->main_image_url,
            'image_urls' => $this->image_urls,

            'category' => new CategoryResource($this->whenLoaded('category')),

            // <-- DIPERBARUI: Menggunakan Nullsafe Operator (?->)
            // Ini akan mengembalikan null jika timestamp tidak ada, tanpa menyebabkan error.
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'created_by' => $this->when(
                $request->user()?->role === 'admin',
                new UserResource($this->whenLoaded('creator'))
            ),
        ];
    }
}
