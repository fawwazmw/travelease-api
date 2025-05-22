<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Ditambahkan untuk return type yang lebih eksplisit
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
// use Illuminate\Validation\Rule; // Dihapus jika validasi slug unik saat update tidak digunakan

class DestinationController extends Controller
{
    public function __construct()
    {
        // Terapkan middleware untuk otorisasi admin pada method yang mengubah data
        $this->middleware(['auth:sanctum', 'isAdmin'])->only(['store', 'update', 'destroy']);
    }

    /**
     * Menampilkan daftar destinasi yang aktif.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Destination::query();

        // Secara default, hanya tampilkan yang aktif untuk publik
        // Admin bisa melihat semua jika ada parameter tambahan atau endpoint berbeda
        if (!(auth()->check() && auth()->user()->role === 'admin' && $request->boolean('include_inactive'))) {
            $query->where('is_active', true);
        }

        $query->with('category:id,name,slug'); // Eager load kategori dengan kolom spesifik

        if ($request->filled('category_slug')) {
            $category = Category::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            } else {
                return response()->json(['data' => [], 'message' => 'Kategori tidak ditemukan.'], 404);
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortColumns = ['name', 'created_at', 'ticket_price', 'average_rating'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $destinations = $query->paginate($request->input('per_page', 10));

        // Dengan $appends di model Destination, 'image_urls' dan 'main_image_url' akan otomatis ditambahkan.
        // Blok transform di bawah ini tidak lagi diperlukan jika $appends sudah diatur.
        // $destinations->getCollection()->transform(function (Destination $destination) {
        //     // $destination->image_urls = $destination->image_urls; // Sudah ditangani oleh $appends
        //     return $destination;
        // });

        return response()->json($destinations);
    }

    /**
     * Menyimpan destinasi baru (oleh Admin).
     */
    public function store(Request $request): JsonResponse
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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $validatedData['slug'] = $validatedData['slug'] ?? Str::slug($validatedData['name']);
        if (Destination::where('slug', $validatedData['slug'])->exists()) {
            $validatedData['slug'] .= '-' . Str::random(5); // Menggunakan Str::random yang lebih pendek
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $imagePaths[] = $imageFile->store('destination-images', 'public');
            }
        }
        $validatedData['images'] = $imagePaths;
        $validatedData['created_by'] = auth()->id(); // Mengisi created_by dengan ID admin yang login

        $destination = Destination::create($validatedData);
        $destination->load('category:id,name,slug'); // Load relasi untuk respons

        // 'image_urls' dan 'main_image_url' akan otomatis ditambahkan oleh $appends di model
        return response()->json($destination, 201);
    }

    /**
     * Menampilkan detail destinasi spesifik.
     */
    public function show(Destination $destination): JsonResponse // Menggunakan Route Model Binding
    {
        if (!$destination->is_active && !(auth()->check() && auth()->user()->role === 'admin')) {
            return response()->json(['message' => 'Destinasi tidak ditemukan atau tidak aktif.'], 404);
        }

        $destination->load('category:id,name,slug');
        // 'image_urls' dan 'main_image_url' akan otomatis ditambahkan oleh $appends di model

        // TODO: Load reviews jika sudah ada Review model dan relasi
        // $destination->load(['reviews' => function ($query) {
        //    $query->where('status', 'approved')->with('user:id,name')->latest()->take(5);
        // }]);

        return response()->json($destination);
    }

    /**
     * Mengupdate destinasi spesifik (oleh Admin).
     */
    public function update(Request $request, Destination $destination): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            // Slug biasanya tidak diupdate via API untuk menjaga stabilitas URL
            'description' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|required|uuid|exists:categories,id',
            'location_address' => 'sometimes|nullable|string',
            'latitude' => ['sometimes','nullable', 'numeric', 'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'],
            'longitude' => ['sometimes','nullable', 'numeric', 'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'],
            'ticket_price' => 'sometimes|nullable|numeric|min:0',
            'operational_hours' => 'sometimes|nullable|string',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email|max:255',
            'is_active' => 'sometimes|boolean',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'string',
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $currentImagePaths = $destination->images ?? [];
        if ($request->filled('images_to_delete')) {
            foreach ($request->input('images_to_delete') as $imagePathToDelete) {
                if (in_array($imagePathToDelete, $currentImagePaths)) {
                    Storage::disk('public')->delete($imagePathToDelete);
                    $currentImagePaths = array_values(array_filter($currentImagePaths, fn($path) => $path !== $imagePathToDelete));
                }
            }
        }

        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $imageFile) {
                $currentImagePaths[] = $imageFile->store('destination-images', 'public');
            }
        }

        // Hanya update field 'images' jika ada operasi gambar
        if ($request->hasFile('new_images') || $request->filled('images_to_delete')) {
            $validatedData['images'] = $currentImagePaths;
        } else {
            unset($validatedData['images']); // Hindari mengosongkan jika tidak ada aksi gambar
        }

        unset($validatedData['images_to_delete'], $validatedData['new_images']);

        $destination->update($validatedData);
        $destination->load('category:id,name,slug');
        // 'image_urls' dan 'main_image_url' akan otomatis oleh $appends
        return response()->json($destination);
    }

    /**
     * Menghapus destinasi spesifik (oleh Admin).
     */
    public function destroy(Destination $destination): JsonResponse
    {
        // Hapus gambar terkait dari storage sebelum menghapus record
        if (is_array($destination->images)) {
            foreach ($destination->images as $imagePath) {
                if (!empty($imagePath)) { // Pastikan path tidak kosong
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }

        $destination->delete();

        return response()->json(['message' => 'Destinasi berhasil dihapus.'], 200);
    }
}
