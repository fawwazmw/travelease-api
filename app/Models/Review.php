<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'destination_id',
        'booking_id',
        'rating',
        'comment',
        'status',
        'images_urls',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'destination_id' => 'string',
        'booking_id' => 'string',
        'rating' => 'integer',
        'images_urls' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getReviewImagePublicUrlsAttribute(): array
    {
        if (is_array($this->images_urls)) {
            return array_map(function ($imagePath) {
                if(!empty($imagePath)) return Storage::disk('public')->url($imagePath);
                return null;
            }, array_filter($this->images_urls));
        }
        return [];
    }
}
