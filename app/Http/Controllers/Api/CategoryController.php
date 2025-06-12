<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource; // <-- DITAMBAHKAN
use App\Http\Resources\DestinationResource; // <-- DITAMBAHKAN
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource; // <-- DITAMBAHKAN

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar semua kategori beserta jumlah destinasi aktif.
     */
    public function index(): JsonResource // <-- Tipe return diubah
    {
        $categories = Category::query()
            // withCount sudah benar
            ->withCount(['destinations' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name', 'asc')
            ->get();

        // <-- DIPERBARUI: Blok `transform` dihapus total.
        // Cukup kembalikan collection resource.
        return CategoryResource::collection($categories);
    }

    /**
     * Menampilkan detail kategori spesifik beserta destinasi aktif di dalamnya (dengan paginasi).
     */
    public function show(Request $request, string $slug): CategoryResource // <-- Tipe return diubah
    {
        $category = Category::where('slug', $slug)
            ->withCount(['destinations' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail(); // Menggunakan firstOrFail untuk otomatis 404 jika tidak ditemukan

        // <-- DIPERBARUI: Logika pemuatan destinasi menjadi lebih rapi
        // Kita memuat relasi 'destinations' ke dalam model kategori yang sudah ditemukan.
        // Resource akan otomatis mendeteksi relasi yang sudah dimuat ini.
        $category->load([
            'destinations' => function ($query) use ($request) {
                $query->where('is_active', true)
                    ->with('images'); // <-- PENTING: Eager load gambar untuk destinasi!

                // Logika filter dan sort destinasi (sudah benar)
                if ($request->filled('search_destination')) {
                    $searchTerm = $request->search_destination;
                    $query->where(fn ($q) => $q->where('name', 'like', "%{$searchTerm}%")->orWhere('description', 'like', "%{$searchTerm}%"));
                }

                $sortBy = $request->input('sort_by_destination', 'created_at');
                $sortOrder = $request->input('sort_order_destination', 'desc');
                $allowedSortColumns = ['name', 'created_at', 'ticket_price', 'average_rating'];
                if (in_array($sortBy, $allowedSortColumns)) {
                    $query->orderBy($sortBy, $sortOrder);
                }

                // Lakukan paginasi di dalam closure relasi
                $query->paginate($request->input('per_page_destination', 9));
            }
        ]);

        // <-- DIPERBARUI: Blok pembuatan array manual dihapus total.
        // Cukup kembalikan resource tunggal. Laravel akan menangani sisanya.
        return new CategoryResource($category);
    }
}
