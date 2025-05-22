<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ReviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rute publik untuk autentikasi
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.password.email');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.password.update');

// Rute yang memerlukan autentikasi (menggunakan middleware sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    // Mengirim ulang email verifikasi (memerlukan user login)
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['throttle:6,1']) // Batasi pengiriman ulang: 6 kali per menit
        ->name('api.verification.send');
});

// Verifikasi email (dipanggil dari link di email)
// Route ini harus memiliki nama 'verification.verify' agar notifikasi email Laravel berfungsi dengan benar
// Middleware 'signed' memastikan URL tidak diubah.
// Middleware 'throttle:6,1' untuk keamanan tambahan.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// --- Destination Routes ---
// Rute publik untuk mendapatkan destinasi
Route::get('/destinations', [DestinationController::class, 'index'])->name('api.destinations.index');
Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show'])->name('api.destinations.show'); // Bisa pakai slug atau ID

// Rute yang memerlukan autentikasi admin untuk manajemen destinasi
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () { // 'isAdmin' adalah contoh middleware role
    Route::post('/destinations', [DestinationController::class, 'store'])->name('api.destinations.store');
    Route::put('/destinations/{destination}', [DestinationController::class, 'update'])->name('api.destinations.update'); // Menggunakan PUT untuk update keseluruhan
    // Atau bisa juga POST dengan _method=PUT jika klien kesulitan dengan PUT
    // Route::post('/destinations/{destination}', [DestinationController::class, 'update'])->name('api.destinations.update');
    Route::delete('/destinations/{destination}', [DestinationController::class, 'destroy'])->name('api.destinations.destroy');
});

// --- Category Routes ---
// Rute publik untuk mendapatkan kategori
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('api.categories.show'); // Menggunakan slug

// Jika kamu memerlukan endpoint untuk admin mengelola kategori via API (biasanya tidak perlu jika sudah ada Filament)
// Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
//     Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');
//     Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.categories.update');
//     Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.categories.destroy');
// });

// --- Slot Routes ---
// Rute publik untuk mendapatkan slot tersedia untuk sebuah destinasi
Route::get('/destinations/{destinationSlugOrId}/slots', [SlotController::class, 'getAvailableSlots'])
    ->name('api.destinations.slots.available');

// Jika kamu memerlukan endpoint untuk admin mengelola slot via API (biasanya tidak perlu jika sudah ada Filament)
// Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
//     Route::apiResource('slots', SlotController::class)->except(['index']); // 'index' mungkin berbeda untuk admin
//     Route::get('/admin/slots', [SlotController::class, 'adminIndex'])->name('api.admin.slots.index'); // Contoh admin index
// });

// --- Booking Routes (Memerlukan Autentikasi Pengguna) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('api.bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('api.bookings.show'); // Menggunakan Route Model Binding
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('api.bookings.cancel'); // Menggunakan POST untuk aksi pembatalan
});

// --- Review Routes ---
// Rute publik untuk mendapatkan ulasan destinasi
Route::get('/destinations/{destinationSlugOrId}/reviews', [ReviewController::class, 'indexForDestination'])
    ->name('api.destinations.reviews.index');

// Rute yang memerlukan autentikasi pengguna
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reviews', [ReviewController::class, 'store'])->name('api.reviews.store');
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('api.reviews.show'); // Untuk user lihat review sendiri
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('api.reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('api.reviews.destroy');
});

// Fallback route untuk request ke endpoint yang tidak ada (opsional)
Route::fallback(function(){
    return response()->json([
        'message' => 'Endpoint tidak ditemukan. Jika error berlanjut, hubungi admin.'], 404);
});
