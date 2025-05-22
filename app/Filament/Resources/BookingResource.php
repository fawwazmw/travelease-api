<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
// use App\Filament\Resources\BookingResource\RelationManagers; // Tidak digunakan saat ini
use App\Models\Booking;
use App\Models\User;
use App\Models\Destination;
use App\Models\Slot;
use Filament\Forms; // Diperlukan untuk Forms\Set
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Actions dan Tables\Filters
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Tidak digunakan karena model Booking tidak pakai SoftDeletes
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker; // Tidak digunakan di form ini
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;
use Illuminate\Support\Str; // Ditambahkan untuk Str::random

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Manajemen Interaksi Pengguna';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'booking_code';

    public static function getNavigationLabel(): string
    {
        return 'Pemesanan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pemesanan';
    }

    public static function getModelLabel(): string
    {
        return 'Pemesanan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detail Pemesanan')->schema([
                    TextInput::make('booking_code')
                        ->label('Kode Booking')
                        ->disabled()
                        // Model Booking sudah menghandle pembuatan booking_code via boot()
                        // Default ini hanya untuk tampilan awal di form create sebelum disimpan
                        ->default(fn () => 'TRV-' . strtoupper(Str::random(8)))
                        ->helperText('Kode booking akan ter-generate otomatis oleh sistem.'),

                    Select::make('user_id')
                        ->label('Pengguna')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('destination_id')
                        ->label('Destinasi')
                        ->relationship('destination', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('slot_id', null)), // Gunakan Forms\Set

                    Select::make('slot_id')
                        ->label('Slot Kunjungan (Opsional)')
                        ->relationship(
                            name: 'slot',
                            titleAttribute: 'id', // Atau atribut lain yang unik dari slot
                            modifyQueryUsing: function (Builder $query, Forms\Get $get) { // Gunakan Forms\Get
                                $destinationId = $get('destination_id');
                                if ($destinationId) {
                                    return $query->where('destination_id', $destinationId)->where('is_active', true);
                                }
                                return $query->whereRaw('1 = 0');
                            }
                        )
                        ->getOptionLabelFromRecordUsing(fn (Slot $record) => "{$record->slot_date->format('d M Y')} (" . ($record->start_time ? Carbon::parse($record->start_time)->format('H:i') : 'N/A') . " - " . ($record->end_time ? Carbon::parse($record->end_time)->format('H:i') : 'N/A') . ") Kap: {$record->capacity}")
                        ->searchable(['slot_date']) // Cari berdasarkan tanggal di tabel slot
                        ->preload()
                        ->nullable(),

                    DatePicker::make('visit_date')
                        ->label('Tanggal Kunjungan')
                        ->required()
                        ->native(false),

                    TextInput::make('num_tickets')
                        ->label('Jumlah Tiket')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    TextInput::make('total_price')
                        ->label('Total Harga')
                        ->numeric()
                        ->prefix('IDR')
                        ->required()
                        ->helperText('Hitung manual atau akan dihitung sistem saat booking via API.'),

                    Select::make('status')
                        ->label('Status Pemesanan')
                        ->options([
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'cancelled' => 'Cancelled',
                            'completed' => 'Completed',
                            'expired' => 'Expired',
                        ])
                        ->required()
                        ->default('pending'),

                    TextInput::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->nullable(),
                    TextInput::make('payment_id_external')
                        ->label('ID Pembayaran Eksternal')
                        ->nullable(),
                    Textarea::make('payment_details')
                        ->label('Detail Pembayaran (JSON)')
                        ->nullable()
                        ->rows(3),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Kode Booking')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Destinasi')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('visit_date')
                    ->label('Tgl Kunjungan')
                    ->date()
                    ->sortable(),
                TextColumn::make('num_tickets')
                    ->label('Tiket')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Total Harga')
                    ->money('IDR')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'primary' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'expired']),
                    ])
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Tgl Pemesanan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'expired' => 'Expired',
                    ]),
                SelectFilter::make('destination_id')
                    ->label('Destinasi')
                    ->relationship('destination', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('visit_date')
                    ->form([
                        DatePicker::make('visit_date_from')->label('Dari Tanggal')->native(false),
                        DatePicker::make('visit_date_to')->label('Sampai Tanggal')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['visit_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '>=', $date),
                            )
                            ->when(
                                $data['visit_date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['visit_date_from'] && ! $data['visit_date_to']) return null;
                        $from = $data['visit_date_from'] ? Carbon::parse($data['visit_date_from'])->translatedFormat('d M Y') : null;
                        $to = $data['visit_date_to'] ? Carbon::parse($data['visit_date_to'])->translatedFormat('d M Y') : null;
                        if ($from && $to) return "Kunjungan dari {$from} sampai {$to}";
                        if ($from) return "Kunjungan dari {$from}";
                        if ($to) return "Kunjungan sampai {$to}";
                        return null;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(), // Tetap dikomentari untuk keamanan
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
