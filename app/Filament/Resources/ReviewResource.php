<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
// use App\Filament\Resources\ReviewResource\RelationManagers; // Tidak digunakan saat ini
use App\Models\Review;
use App\Models\User;
use App\Models\Destination;
use Filament\Forms; // Diperlukan untuk Forms\Components\DateTimePicker
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Actions dan Tables\Filters
use Filament\Tables\Table;
// use Illuminate\Database\Eloquent\Builder; // Tidak digunakan secara eksplisit di sini
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Tidak digunakan, model Review tidak pakai SoftDeletes
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\TextInput; // Tidak digunakan di form ini
// use Filament\Forms\Components\Placeholder; // Kode yang menggunakan ini dikomentari
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Tables\Columns\TextColumn;
// use Filament\Tables\Columns\IconColumn; // Tidak digunakan di tabel ini
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Collection;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Manajemen Interaksi Pengguna';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return 'Ulasan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Ulasan';
    }

    public static function getModelLabel(): string
    {
        return 'Ulasan';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detail Ulasan')->schema([
                    Select::make('user_id')
                        ->label('Pengguna')
                        ->relationship('user', 'name')
                        ->disabled()
                        ->searchable()
                        ->preload(),
                    Select::make('destination_id')
                        ->label('Destinasi')
                        ->relationship('destination', 'name')
                        ->disabled()
                        ->searchable()
                        ->preload(),
                    ViewField::make('rating_display')
                        ->label('Rating')
                        ->view('filament.forms.components.star-rating-display')
                        ->columnSpanFull()
                        ->formatStateUsing(fn (?Review $record): int => $record ? $record->rating : 0),
                    Textarea::make('comment')
                        ->label('Komentar Ulasan')
                        ->disabled()
                        ->rows(5)
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label('Status Ulasan')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                    KeyValue::make('images_urls')
                        ->label('Gambar Unggahan (Path)')
                        ->disabled()
                        ->columnSpanFull()
                        ->visible(fn (?Review $record) => !empty($record?->images_urls)),
                    Forms\Components\DateTimePicker::make('created_at') // Menggunakan Forms\Components namespace
                    ->label('Tanggal Ulasan Dibuat')
                        ->disabled(),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Destinasi')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->numeric() // numeric() tidak diperlukan jika formatStateUsing sudah menghasilkan string
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ? str_repeat('⭐', (int)$state) . str_repeat('☆', 5 - (int)$state) : '-'), // Tambahkan pengecekan $state
                TextColumn::make('comment')
                    ->label('Komentar Singkat')
                    ->limit(50)
                    ->tooltip(fn (Review $record) => $record->comment)
                    ->wrap(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Tanggal Ulasan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('destination_id')
                    ->label('Destinasi')
                    ->relationship('destination', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('rating')
                    ->options([
                        1 => '1 Bintang',
                        2 => '2 Bintang',
                        3 => '3 Bintang',
                        4 => '4 Bintang',
                        5 => '5 Bintang',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->label('Ubah Status'),
                Tables\Actions\DeleteAction::make(),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Review $record) => $record->update(['status' => 'approved']))
                    ->requiresConfirmation()
                    ->visible(fn (Review $record): bool => $record->status !== 'approved'),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(fn (Review $record) => $record->update(['status' => 'rejected']))
                    ->requiresConfirmation()
                    ->visible(fn (Review $record): bool => $record->status !== 'rejected'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    BulkAction::make('approve_selected')
                        ->label('Setujui Terpilih')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'approved']))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject_selected')
                        ->label('Tolak Terpilih')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'rejected']))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
            'view' => Pages\ViewReview::route('/{record}'),
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Review $review) {
            // Pastikan relasi destination ada sebelum memanggil methodnya
            if ($review->destination && ($review->isDirty('status') || $review->wasRecentlyCreated)) {
                $review->destination->updateAverageRating();
            }
        });

        static::deleted(function (Review $review) {
            // Pastikan relasi destination ada
            if ($review->destination) {
                $review->destination->updateAverageRating();
            }
        });
    }
}
