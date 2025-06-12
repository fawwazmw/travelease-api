<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // <-- DITAMBAHKAN
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_url',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    /**
     * Menambahkan accessor ke serialisasi JSON model secara default.
     */
    protected $appends = ['public_icon_url']; // <-- DITAMBAHKAN

    // Method boot() tidak perlu diubah, sudah benar.
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
                $originalSlug = $category->slug;
                $count = 1;
                while (static::whereSlug($category->slug)->exists()) {
                    $category->slug = "{$originalSlug}-{$count}";
                    $count++;
                }
            }
        });
    }

    public function destinations()
    {
        return $this->hasMany(Destination::class);
    }

    /**
     * Accessor untuk mendapatkan URL publik dari ikon.
     * Logika yang sebelumnya ada di controller, kini dipindahkan ke sini.
     *
     * @return string
     */
    public function getPublicIconUrlAttribute(): string // <-- DITAMBAHKAN
    {
        if ($this->icon_url && Storage::disk('public')->exists($this->icon_url)) {
            return Storage::disk('public')->url($this->icon_url);
        }
        // Pastikan file placeholder ini ada di public/images/placeholder-category.png
        return asset('images/placeholder-category.png');
    }
}
