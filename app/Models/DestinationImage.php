<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DestinationImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'destination_id',
        'image_url',
        'caption',
        'is_primary',
    ];

    protected $casts = [
        'id' => 'string',
        'destination_id' => 'string',
        'is_primary' => 'boolean',
    ];

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function getPublicUrlAttribute(): ?string
    {
        if ($this->image_url) {
            return Storage::disk('public')->url($this->image_url);
        }
        return null;
    }
}
