<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            DestinationSeeder::class, // Destination harus ada sebelum DestinationImage dan Slot
            DestinationImageSeeder::class,
            SlotSeeder::class,
            // Anda bisa menambahkan BookingSeeder dan ReviewSeeder di sini nanti
            // BookingSeeder::class,
            // ReviewSeeder::class,
        ]);
    }
}
