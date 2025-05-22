<?php

// Namespace ini yang Anda tentukan. Jika widget ini spesifik untuk panel 'admin'
// dan widget lain ada di App\Filament\Admin\Widgets, Anda mungkin ingin menyesuaikannya menjadi
// namespace App\Filament\Admin\Widgets; untuk konsistensi.
// Namun, jika ini adalah direktori widget umum Anda, maka namespace ini sudah benar.
namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Import Resource classes untuk mendapatkan URL
use App\Filament\Resources\DestinationResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\ReviewResource;

class TravelEaseStatsOverview extends BaseWidget
{
    protected static ?int $sort = -2; // Urutan widget di dashboard, -2 agar di paling atas

    // Interval polling untuk refresh data otomatis (opsional)
    // protected static ?string $pollingInterval = '15s';

    // Properti untuk caching (opsional, bisa meningkatkan performa jika data tidak sering berubah)
    // protected static bool $isLazy = true;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Destinasi', Destination::count())
                ->description('Jumlah semua destinasi wisata')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success')
                ->url(DestinationResource::getUrl('index')),

            Stat::make('Total Kategori', Category::count())
                ->description('Jumlah kategori destinasi')
                ->descriptionIcon('heroicon-m-tag')
                ->color('info')
                ->url(CategoryResource::getUrl('index')),

            Stat::make('Total Pengguna', User::count())
                ->description(User::where('role', 'admin')->count() . ' Admin, ' . User::where('role', 'visitor')->count() . ' Visitor')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->url(UserResource::getUrl('index')),

            Stat::make('Total Pemesanan', Booking::count())
                ->description(Booking::where('status', 'pending')->count() . ' Pending, ' . Booking::where('status', 'confirmed')->count() . ' Confirmed')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning')
                ->url(BookingResource::getUrl('index')),

            Stat::make('Ulasan Menunggu Moderasi', Review::where('status', 'pending')->count())
                ->description(Review::where('status', 'approved')->count() . ' sudah disetujui')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('danger')
                ->url(ReviewResource::getUrl('index')),
        ];
    }
}
