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
        // 'average_rating', // Sebaiknya dihitung, bukan diisi manual
        // 'total_reviews',  // Sebaiknya dihitung, bukan diisi manual
        'is_active',
        // Kolom 'images' (array) sudah tidak ada lagi di sini
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
        'total_reviews' => 'integer', // Tambahkan cast jika kolomnya ada
    ];

    /**
     * Atribut yang akan otomatis ditambahkan saat model diserialisasi ke array/JSON.
     */
    protected $appends = ['main_image_url', 'image_urls'];

    protected static function boot()
    {
        parent::boot();

        // Otomatis buat slug saat destinasi dibuat jika slug kosong
        static::creating(function ($destination) {
            if (empty($destination->slug)) {
                $destination->slug = Str::slug($destination->name);
                // Pastikan slug unik
                $originalSlug = $destination->slug;
                $count = 1;
                while (static::whereSlug($destination->slug)->exists()) {
                    $destination->slug = "{$originalSlug}-{$count}";
                    $count++;
                }
            }
        });

        // Saat destinasi dihapus, semua record DestinationImage terkait akan otomatis dihapus
        // karena ada onDelete('cascade') pada foreign key di migrasi destination_images.
        // Model event 'deleting' di DestinationImage akan menangani penghapusan file dari storage.
    }

    /**
     * Relasi: Satu destinasi dimiliki oleh satu kategori.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi: Satu destinasi dibuat oleh satu pengguna (admin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi: Satu destinasi bisa memiliki BANYAK gambar.
     */
    public function destinationImages()
    {
        return $this->hasMany(DestinationImage::class);
    }

    /**
     * Relasi: Satu destinasi bisa memiliki banyak slot kunjungan.
     */
    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    /**
     * Relasi: Satu destinasi bisa memiliki banyak pemesanan.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Relasi: Satu destinasi bisa memiliki banyak ulasan.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Accessor untuk mendapatkan URL gambar utama.
     * Mengambil gambar yang 'is_primary' atau gambar pertama jika tidak ada.
     */
    public function getMainImageUrlAttribute(): ?string
    {
        $primaryImage = $this->destinationImages()->where('is_primary', true)->first();
        if ($primaryImage && $primaryImage->image_url) {
            return Storage::disk('public')->url($primaryImage->image_url);
        }

        $firstImage = $this->destinationImages()->orderBy('created_at', 'asc')->first(); // Ambil yang paling awal diunggah
        if ($firstImage && $firstImage->image_url) {
            return Storage::disk('public')->url($firstImage->image_url);
        }

        return asset('images/placeholder-destination.png'); // Sediakan placeholder ini di public/images
    }

    /**
     * Accessor untuk mendapatkan array URL publik dari semua gambar destinasi.
     */
    public function getImageUrlsAttribute(): array
    {
        if ($this->relationLoaded('destinationImages')) {
            // Jika relasi sudah di-load, gunakan koleksi yang ada untuk efisiensi
            return $this->destinationImages
                ->whereNotNull('image_url')
                ->map(fn ($image) => Storage::disk('public')->url($image->image_url))
                ->all();
        }
        // Jika belum, query ke database
        return $this->destinationImages()->get()
            ->whereNotNull('image_url')
            ->map(fn ($image) => Storage::disk('public')->url($image->image_url))
            ->all();
    }


    /**
     * Method untuk mengupdate rating rata-rata destinasi.
     * Dipanggil setelah ulasan disimpan atau dihapus.
     */
    public function updateAverageRating(): void
    {
        $approvedReviews = $this->reviews()->where('status', 'approved');
        $newAverage = $approvedReviews->avg('rating');
        $newTotal = $approvedReviews->count();

        $this->average_rating = $newAverage ?? 0;
        $this->total_reviews = $newTotal;
        $this->saveQuietly(); // Simpan tanpa memicu event lain
    }
}
