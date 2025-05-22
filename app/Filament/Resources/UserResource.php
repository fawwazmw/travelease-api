<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
// use App\Filament\Resources\UserResource\RelationManagers; // Tidak digunakan karena getRelations() kosong
use App\Models\User;
use Filament\Forms; // Diperlukan untuk Forms\Components\DateTimePicker
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; // Diperlukan untuk Tables\Filters\SelectFilter dan berbagai Tables\Actions
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Eloquent\Builder; // Tidak digunakan secara eksplisit di sini
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Model User default tidak pakai SoftDeletes

// Form Components (sudah spesifik diimpor)
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
// Table Columns (sudah spesifik diimpor)
use Filament\Tables\Columns\TextColumn;
// Table Actions (sudah spesifik diimpor)
use Filament\Tables\Actions\Action;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen Akses';
    // navigationSort tidak diset, akan diurutkan berdasarkan nama atau urutan registrasi resource di dalam grup. Ini tidak masalah.

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Nama Lengkap'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->label('Alamat Email'),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->confirmed()
                    ->minLength(8)
                    ->label('Password'),
                TextInput::make('password_confirmation')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false)
                    ->label('Konfirmasi Password'),
                Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'visitor' => 'Visitor',
                    ])
                    ->required()
                    ->default('visitor')
                    ->label('Peran Pengguna'),
                Forms\Components\DateTimePicker::make('email_verified_at') // Menggunakan Forms namespace
                ->label('Email Terverifikasi Pada')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Lengkap'),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->label('Alamat Email'),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'visitor' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Peran'),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Email Terverifikasi'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Dibuat Pada'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role') // Menggunakan Tables namespace
                ->options([
                    'admin' => 'Admin',
                    'visitor' => 'Visitor',
                ])
                    ->label('Filter Peran'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(), // Menggunakan Tables namespace
                Action::make('resend_verification') // Menggunakan Action yang sudah diimpor
                ->label('Kirim Ulang Verifikasi')
                    ->icon('heroicon-o-envelope')
                    ->action(function (User $record) {
                        if (!$record->hasVerifiedEmail()) {
                            $record->sendEmailVerificationNotification();
                            \Filament\Notifications\Notification::make()
                                ->title('Email verifikasi dikirim ulang')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Email sudah terverifikasi')
                                ->warning()
                                ->send();
                        }
                    })
                    ->visible(fn (User $record): bool => !$record->hasVerifiedEmail()),
                Tables\Actions\DeleteAction::make(), // Menggunakan Tables namespace
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([ // Menggunakan Tables namespace
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Komentar ini sudah bagus dan informatif.
     */
}
