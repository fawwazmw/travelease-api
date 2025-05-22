<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Destination; // Diperlukan untuk type-hinting pada transform di show()
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage; // Untuk akses URL ikon

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar semua kategori beserta jumlah destinasi aktif.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            // Menghitung hanya destinasi yang aktif untuk setiap kategori
            ->withCount(['destinations' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('name', 'asc')
            ->get();

        // Menambahkan URL ikon publik ke setiap kategori
        $categories->transform(function (Category $category) {
            if ($category->icon_url) {
                $category->icon_public_url = Storage::disk('public')->url($category->icon_url);
            } else {
                // Pastikan file placeholder ini ada di public/images/placeholder-category.png
                $category->icon_public_url = asset('images/placeholder-category.png');
            }
            // Mengganti nama default 'destinations_count' agar lebih jelas
            $category->active_destinations_count = $category->destinations_count;
            unset($category->destinations_count); // Hapus properti asli jika nama baru digunakan

            return $category;
        });

        return response()->json(['data' => $categories]);
    }

    /**
     * Menampilkan detail kategori spesifik beserta destinasi aktif di dalamnya (dengan paginasi).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $slug // Menggunakan slug kategori
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            // Menghitung hanya destinasi yang aktif
            ->withCount(['destinations' => function ($query) {
                $query->where('is_active', true);
            }])
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        // Menambahkan URL ikon publik
        if ($category->icon_url) {
            $category->icon_public_url = Storage::disk('public')->url($category->icon_url);
        } else {
            $category->icon_public_url = asset('images/placeholder-category.png');
        }
        // Mengganti nama default 'destinations_count'
        $category->active_destinations_count = $category->destinations_count;
        unset($category->destinations_count);

        // Ambil destinasi terkait yang aktif dengan paginasi
        $destinationsQuery = $category->destinations()->where('is_active', true);

        // Pencarian destinasi dalam kategori
        if ($request->filled('search_destination')) {
            $searchTerm = $request->search_destination;
            $destinationsQuery->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Urutkan destinasi
        $sortBy = $request->input('sort_by_destination', 'created_at');
        $sortOrder = $request->input('sort_order_destination', 'desc');
        // Daftar kolom yang diizinkan untuk sorting destinasi
        $allowedSortColumns = ['name', 'created_at', 'ticket_price', 'average_rating'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $destinationsQuery->orderBy($sortBy, $sortOrder);
        }

        $destinations = $destinationsQuery->paginate($request->input('per_page_destination', 9));

        // Menambahkan URL gambar ke setiap destinasi
        // Ini berfungsi jika accessor `image_urls` ada di model Destination
        // dan model Destination sudah di-load atau di-transform dengan benar.
        $destinations->getCollection()->transform(function (Destination $destination) {
            // Memastikan accessor terpanggil dan hasilnya diserialisasi
            $destination->image_urls = $destination->image_urls;
            // unset($destination->images); // Opsional: jika tidak ingin menampilkan path asli
            return $destination;
        });

        // Gabungkan data kategori dengan destinasi yang sudah dipaginasi
        $categoryData = $category->toArray();
        $categoryData['destinations_paginated'] = $destinations;

        return response()->json(['data' => $categoryData]);
    }

    // Metode store, update, destroy untuk kategori bisa ditambahkan di sini
    // dan diproteksi dengan middleware 'auth:sanctum' dan 'isAdmin' jika diperlukan.
    // Contoh:
    // public function __construct()
    // {
    //     $this->middleware(['auth:sanctum', 'isAdmin'])->except(['index', 'show']);
    // }
    // Namun, untuk saat ini, kita asumsikan manajemen kategori utama dilakukan via Filament.
}
