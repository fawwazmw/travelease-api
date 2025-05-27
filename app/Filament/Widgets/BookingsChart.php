<?php

namespace App\Filament\Widgets;
// Sesuaikan namespace jika panel-specific

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingsChart extends ChartWidget
{
    protected static ?string $heading = 'Pemesanan 7 Hari Terakhir';
    protected static ?int $sort = -1;

    protected function getData(): array
    {
        $data = Booking::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->where('created_at', '<=', Carbon::now()->endOfDay())
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Pemesanan Dibuat',
                    'data' => $data->map(fn($value) => $value->count)->all(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.5)', // Warna untuk bar chart (contoh)
                    'borderColor' => 'rgb(75, 192, 192)',         // Warna border bar
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data->map(fn($value) => Carbon::parse($value->date)->translatedFormat('D, d M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // Tipe chart: 'line', 'bar', 'pie', 'doughnut', 'radar', 'polarArea'
    }
}
