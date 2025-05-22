<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DestinationResource\Pages;
// use App\Filament\Resources\DestinationResource\RelationManagers; // Dikomentari karena tidak digunakan di getRelations()
use App\Models\Destination;
use App\Models\Category; // Untuk Select Kategori
use Filament\Forms; // Diperlukan untuk Forms\Set
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Actions dan Tables\Filters
use Filament\Tables\Table;
// use Illuminate\Database\Eloquent\Builder; // Tidak digunakan secara eksplisit di sini
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Tidak digunakan, model Destination tidak pakai SoftDeletes
// use Illuminate\Support\Facades\Auth; // Tidak digunakan di file ini
use Illuminate\Support\Str; // Untuk Str::slug dan Str::limit

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
// use Filament\Forms\Components\KeyValue; // Tidak digunakan jika operational_hours pakai Textarea

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;

// Table Filters
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class DestinationResource extends Resource
{
    protected static ?string $model = Destination::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Manajemen Konten Wisata';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Utama')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Destinasi')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(Destination::class, 'slug', ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->helperText('Akan terisi otomatis dari nama destinasi saat pembuatan.'),

                        Select::make('category_id')
                            ->label('Kategori Destinasi')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('ticket_price')
                            ->label('Harga Tiket')
                            ->numeric()
                            ->prefix('IDR')
                            ->minValue(0)
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika destinasi tidak ingin ditampilkan ke publik.'),
                    ]),

                Section::make('Deskripsi & Lokasi')
                    ->schema([
                        RichEditor::make('description')
                            ->label('Deskripsi Lengkap')
                            ->columnSpanFull(),

                        Textarea::make('location_address')
                            ->label('Alamat Lokasi')
                            ->rows(3)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->nullable()
                                ->rules(['regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/'])
                                ->helperText('Contoh: -6.2000000'),
                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->nullable()
                                ->rules(['regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/'])
                                ->helperText('Contoh: 106.816666'),
                        ]),
                    ]),

                Section::make('Detail Tambahan')
                    ->columns(2)
                    ->schema([
                        Textarea::make('operational_hours')
                            ->label('Jam Operasional')
                            ->rows(3)
                            ->helperText('Contoh: Senin - Jumat: 09:00 - 17:00, Sabtu: 10:00 - 15:00'),
                        TextInput::make('contact_phone')
                            ->label('Telepon Kontak')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('contact_email')
                            ->label('Email Kontak')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Gambar Destinasi')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Unggah Gambar (Bisa Lebih dari Satu)')
                            ->multiple()
                            ->directory('destination-images')
                            ->image()
                            ->imageEditor()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->helperText('Pilih satu gambar sebagai gambar utama jika diperlukan.')
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('main_image_url')
                    ->label('Gambar Utama')
                    ->disk('public')
                    ->defaultImageUrl(asset('images/placeholder-destination.png')),

                TextColumn::make('name')
                    ->label('Nama Destinasi')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Destination $record): string => Str::limit($record->description, 40)),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ticket_price')
                    ->label('Harga Tiket')
                    ->money('IDR')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
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
            // RelationManagers\SlotsRelationManager::class,
            // RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDestinations::route('/'),
            'create' => Pages\CreateDestination::route('/create'),
            'edit' => Pages\EditDestination::route('/{record}/edit'),
        ];
    }
}
