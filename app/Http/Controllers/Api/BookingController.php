<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Slot;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth; // Tidak digunakan secara langsung, $request->user() lebih diutamakan
use Illuminate\Support\Facades\DB; // Untuk transaksi database
use Illuminate\Validation\ValidationException; // Berguna jika Anda melemparnya secara manual
use Illuminate\Http\JsonResponse;
use Carbon\Carbon; // Digunakan dalam logika pembatalan yang dikomentari

class BookingController extends Controller
{
    public function __construct()
    {
        // Middleware ini memastikan semua method memerlukan token autentikasi Sanctum
        $this->middleware('auth:sanctum');
    }

    /**
     * Menampilkan daftar pemesanan milik pengguna yang terautentikasi.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookings = $user->bookings()
            ->with([
                'destination:id,name,slug', // Eager load hanya kolom yang diperlukan
                'slot:id,slot_date,start_time,end_time' // Eager load hanya kolom yang diperlukan
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        // Menambahkan URL gambar utama destinasi ke setiap booking.
        // Ini berfungsi jika accessor `main_image_url` ada di model Destination.
        // Alternatifnya, Anda bisa menambahkan `main_image_url` ke $appends di model Destination.
        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->destination) {
                // Memastikan accessor terpanggil dan hasilnya diserialisasi
                $booking->destination->main_image_url = $booking->destination->main_image_url;
            }
            return $booking;
        });

        return response()->json($bookings);
    }

    /**
     * Menyimpan pemesanan baru.
     */
    public function store(Request $request): JsonResponse
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
            ->first();

        if (!$destination) {
            return response()->json(['message' => 'Destinasi tidak ditemukan atau tidak aktif.'], 404);
        }

        $slot = null;
        if (!empty($validatedData['slot_id'])) {
            $slot = Slot::find($validatedData['slot_id']);
            if (!$slot || $slot->destination_id !== $destination->id || !$slot->is_active) {
                return response()->json(['message' => 'Slot tidak valid atau tidak aktif untuk destinasi ini.'], 422);
            }
            if ($slot->slot_date->format('Y-m-d') !== $validatedData['visit_date']) {
                return response()->json(['message' => 'Tanggal kunjungan tidak cocok dengan tanggal slot yang dipilih.'], 422);
            }
            if (($slot->capacity - $slot->booked_count) < $validatedData['num_tickets']) {
                return response()->json(['message' => 'Kapasitas slot tidak mencukupi untuk jumlah tiket yang diminta.'], 422);
            }
        }
        // Validasi 'after_or_equal:today' untuk visit_date sudah cukup baik.

        $totalPrice = $destination->ticket_price * $validatedData['num_tickets'];

        try {
            DB::beginTransaction();

            $booking = Booking::create([
                'user_id' => $user->id,
                'destination_id' => $destination->id,
                'slot_id' => $slot ? $slot->id : null,
                'visit_date' => $validatedData['visit_date'],
                'num_tickets' => $validatedData['num_tickets'],
                'total_price' => $totalPrice,
                'status' => 'pending', // Status awal, menunggu pembayaran atau konfirmasi admin
            ]);

            // Logika untuk mengupdate `booked_count` di tabel `slots` sebaiknya ditangani
            // saat status booking menjadi 'confirmed' (misalnya setelah pembayaran berhasil
            // atau admin melakukan konfirmasi). Untuk status 'pending', biasanya belum mengurangi kapasitas.
            // if ($slot && $booking->status === 'confirmed') {
            //     $slot->increment('booked_count', $validatedData['num_tickets']);
            // }

            DB::commit();

            $booking->load(['destination:id,name', 'slot:id,slot_date,start_time,end_time']);
            if ($booking->destination) {
                $booking->destination->main_image_url = $booking->destination->main_image_url;
            }

            return response()->json([
                'message' => 'Pemesanan berhasil dibuat dan sedang menunggu konfirmasi.',
                'data' => $booking
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Di lingkungan produksi, sebaiknya log error ini dan kirim pesan generik.
            // Log::error("Booking creation failed for user {$user->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membuat pemesanan. Terjadi kesalahan server.',
                // 'error' => $e->getMessage(), // Sebaiknya tidak diekspos di produksi
            ], 500);
        }
    }

    /**
     * Menampilkan detail pemesanan spesifik milik pengguna yang terautentikasi.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        if ($request->user()->id !== $booking->user_id) {
            return response()->json(['message' => 'Tidak diizinkan untuk mengakses pemesanan ini.'], 403);
        }

        $booking->load([
            'destination:id,name,slug,location_address',
            'user:id,name,email', // Mungkin tidak perlu jika user adalah $request->user()
            'slot:id,slot_date,start_time,end_time'
        ]);

        if ($booking->destination) {
            // Memastikan accessor terpanggil dan hasilnya diserialisasi
            $booking->destination->main_image_url = $booking->destination->main_image_url;
            $booking->destination->image_urls = $booking->destination->image_urls;
        }

        return response()->json(['data' => $booking]);
    }

    /**
     * Membatalkan pemesanan yang dibuat oleh pengguna yang terautentikasi.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $booking->user_id) {
            return response()->json(['message' => 'Tidak diizinkan untuk membatalkan pemesanan ini.'], 403);
        }

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Pemesanan tidak dapat dibatalkan karena statusnya saat ini: ' . $booking->status . '.'], 422);
        }

        // Contoh: Aturan pembatalan H-1 sebelum tanggal kunjungan untuk booking yang 'confirmed'
        // if ($booking->status === 'confirmed' && Carbon::parse($booking->visit_date)->isBefore(Carbon::today()->addDay())) {
        //     return response()->json(['message' => 'Pemesanan tidak dapat dibatalkan kurang dari 1 hari sebelum tanggal kunjungan.'], 422);
        // }

        try {
            DB::beginTransaction();

            // $previousStatus = $booking->status; // Simpan status sebelumnya jika perlu logika kompleks
            $booking->status = 'cancelled';
            $booking->save();

            // Logika untuk mengembalikan kapasitas slot (booked_count--) jika booking sebelumnya 'confirmed'.
            // Ini harus konsisten dengan bagaimana Anda menaikkan booked_count.
            // if ($previousStatus === 'confirmed' && $booking->slot_id) {
            //     $slot = Slot::find($booking->slot_id);
            //     if ($slot) {
            //         // Pastikan tidak menjadi negatif jika ada race condition atau data tidak konsisten
            //         $slot->decrement('booked_count', $booking->num_tickets);
            //     }
            // }

            DB::commit();

            return response()->json(['message' => 'Pemesanan berhasil dibatalkan.']);

        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error("Booking cancellation failed for booking ID {$booking->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membatalkan pemesanan. Terjadi kesalahan server.',
                // 'error' => $e->getMessage(), // Sebaiknya tidak diekspos di produksi
            ], 500);
        }
    }
}
