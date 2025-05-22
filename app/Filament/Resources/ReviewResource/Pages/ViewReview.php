<?php

namespace App\Filament\Resources\ReviewResource\Pages; // Sesuaikan dengan resource-mu

use App\Filament\Resources\ReviewResource; // Sesuaikan dengan resource-mu
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord; // <-- PASTIKAN INI BENAR

class ViewReview extends ViewRecord // <-- PASTIKAN KELAS YANG DI-EXTEND ADALAH ViewRecord
{
    protected static string $resource = ReviewResource::class; // Sesuaikan dengan resource-mu

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    // Opsional: Jika kamu menggunakan Infolist untuk tampilan detail
    // public function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    // {
    //     return $infolist
    //         ->schema([
    //             // Definisikan komponen infolist di sini
    //             // Contoh: \Filament\Infolists\Components\TextEntry::make('field_name'),
    //         ]);
    // }
}
