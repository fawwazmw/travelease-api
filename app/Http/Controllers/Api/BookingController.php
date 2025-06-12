<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource; // <-- DITAMBAHKAN
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource; // <-- DITAMBAHKAN
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // <-- DITAMBAHKAN

class BookingController extends Controller
{

    /**
     * Menampilkan daftar pemesanan milik pengguna yang terautentikasi.
     */
    public function index(Request $request): JsonResource // <-- Tipe return diubah
    {
        $user = $request->user();

        $bookings = $user->bookings()
            // <-- DIPERBARUI: Eager loading yang benar untuk mengatasi N+1
            ->with([
                'destination:id,name,slug',
                'destination.images', // <-- PENTING: Muat gambar destinasi
                'slot:id,slot_date,start_time,end_time'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        // <-- DIPERBARUI: Blok `transform` dihapus total.
        return BookingResource::collection($bookings);
    }

    /**
     * Menyimpan pemesanan baru.
     */
    public function store(Request $request): JsonResource // <-- Tipe return diubah
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'destination_id' => 'required|uuid|exists:destinations,id',
            'slot_id' => 'nullable|uuid|exists:slots,id',
            'visit_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'num_tickets' => 'required|integer|min:1',
        ]);

        $destination = Destination::where('id', $validatedData['destination_id'])
            ->where('is_active', true)
            ->firstOrFail(); // Gunakan firstOrFail

        // <-- DIPERBARUI: Logika validasi slot dibuat lebih ringkas
        if (!empty($validatedData['slot_id'])) {
            $slot = Slot::find($validatedData['slot_id']);
            if (!$slot || $slot->destination_id !== $destination->id || !$slot->is_active ||
                $slot->slot_date->format('Y-m-d') !== $validatedData['visit_date'] ||
                ($slot->capacity - $slot->booked_count) < $validatedData['num_tickets']) {
                abort(422, 'Slot tidak valid, tidak aktif, atau kapasitas tidak mencukupi.');
            }
        }

        $totalPrice = $destination->ticket_price * $validatedData['num_tickets'];

        try {
            // <-- DIPERBARUI: Logika pembuatan booking kini dalam satu transaksi
            $booking = DB::transaction(function () use ($user, $validatedData, $destination, $totalPrice) {
                return Booking::create([
                    'user_id' => $user->id,
                    'destination_id' => $destination->id,
                    'slot_id' => $validatedData['slot_id'] ?? null,
                    'visit_date' => $validatedData['visit_date'],
                    'num_tickets' => $validatedData['num_tickets'],
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                ]);
            });

            // Muat relasi sebelum dikirim ke resource
            $booking->load(['destination.images', 'slot', 'user']);

            // <-- DIPERBARUI: Gunakan Resource dan tambahkan pesan via additional()
            return (new BookingResource($booking))
                ->additional(['message' => 'Pemesanan berhasil dibuat dan sedang menunggu konfirmasi.'])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            Log::error("Booking creation failed for user {$user->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membuat pemesanan. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Menampilkan detail pemesanan spesifik milik pengguna yang terautentikasi.
     */
    public function show(Request $request, Booking $booking): BookingResource // <-- Tipe return diubah
    {
        // Pengecekan kepemilikan. Untuk aplikasi lebih besar, gunakan Policy.
        if ($request->user()->id !== $booking->user_id) {
            abort(403, 'Tidak diizinkan untuk mengakses pemesanan ini.');
        }

        // <-- DIPERBARUI: Muat semua relasi yang dibutuhkan untuk detail view
        $booking->load(['destination.images', 'destination.category', 'user', 'slot', 'review']);

        return new BookingResource($booking);
    }

    /**
     * Membatalkan pemesanan yang dibuat oleh pengguna yang terautentikasi.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        if ($request->user()->id !== $booking->user_id) {
            abort(403, 'Tidak diizinkan untuk membatalkan pemesanan ini.');
        }
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            abort(422, 'Pemesanan tidak dapat dibatalkan karena statusnya saat ini: ' . $booking->status . '.');
        }

        // Logika pembatalan sudah baik, tidak perlu diubah, hanya ditambahkan logging
        try {
            DB::transaction(function () use ($booking) {
                $booking->status = 'cancelled';
                $booking->save();
                // Tambahkan logika pengembalian slot di sini jika diperlukan
            });
            return response()->json(['message' => 'Pemesanan berhasil dibatalkan.']);
        } catch (\Exception $e) {
            Log::error("Booking cancellation failed for booking ID {$booking->id}: " . $e->getMessage());
            return response()->json(['message' => 'Gagal membatalkan pemesanan.',], 500);
        }
    }
}
