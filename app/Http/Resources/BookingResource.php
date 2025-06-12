<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'booking_code' => $this->booking_code,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'num_tickets' => $this->num_tickets,
            'total_price' => (float) $this->total_price,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_id_external' => $this->payment_id_external,
            'payment_details' => $this->payment_details,

            // <-- DIPERBARUI: Menggunakan Nullsafe Operator (?->)
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => new UserResource($this->whenLoaded('user')),
            'destination' => new DestinationResource($this->whenLoaded('destination')),
            'slot' => new SlotResource($this->whenLoaded('slot')),
        ];
    }
}
