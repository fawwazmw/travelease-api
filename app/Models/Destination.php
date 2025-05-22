<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Destination extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location_address',
        'latitude',
        'longitude',
        'category_id',
        'created_by',
        'ticket_price',
        'operational_hours',
        'contact_phone',
        'contact_email',
        'average_rating',
        'total_reviews',
        'is_active',
        'images',
    ];

    protected $casts = [
        'id' => 'string',
        'category_id' => 'string',
        'created_by' => 'string',
        'is_active' => 'boolean',
        'ticket_price' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'average_rating' => 'decimal:2',
        'images' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($destination) {
            if (empty($destination->slug)) {
                $destination->slug = Str::slug($destination->name);
                $originalSlug = $destination->slug;
                $count = 1;
                while (static::whereSlug($destination->slug)->exists()) {
                    $destination->slug = "{$originalSlug}-{$count}";
                    $count++;
                }
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getMainImageUrlAttribute(): ?string
    {
        if (isset($this->images[0]) && !empty($this->images[0])) {
            return Storage::disk('public')->url($this->images[0]);
        }
        return asset('images/placeholder-destination.png');
    }

    public function getImageUrlsAttribute(): array
    {
        if (is_array($this->images)) {
            return array_map(function ($imagePath) {
                if(!empty($imagePath)) return Storage::disk('public')->url($imagePath);
                return null;
            }, array_filter($this->images));
        }
        return [];
    }
}
