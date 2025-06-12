<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'destination_id',
        'slot_date',
        'start_time',
        'end_time',
        'capacity',
        'booked_count',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'destination_id' => 'string',
        'slot_date' => 'date',
        'capacity' => 'integer',
        'booked_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Menambahkan accessor ke serialisasi JSON model secara default.
     */
    protected $appends = ['available_capacity']; // <-- DITAMBAHKAN

    /**
     * Accessor untuk mendapatkan sisa kapasitas yang tersedia.
     *
     * @return int
     */
    public function getAvailableCapacityAttribute(): int // <-- DITAMBAHKAN
    {
        return $this->capacity - $this->booked_count;
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
