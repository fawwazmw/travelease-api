<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

Route::get('/', function () {
    $adminPanel = filament()->getPanel('admin');

    if (LaravelAuth::check() && LaravelAuth::user()->canAccessPanel($adminPanel)) {
        return redirect()->route('filament.admin.pages.dashboard');
    }
    return redirect()->route('filament.admin.auth.login');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth:web'])->group(function () { // Pastikan guard 'web' atau sesuai panel Filament Anda

    Route::get('/email/verify', function (Request $request) {
        if ($request->user() && $request->user()->hasVerifiedEmail()) {
            $panel = filament()->getCurrentPanel() ?? filament()->getDefaultPanel();
            return redirect()->intended($panel->getUrl());
        }
        // Pastikan view 'auth.verify-email' ada atau ganti dengan view yang sesuai
        if (!view()->exists('auth.verify-email')) {
            // Fallback jika view tidak ada, bisa redirect atau tampilkan pesan sederhana
            return "Silakan verifikasi email Anda. Tautan telah dikirim.";
        }
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            $panel = filament()->getCurrentPanel() ?? filament()->getDefaultPanel();
            return redirect()->intended($panel->getUrl());
        }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware(['throttle:6,1'])->name('verification.send');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $panel = filament()->getCurrentPanel() ?? filament()->getDefaultPanel();
        $redirectUrl = $panel->getUrl();

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($redirectUrl .'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($request->user()));
        }
        return redirect()->intended($redirectUrl .'?verified=1');
    })->middleware(['signed'])->name('verification.verify');
});

// require __DIR__.'/auth.php';
