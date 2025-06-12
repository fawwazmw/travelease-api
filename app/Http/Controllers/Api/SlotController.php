<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotResource;
use App\Models\Destination;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotController extends Controller
{
    /**
     * Menampilkan daftar slot yang tersedia untuk destinasi dan rentang tanggal tertentu.
     */
    public function getAvailableSlots(Request $request, string $destinationSlug): JsonResource // Nama variabel diubah
    {
        $request->validate([
            'date' => 'sometimes|date_format:Y-m-d',
            'start_date' => 'sometimes|date_format:Y-m-d|required_with:end_date|before_or_equal:end_date',
            'end_date' => 'sometimes|date_format:Y-m-d|required_with:start_date|after_or_equal:start_date',
        ]);

        // Menggunakan cara pencarian yang sama persis dengan DestinationController
        $destination = Destination::where('slug', $destinationSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $query = $destination->slots()
            ->where('is_active', true)
            ->whereRaw('capacity > booked_count');

        if ($request->filled('date')) {
            $query->whereDate('slot_date', $request->date);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('slot_date', [$request->start_date, $request->end_date]);
        } else {
            $query->whereDate('slot_date', '>=', now());
        }

        $slots = $query->orderBy('slot_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return SlotResource::collection($slots);
    }
}
