<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource; // <-- DITAMBAHKAN
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource; // <-- DITAMBAHKAN
use Illuminate\Support\Facades\DB; // <-- DITAMBAHKAN
use Illuminate\Support\Facades\Log; // <-- DITAMBAHKAN

class ReviewController extends Controller
{

    /**
     * Menampilkan daftar ulasan yang disetujui untuk destinasi spesifik.
     */
    public function indexForDestination(Request $request, string $destinationSlug): JsonResource // Nama variabel diubah agar lebih jelas
    {
        // Menggunakan cara pencarian yang sama persis dengan DestinationController
        $destination = Destination::where('slug', $destinationSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $reviews = $destination->reviews()
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->paginate($request->input('per_page', 5));

        return ReviewResource::collection($reviews);
    }

    /**
     * Menyimpan ulasan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'destination_id' => 'required|uuid|exists:destinations,id',
            'booking_id' => 'nullable|uuid|exists:bookings,id,user_id,' . $user->id,
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:5000',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        if (Review::where('user_id', $user->id)->where('destination_id', $validatedData['destination_id'])->exists()) {
            abort(422, 'Anda sudah pernah memberikan ulasan untuk destinasi ini.');
        }

        if (isset($validatedData['booking_id'])) {
            $booking = Booking::find($validatedData['booking_id']);
            if (!$booking || !in_array($booking->status, ['completed', 'verified'])) {
                abort(422, 'Anda hanya bisa memberikan ulasan setelah kunjungan selesai.');
            }
        }

        $destination = Destination::find($validatedData['destination_id']);

        try {
            $review = DB::transaction(function () use ($user, $validatedData, $destination, $request) {
                $imagePaths = [];
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $imageFile) {
                        $imagePaths[] = $imageFile->store('review-images', 'public');
                    }
                }
                $review = Review::create([
                    'user_id' => $user->id,
                    'destination_id' => $validatedData['destination_id'],
                    'booking_id' => $validatedData['booking_id'] ?? null,
                    'rating' => $validatedData['rating'],
                    'comment' => $validatedData['comment'] ?? null,
                    'images_urls' => $imagePaths,
                    'status' => 'pending', // Default status
                ]);

                // Update rating destinasi jika review langsung disetujui (atau bisa dipindah ke panel admin)
                // if ($review->status === 'approved') {
                //     $destination->updateAverageRating();
                // }
                return $review;
            });

            $review->load('user');
            return (new ReviewResource($review))
                ->additional(['message' => 'Ulasan Anda berhasil dikirim dan sedang menunggu moderasi.'])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            Log::error('Review creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan ulasan.'], 500);
        }
    }

    /**
     * Menampilkan detail ulasan spesifik.
     */
    public function show(Request $request, Review $review): ReviewResource // <-- Tipe return diubah
    {
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            abort(403, 'Tidak diizinkan untuk mengakses ulasan ini.');
        }
        $review->load(['user', 'destination:id,name,slug']);
        return new ReviewResource($review);
    }

    /**
     * Menghapus ulasan spesifik (milik pengguna atau oleh admin).
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            abort(403, 'Tidak diizinkan untuk menghapus ulasan ini.');
        }

        DB::transaction(function() use ($review) {
            $destination = $review->destination;
            // <-- DIPERBARUI: Logika hapus file sudah pindah ke Model event.
            $review->delete();
            // Update rating rata-rata setelah review dihapus
            if ($destination) {
                $destination->updateAverageRating();
            }
        });

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
