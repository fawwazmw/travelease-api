<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Slot; // Meskipun tidak dipanggil statis, baik untuk diimpor karena relasi mengembalikan instance Slot
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon; // Untuk manipulasi tanggal

class SlotController extends Controller
{
    /**
     * Menampilkan daftar slot yang tersedia untuk destinasi dan rentang tanggal tertentu.
     * Endpoint ini bersifat publik.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $destinationSlugOrId Dapat berupa slug atau UUID dari destinasi
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableSlots(Request $request, string $destinationSlugOrId): JsonResponse
    {
        // Validasi input tanggal dari request
        $request->validate([
            'date' => 'sometimes|date_format:Y-m-d', // Untuk tanggal spesifik
            'start_date' => 'sometimes|date_format:Y-m-d|required_with:end_date|before_or_equal:end_date', // Validasi rentang
            'end_date' => 'sometimes|date_format:Y-m-d|required_with:start_date|after_or_equal:start_date',
        ]);

        // Cari destinasi berdasarkan slug atau ID, dan pastikan aktif
        $destination = Destination::where(function ($query) use ($destinationSlugOrId) {
            $query->where('slug', $destinationSlugOrId)
                ->orWhere('id', $destinationSlugOrId);
        })
            ->where('is_active', true)
            ->first();

        if (!$destination) {
            return response()->json(['message' => 'Destinasi tidak ditemukan atau tidak aktif.'], 404);
        }

        // Query dasar untuk slot destinasi yang aktif dan masih memiliki kapasitas
        $query = $destination->slots()
            ->where('is_active', true)
            ->whereRaw('capacity > booked_count'); // Hanya slot yang kapasitasnya belum penuh

        // Filter berdasarkan parameter tanggal dari request
        if ($request->filled('date')) {
            $query->whereDate('slot_date', $request->date);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('slot_date', [$request->start_date, $request->end_date]);
        } else {
            // Default: tampilkan slot untuk hari ini dan 6 hari ke depan (total 7 hari)
            $query->whereBetween('slot_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(6)->toDateString()]);
        }

        // Ambil slot yang sudah difilter dan diurutkan
        $slots = $query->orderBy('slot_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($slots->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada slot tersedia untuk kriteria yang dipilih.',
                'data' => []
            ], 200); // Mengembalikan 200 OK dengan data kosong lebih baik daripada 404 jika query valid tapi tidak ada hasil
        }

        return response()->json(['data' => $slots]);
    }

    // Komentar Anda mengenai endpoint admin sudah tepat.
    // Jika diperlukan, endpoint untuk manajemen slot oleh admin (CRUD) bisa ditambahkan di sini.
    // Endpoint tersebut akan diproteksi dengan middleware ['auth:sanctum', 'isAdmin'].
    // Namun, karena manajemen slot utama dilakukan via Filament, ini mungkin tidak esensial untuk API publik.
}
