<?php

// Namespace ini yang Anda tentukan. Jika widget ini spesifik untuk panel 'admin',
// dan widget lain ada di App\Filament\Admin\Widgets, Anda mungkin ingin menyesuaikannya menjadi
// namespace App\Filament\Admin\Widgets; untuk konsistensi.
// Namun, jika ini adalah direktori widget umum Anda, maka namespace ini sudah benar.
namespace App\Filament\Widgets;

use App\Filament\Resources\BookingResource; // Diperlukan untuk BookingResource::getUrl()
use App\Models\Booking;
use Filament\Tables; // Diperlukan untuk Tables\Actions\Action
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class RecentBookingsTable extends BaseWidget
{
    protected static ?int $sort = 0; // Urutan widget, misalnya setelah chart
    protected int | string | array $columnSpan = 'full'; // Agar widget mengambil lebar penuh kolom dashboard

    protected static ?string $heading = 'Pemesanan Terbaru';

    // Properti untuk caching (opsional, bisa meningkatkan performa jika data tidak sering berubah)
    // protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->with([
                        'user:id,name', // Eager load hanya kolom yang dibutuhkan dari user
                        'destination:id,name' // Eager load hanya kolom yang dibutuhkan dari destinasi
                    ])
                    ->latest() // Mengambil data terbaru berdasarkan 'created_at'
                    ->limit(5) // Batasi hanya 5 record
            )
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Kode Booking')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable(), // Bisa di-sort berdasarkan nama pengguna
                TextColumn::make('destination.name')
                    ->label('Destinasi')
                    ->searchable()
                    ->sortable() // Bisa di-sort berdasarkan nama destinasi
                    ->wrap(),
                TextColumn::make('visit_date')
                    ->label('Tgl Kunjungan')
                    ->date()
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'primary' => 'completed',
                        'danger' => fn ($state): bool => in_array($state, ['cancelled', 'expired']),
                    ])
                    ->sortable(), // Bisa di-sort berdasarkan status
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye') // Tambahkan ikon untuk konsistensi
                    ->url(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record])),
                // Contoh jika ingin menambahkan EditAction:
                // Tables\Actions\EditAction::make()
                //     ->url(fn (Booking $record): string => BookingResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Belum ada pemesanan terbaru.')
            ->emptyStateDescription('Ketika ada pemesanan baru, akan muncul di sini.') // Tambahkan deskripsi untuk empty state
            ->paginated(false); // Karena kita sudah limit 5, paginasi mungkin tidak perlu untuk widget ini
    }
}
