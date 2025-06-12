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
        'created_by', // <-- DIPERBARUI: Ditambahkan agar bisa di-create secara massal
        'ticket_price',
        'operational_hours',
        'contact_phone',
        'contact_email',
        'is_active',
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
        'total_reviews' => 'integer',
    ];

    protected $appends = ['main_image_url', 'image_urls'];

    // boot() method tidak perlu diubah, sudah benar.
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

    // --- Relasi (Sudah Benar) ---
    public function category() { return $this->belongsTo(Category::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function images() { return $this->hasMany(DestinationImage::class); }
    public function slots() { return $this->hasMany(Slot::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function reviews() { return $this->hasMany(Review::class); }

    // --- Accessor (PENTING: Perbaikan Performa) ---

    public function getMainImageUrlAttribute(): ?string
    {
        // <-- DIPERBARUI: Blok 'if (!$this->relationLoaded...)' dihapus untuk mencegah N+1 Query.
        // Accessor ini sekarang berasumsi relasi 'images' sudah dimuat oleh Controller.
        $primaryImage = $this->images->firstWhere('is_primary', true);
        if ($primaryImage && $primaryImage->image_url) {
            return $primaryImage->public_url; // Menggunakan accessor dari model DestinationImage
        }

        $firstImage = $this->images->sortBy('created_at')->first();
        if ($firstImage && $firstImage->image_url) {
            return $firstImage->public_url; // Menggunakan accessor dari model DestinationImage
        }

        return asset('images/placeholder-image.webp');
    }

    public function getImageUrlsAttribute(): array
    {
        // <-- DIPERBARUI: Blok 'if (!$this->relationLoaded...)' dihapus.
        return $this->images
            ->whereNotNull('image_url')
            ->map(fn (DestinationImage $image) => $image->public_url) // Lebih bersih dengan accessor
            ->all();
    }

    // Method updateAverageRating() tidak perlu diubah, sudah benar.
    public function updateAverageRating(): void
    {
        $approvedReviews = $this->reviews()->where('status', 'approved');
        $this->average_rating = $approvedReviews->avg('rating') ?? 0;
        $this->total_reviews = $approvedReviews->count();
        $this->saveQuietly();
    }
}
