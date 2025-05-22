<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlotResource\Pages;
// use App\Filament\Resources\SlotResource\RelationManagers; // Tidak digunakan saat ini
use App\Models\Slot;
use App\Models\Destination; // Untuk Select Destinasi
// use Filament\Forms; // Tidak digunakan secara langsung jika semua komponen spesifik diimpor
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Filters\TernaryFilter dan Tables\Actions
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // Digunakan dalam closure query filter
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Tidak digunakan, model Slot tidak pakai SoftDeletes
use Carbon\Carbon; // Ditambahkan untuk Carbon::parse()

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

// Table Filters
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class SlotResource extends Resource
{
    protected static ?string $model = Slot::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Manajemen Konten Wisata';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'slot_date';

    public static function getNavigationLabel(): string
    {
        return 'Slot Kunjungan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Slot Kunjungan';
    }

    public static function getModelLabel(): string
    {
        return 'Slot Kunjungan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detail Slot Kunjungan')->schema([
                    Select::make('destination_id')
                        ->label('Destinasi')
                        ->relationship('destination', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    DatePicker::make('slot_date')
                        ->label('Tanggal Slot')
                        ->required()
                        ->native(false),

                    TimePicker::make('start_time')
                        ->label('Waktu Mulai (Opsional)')
                        ->seconds(false)
                        ->nullable(),

                    TimePicker::make('end_time')
                        ->label('Waktu Selesai (Opsional)')
                        ->seconds(false)
                        ->nullable(),

                    TextInput::make('capacity')
                        ->label('Kapasitas Slot')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),

                    TextInput::make('booked_count')
                        ->label('Jumlah Terpesan')
                        ->numeric()
                        ->default(0)
                        ->disabled()
                        ->helperText('Diupdate otomatis berdasarkan pemesanan.'),

                    Toggle::make('is_active')
                        ->label('Status Slot Aktif')
                        ->default(true),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('destination.name')
                    ->label('Destinasi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slot_date')
                    ->label('Tanggal Slot')
                    ->date()
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Waktu Mulai')
                    ->time('H:i')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('end_time')
                    ->label('Waktu Selesai')
                    ->time('H:i')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('booked_count')
                    ->label('Terpesan')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('destination_id')
                    ->label('Filter Destinasi')
                    ->relationship('destination', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('slot_date')
                    ->form([
                        DatePicker::make('slot_date_from')->label('Dari Tanggal')->native(false),
                        DatePicker::make('slot_date_to')->label('Sampai Tanggal')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['slot_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('slot_date', '>=', $date),
                            )
                            ->when(
                                $data['slot_date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('slot_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['slot_date_from'] && ! $data['slot_date_to']) {
                            return null;
                        }
                        $from = $data['slot_date_from'] ? Carbon::parse($data['slot_date_from'])->translatedFormat('d M Y') : null;
                        $to = $data['slot_date_to'] ? Carbon::parse($data['slot_date_to'])->translatedFormat('d M Y') : null;

                        if ($from && $to) return "Slot dari {$from} sampai {$to}";
                        if ($from) return "Slot dari {$from}";
                        if ($to) return "Slot sampai {$to}";
                        return null;
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\BookingsRelationManager::class, // Sesuai, dikomentari jika belum ada
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlots::route('/'),
            'create' => Pages\CreateSlot::route('/create'),
            'edit' => Pages\EditSlot::route('/{record}/edit'),
        ];
    }
}
