<?php

namespace App\Filament\Pages\Admin; // Namespace ini sudah benar jika file-nya ada di app/Filament/Pages/Admin/

use Filament\Pages\Dashboard as BaseDashboard;
// Sesuaikan path ke widget jika berbeda. Asumsi ada di App\Filament\Admin\Widgets
use App\Filament\Widgets\TravelEaseStatsOverview;
use App\Filament\Widgets\BookingsChart;
use App\Filament\Widgets\RecentBookingsTable;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $routePath = '/'; // Ini akan menjadi halaman utama panel admin
    protected static ?int $navigationSort = -2; // Urutan tertinggi di sidebar

    /**
     * Mengambil judul halaman.
     */
    public function getTitle(): string | Htmlable
    {
        return __('Dashboard Utama TravelEase');
    }

    /**
     * Mengambil label navigasi untuk sidebar.
     */
    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    /**
     * Mendefinisikan widget yang akan ditampilkan di bagian header dashboard.
     *
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TravelEaseStatsOverview::class,
        ];
    }

    /**
     * Mendefinisikan widget yang akan ditampilkan di bagian utama/body dashboard.
     *
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            BookingsChart::class,
            RecentBookingsTable::class,
        ];
    }

    /**
     * Mendefinisikan jumlah kolom untuk layout widget di body.
     *
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 1; // Atau 2, atau ['md' => 2] untuk layout responsif
    }
}
