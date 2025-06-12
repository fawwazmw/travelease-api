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

    // Menambahkan accessor ke serialisasi JSON model secara default
    protected $appends = ['review_image_public_urls'];

    /**
     * Otomatisasi proses saat event model terjadi.
     */
    protected static function boot() // <-- DITAMBAHKAN
    {
        parent::boot();

        // Saat sebuah review dihapus, jalankan fungsi ini
        static::deleting(function (Review $review) {
            // Jika ada gambar, hapus dari storage
            if (is_array($review->images_urls)) {
                foreach ($review->images_urls as $path) {
                    if (!empty($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        });
    }

    // --- Relasi (Sudah Benar) ---
    public function user() { return $this->belongsTo(User::class); }
    public function destination() { return $this->belongsTo(Destination::class); }
    public function booking() { return $this->belongsTo(Booking::class); }

    // --- Accessor (Sudah Benar) ---
    public function getReviewImagePublicUrlsAttribute(): array
    {
        if (empty($this->images_urls)) {
            return [];
        }

        return collect($this->images_urls)
            ->filter() // Menghapus nilai null atau string kosong
            ->map(fn ($imagePath) => Storage::disk('public')->url($imagePath))
            ->all();
    }
}
