<?php

// Namespace ini yang Anda tentukan. Jika widget ini spesifik untuk panel 'admin',
// dan widget lain ada di App\Filament\Admin\Widgets, Anda mungkin ingin menyesuaikannya menjadi
// namespace App\Filament\Admin\Widgets; untuk konsistensi.
// Namun, jika ini adalah direktori widget umum Anda, maka namespace ini sudah benar.
namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Diperlukan untuk DB::raw()

class BookingsChart extends ChartWidget
{
    protected static ?string $heading = 'Pemesanan 7 Hari Terakhir';
    protected static ?int $sort = -1; // Urutan widget, misalnya setelah StatsOverview

    // Interval polling untuk refresh data otomatis (opsional)
    // protected static ?string $pollingInterval = '60s';

    // Properti untuk caching (opsional, bisa meningkatkan performa jika data tidak sering berubah)
    // protected static bool $isLazy = true;

    protected function getData(): array
    {
        // Mengambil data pemesanan: jumlah per hari selama 7 hari terakhir (termasuk hari ini)
        $data = Booking::query()
            ->select(
                DB::raw('DATE(created_at) as date'), // Mengambil tanggal saja dari created_at
                DB::raw('count(*) as count')      // Menghitung jumlah booking per tanggal
            )
            // Mengambil data dari 6 hari yang lalu hingga akhir hari ini
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->where('created_at', '<=', Carbon::now()->endOfDay())
            ->groupBy('date')
            ->orderBy('date', 'ASC') // Urutkan berdasarkan tanggal
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Pemesanan Dibuat',
                    'data' => $data->map(fn ($value) => $value->count)->all(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)', // Warna area di bawah garis
                    'borderColor' => 'rgb(54, 162, 235)',         // Warna garis
                    'borderWidth' => 2,                             // Ketebalan garis
                    'pointBackgroundColor' => 'rgb(54, 162, 235)', // Warna titik data
                    'pointBorderColor' => '#fff',                  // Warna border titik data
                    'pointHoverBackgroundColor' => '#fff',          // Warna titik saat hover
                    'pointHoverBorderColor' => 'rgb(54, 162, 235)',// Warna border titik saat hover
                    // 'fill' => 'start', // Jika ingin area chart (mengisi area di bawah garis)
                    // 'tension' => 0.1, // Untuk membuat garis lebih melengkung (curved)
                ],
            ],
            'labels' => $data->map(fn ($value) => Carbon::parse($value->date)->translatedFormat('D, d M'))->all(), // Format label tanggal (misal: Sen, 23 Mei)
        ];
    }

    protected function getType(): string
    {
        return 'line'; // Tipe chart: 'line', 'bar', 'pie', 'doughnut', 'radar', 'polarArea'
    }
}
