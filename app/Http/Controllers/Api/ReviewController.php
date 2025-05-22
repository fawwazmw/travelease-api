<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Destination;
use App\Models\Booking;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth; // Tidak digunakan secara langsung, $request->user() lebih diutamakan
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException; // Berguna jika Anda melemparnya secara manual
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct()
    {
        // Menerapkan middleware auth:sanctum ke method yang memerlukan user terautentikasi
        $this->middleware('auth:sanctum')->except(['indexForDestination']);
    }

    /**
     * Menampilkan daftar ulasan yang disetujui untuk destinasi spesifik.
     */
    public function indexForDestination(Request $request, string $destinationSlugOrId): JsonResponse
    {
        $destination = Destination::where('slug', $destinationSlugOrId)
            ->orWhere('id', $destinationSlugOrId)
            ->where('is_active', true)
            ->first();

        if (!$destination) {
            return response()->json(['message' => 'Destinasi tidak ditemukan atau tidak aktif.'], 404);
        }

        $reviews = $destination->reviews()
            ->where('status', 'approved')
            ->with('user:id,name') // Eager load hanya kolom yang diperlukan dari user
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 5));

        // Dengan $appends di model Review, 'review_image_public_urls' akan otomatis ditambahkan.
        // Blok transform di bawah ini tidak lagi diperlukan jika $appends sudah diatur.
        // $reviews->getCollection()->transform(function (Review $review) {
        //     // $review->review_image_public_urls = $review->review_image_public_urls;
        //     return $review;
        // });

        return response()->json($reviews);
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
            'images' => 'nullable|array|max:3', // Batasi maks 3 gambar
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $existingReview = Review::where('user_id', $user->id)
            ->where('destination_id', $validatedData['destination_id'])
            ->first();
        if ($existingReview) {
            return response()->json(['message' => 'Anda sudah pernah memberikan ulasan untuk destinasi ini.'], 422);
        }

        if (isset($validatedData['booking_id'])) {
            $booking = Booking::find($validatedData['booking_id']); // user_id sudah divalidasi di rule 'exists'
            if (!$booking || $booking->status !== 'completed') {
                return response()->json(['message' => 'Anda hanya bisa memberikan ulasan setelah kunjungan (booking completed).'], 422);
            }
        }

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
            'status' => 'pending',
        ]);

        $review->load('user:id,name');
        // 'review_image_public_urls' akan otomatis oleh $appends

        return response()->json([
            'message' => 'Ulasan Anda berhasil dikirim dan sedang menunggu moderasi.',
            'data' => $review
        ], 201);
    }

    /**
     * Menampilkan detail ulasan spesifik (milik pengguna atau oleh admin).
     */
    public function show(Request $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Tidak diizinkan untuk mengakses ulasan ini.'], 403);
        }
        $review->load(['user:id,name', 'destination:id,name']);
        // 'review_image_public_urls' akan otomatis oleh $appends
        return response()->json(['data' => $review]);
    }

    /**
     * Mengupdate ulasan spesifik (milik pengguna, status 'pending').
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id) {
            return response()->json(['message' => 'Tidak diizinkan untuk mengubah ulasan ini.'], 403);
        }

        if ($review->status !== 'pending') {
            return response()->json(['message' => 'Ulasan tidak dapat diubah karena sudah diproses.'], 422);
        }

        $validatedData = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'sometimes|nullable|string|max:5000',
            // Logika update gambar tidak dihandle di sini untuk kesederhanaan.
            // Jika perlu, tambahkan validasi dan logika untuk 'images' dan 'images_to_delete'.
        ]);

        $review->update($validatedData);
        $review->load('user:id,name');
        // 'review_image_public_urls' akan otomatis oleh $appends

        return response()->json([
            'message' => 'Ulasan berhasil diperbarui dan sedang menunggu moderasi ulang.',
            'data' => $review
        ]);
    }

    /**
     * Menghapus ulasan spesifik (milik pengguna atau oleh admin).
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Tidak diizinkan untuk menghapus ulasan ini.'], 403);
        }

        // Simpan destinasi sebelum review dihapus untuk update rating
        $destination = $review->destination;

        // Hapus gambar terkait dari storage
        if (is_array($review->images_urls)) {
            foreach ($review->images_urls as $imagePath) {
                if (!empty($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }

        $review->delete();

        // Update rating rata-rata destinasi setelah review dihapus
        if ($destination) {
            $destination->updateAverageRating(); // Pastikan method ini ada di model Destination
        }

        return response()->json(['message' => 'Ulasan berhasil dihapus.']);
    }
}
