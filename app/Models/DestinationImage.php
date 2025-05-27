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
        'image_url', // Path ke gambar di storage
        'caption',
        'is_primary',
    ];

    protected $casts = [
        'id' => 'string',
        'destination_id' => 'string',
        'is_primary' => 'boolean',
    ];

    /**
     * Setup model event hooks
     */
    protected static function boot()
    {
        parent::boot();

        // Otomatis hapus file dari storage saat record DestinationImage dihapus
        static::deleting(function ($image) {
            if ($image->image_url) {
                Storage::disk('public')->delete($image->image_url);
            }
        });
    }

    /**
     * Relasi: Satu gambar dimiliki oleh satu destinasi.
     */
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * Accessor untuk mendapatkan URL publik gambar.
     * Ini bisa berguna jika Anda ingin mengakses URL langsung dari instance DestinationImage.
     */
    public function getPublicUrlAttribute(): ?string
    {
        if ($this->image_url) {
            return Storage::disk('public')->url($this->image_url);
        }
        return null;
    }
}
