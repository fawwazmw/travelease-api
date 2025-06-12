<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'initials' => $this->getInitialsAttribute(), // Menggunakan accessor dari model
            'is_verified' => $this->hasVerifiedEmail(), // Memberikan boolean yang jelas untuk frontend
            'email_verified_at' => $this->email_verified_at?->toIso8601String(), // Format timestamp standar
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
