<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
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
            'slot_date' => $this->slot_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'available_capacity' => $this->available_capacity, // Menggunakan accessor dari model
            'is_active' => $this->is_active,

            // Memuat relasi destinasi jika sudah di-load oleh controller
            'destination' => new DestinationResource($this->whenLoaded('destination')),
        ];
    }
}
