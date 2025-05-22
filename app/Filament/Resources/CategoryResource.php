<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
// use App\Filament\Resources\CategoryResource\RelationManagers; // Dikomentari karena tidak digunakan di getRelations()
use App\Models\Category;
use Filament\Forms; // Diperlukan untuk Forms\Set
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Actions dan Tables\Filters
use Filament\Tables\Table;
// use Illuminate\Database\Eloquent\Builder; // Tidak digunakan secara eksplisit di sini
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Tidak digunakan, model Category tidak pakai SoftDeletes
use Illuminate\Support\Str;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Manajemen Konten Wisata';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    TextInput::make('name')
                        ->label('Nama Kategori')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                    TextInput::make('slug')
                        ->label('Slug (URL)')
                        ->required()
                        ->maxLength(255)
                        ->unique(Category::class, 'slug', ignoreRecord: true)
                        ->disabled(fn (string $operation): bool => $operation !== 'create')
                        ->helperText('Akan terisi otomatis dari nama kategori saat pembuatan.'),

                    Textarea::make('description')
                        ->label('Deskripsi Kategori')
                        ->rows(3)
                        ->nullable(),

                    FileUpload::make('icon_url')
                        ->label('Ikon Kategori (Opsional)')
                        ->directory('category-icons')
                        ->image()
                        ->imageEditor()
                        ->nullable()
                        ->helperText('Unggah gambar ikon untuk kategori ini.'),
                ])->columns(1)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon_url')
                    ->label('Ikon')
                    ->disk('public')
                    ->defaultImageUrl(asset('images/placeholder-category.png'))
                    ->circular(),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('destinations_count')
                    ->counts('destinations')
                    ->label('Jumlah Destinasi')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tidak ada filter
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
            // RelationManagers\DestinationsRelationManager::class, // Tetap dikomentari jika belum dibuat
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
