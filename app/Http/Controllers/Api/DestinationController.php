<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Category;
use App\Models\Destination;
use App\Models\DestinationImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DestinationController extends Controller
{
    // <-- DIHAPUS: Blok __construct() yang menyebabkan error telah dihapus.
    // Penerapan middleware sekarang sepenuhnya ditangani oleh file routes/api.php,
    // yang merupakan praktik yang lebih modern dan aman.

    /**
     * Menampilkan daftar destinasi yang aktif.
     */
    public function index(Request $request): JsonResource
    {
        $query = Destination::query();
        if (!(auth()->check() && auth()->user()?->role === 'admin' && $request->boolean('include_inactive'))) {
            $query->where('is_active', true);
        }

        // Eager load relasi untuk performa
        $query->with(['category:id,name,slug', 'images']);

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', 'like', $request->category_slug));
        }
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$searchTerm}%")->orWhere('description', 'like', "%{$searchTerm}%"));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortColumns = ['name', 'created_at', 'ticket_price', 'average_rating'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $destinations = $query->paginate($request->input('per_page', 10));

        // Gunakan API Resource Collection
        return DestinationResource::collection($destinations);
    }

    /**
     * Menyimpan destinasi baru (oleh Admin).
     */
    public function store(Request $request): DestinationResource
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:destinations,slug',
            'description' => 'nullable|string',
            'category_id' => 'required|uuid|exists:categories,id',
            'location_address' => 'nullable|string',
            'latitude' => ['nullable', 'numeric', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['nullable', 'numeric', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            'ticket_price' => 'nullable|numeric|min:0',
            'operational_hours' => 'nullable|string',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'is_active' => 'sometimes|boolean',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $validatedData['created_by'] = auth()->id();

        // Logika penyimpanan gambar yang benar menggunakan transaksi DB
        $destination = DB::transaction(function () use ($validatedData, $request) {
            $destination = Destination::create($validatedData);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $key => $imageFile) {
                    $path = $imageFile->store('destination-images', 'public');
                    $destination->images()->create([
                        'image_url' => $path,
                        'is_primary' => ($key == 0), // Jadikan gambar pertama sebagai primary
                    ]);
                }
            }
            return $destination;
        });

        $destination->load(['category:id,name,slug', 'images']);
        return new DestinationResource($destination);
    }

    /**
     * Menampilkan detail destinasi spesifik.
     */
    public function show(string $slug): DestinationResource // 1. Ubah parameter input
    {
        // 2. Cari destinasi secara eksplisit berdasarkan slug
        $destination = Destination::where('slug', $slug)->firstOrFail();

        // 3. Logika pengecekan status aktif tetap sama
        if (!$destination->is_active && !(auth()->check() && auth()->user()?->role === 'admin')) {
            abort(404, 'Destinasi tidak ditemukan atau tidak aktif.');
        }

        // 4. Eager load relasi yang dibutuhkan
        $destination->load([
            'category:id,name,slug',
            'images',
            'creator:id,name' // Cukup ambil id dan nama creator
        ]);

        // Jangan load 'reviews' di sini karena frontend sudah memanggil endpoint review terpisah
        // Ini untuk menjaga agar response awal tetap cepat

        return new DestinationResource($destination);
    }

    /**
     * Mengupdate destinasi spesifik (oleh Admin).
     */
    public function update(Request $request, Destination $destination): DestinationResource
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|required|uuid|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'uuid|exists:destination_images,id',
            'new_images' => 'nullable|array|max:5',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        DB::transaction(function () use ($validatedData, $request, $destination) {
            $destination->update($validatedData);

            if ($request->filled('images_to_delete')) {
                DestinationImage::whereIn('id', $request->input('images_to_delete'))
                    ->where('destination_id', $destination->id)
                    ->delete();
            }

            if ($request->hasFile('new_images')) {
                foreach ($request->file('new_images') as $imageFile) {
                    $path = $imageFile->store('destination-images', 'public');
                    $destination->images()->create(['image_url' => $path]);
                }
            }
        });

        $destination->load(['category:id,name,slug', 'images']);
        return new DestinationResource($destination);
    }

    /**
     * Menghapus destinasi spesifik (oleh Admin).
     */
    public function destroy(Destination $destination): JsonResponse
    {
        $destination->delete();
        return response()->json(['message' => 'Destinasi berhasil dihapus.'], 200);
    }
}
