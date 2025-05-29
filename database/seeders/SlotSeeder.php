<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Destination;
use App\Models\Slot; // Pastikan Anda sudah membuat model Slot
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Support\Str; // <--- TAMBAHKAN BARIS INI

class SlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        // Ambil destinasi yang aktif dan memiliki kategori (untuk mengakses category->name)
        $destinations = Destination::where('is_active', true)->with('category')->get();

        if ($destinations->isEmpty()) {
            $this->command->info('Tidak ada destinasi aktif atau destinasi tanpa kategori. Jalankan DestinationSeeder dan pastikan relasi kategori ada.');
            return;
        }

        foreach ($destinations as $destination) {
            // Buat slot untuk 7 hari ke depan
            for ($day = 0; $day < 7; $day++) {
                $slotDate = Carbon::today()->addDays($day);

                // Buat beberapa slot waktu per hari
                $timeSlots = [
                    ['start' => '09:00:00', 'end' => '12:00:00'],
                    ['start' => '13:00:00', 'end' => '16:00:00'],
                ];

                // Jika destinasi adalah tempat kuliner atau buka malam, tambahkan slot malam
                // Pastikan $destination->category tidak null sebelum mengakses name
                $categoryName = $destination->category ? strtolower($destination->category->name) : '';
                $operationalHours = strtolower($destination->operational_hours ?? '');

                if (Str::contains($categoryName, 'kuliner') || Str::contains($operationalHours, 'malam') || Str::contains($operationalHours, '24 jam')) {
                    $timeSlots[] = ['start' => '18:00:00', 'end' => '21:00:00'];
                }


                foreach ($timeSlots as $ts) {
                    // Cek apakah slot sudah ada untuk menghindari duplikasi (jika constraint unik ada)
                    $existingSlot = Slot::where('destination_id', $destination->id)
                        ->where('slot_date', $slotDate->toDateString())
                        ->where('start_time', $ts['start'])
                        ->first();
                    if ($existingSlot) {
                        continue;
                    }

                    Slot::create([
                        'destination_id' => $destination->id,
                        'slot_date' => $slotDate->toDateString(),
                        'start_time' => $ts['start'],
                        'end_time' => $ts['end'],
                        'capacity' => $faker->numberBetween(20, 100),
                        'booked_count' => 0, // Awalnya belum ada yang booking
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
