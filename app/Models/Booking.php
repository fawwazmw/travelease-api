<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // <-- DITAMBAHKAN

class Booking extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'booking_code',
        'user_id',
        'destination_id',
        'slot_id',
        'visit_date',
        'num_tickets',
        'total_price',
        'status',
        'payment_method',
        'payment_id_external',
        'payment_details',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'destination_id' => 'string',
        'slot_id' => 'string',
        'visit_date' => 'date',
        'num_tickets' => 'integer',
        'total_price' => 'decimal:2',
        'payment_details' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = 'TRV-' . strtoupper(Str::random(8));
                while (static::where('booking_code', $booking->booking_code)->exists()) {
                    $booking->booking_code = 'TRV-' . strtoupper(Str::random(8));
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
