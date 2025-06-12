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
*/

// == Rute Publik (Tidak Perlu Login) ==

// Autentikasi
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.password.email');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.password.update');

// Verifikasi Email (URL dari email)
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Data Publik (Kategori, Destinasi, Slot, Review)
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('api.categories.show');

Route::get('/destinations', [DestinationController::class, 'index'])->name('api.destinations.index');
Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show'])->name('api.destinations.show');

Route::get('/destinations/{destinationSlugOrId}/slots', [SlotController::class, 'getAvailableSlots'])->name('api.destinations.slots.available');
Route::get('/destinations/{destinationSlugOrId}/reviews', [ReviewController::class, 'indexForDestination'])->name('api.destinations.reviews.index');


// == Rute Terproteksi (Wajib Login) ==

Route::middleware('auth:sanctum')->group(function () {

    // --- Rute Pengguna ---
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['throttle:6,1'])
        ->name('api.verification.send');

    // --- Rute Booking (Semua method butuh login) ---
    Route::apiResource('bookings', BookingController::class)->except(['update']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('api.bookings.cancel');

    // --- Rute Review (Hanya aksi tertentu yang butuh login) ---
    Route::post('/reviews', [ReviewController::class, 'store'])->name('api.reviews.store');
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('api.reviews.show'); // Untuk user lihat review sendiri
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('api.reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('api.reviews.destroy');


    // == Rute Khusus Admin (Wajib Login & Role Admin) ==

    Route::middleware('isAdmin')->group(function () {
        // Rute untuk mengelola destinasi oleh Admin
        Route::post('/destinations', [DestinationController::class, 'store'])->name('api.destinations.store');
        Route::put('/destinations/{destination}', [DestinationController::class, 'update'])->name('api.destinations.update');
        Route::delete('/destinations/{destination}', [DestinationController::class, 'destroy'])->name('api.destinations.destroy');

        // Tambahkan rute admin lain di sini jika ada (misal: kelola kategori, slot, dll)
        // Route::post('/categories', [CategoryController::class, 'store']);
    });
});


// == Fallback Route ==
// Akan dijalankan jika tidak ada rute lain yang cocok
Route::fallback(function(){
    return response()->json(['message' => 'Endpoint tidak ditemukan.'], 404);
});
