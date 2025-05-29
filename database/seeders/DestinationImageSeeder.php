<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Destination;
use App\Models\DestinationImage; // Pastikan Anda sudah membuat model DestinationImage
use Faker\Factory as Faker;
use Illuminate\Support\Str; // <--- TAMBAHKAN BARIS INI

class DestinationImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $destinations = Destination::all();

        if ($destinations->isEmpty()) {
            $this->command->info('Tidak ada destinasi. Jalankan DestinationSeeder terlebih dahulu.');
            return;
        }

        foreach ($destinations as $destination) {
            $imageCount = rand(2, 5); // Setiap destinasi memiliki 2-5 gambar
            $isPrimarySet = false;

            for ($i = 0; $i < $imageCount; $i++) {
                $isPrimary = false;
                if (!$isPrimarySet && ($i === 0 || $faker->boolean(25))) { // Gambar pertama atau 25% kemungkinan jadi primary
                    $isPrimary = true;
                    $isPrimarySet = true;
                }

                DestinationImage::create([
                    'destination_id' => $destination->id,
                    // Gunakan URL gambar placeholder atau dari layanan seperti Picsum/Unsplash
                    'image_url' => 'https://picsum.photos/seed/' . Str::random(10) . '/800/600', // Str::random() sekarang akan dikenali
                    'caption' => $faker->sentence(5),
                    'is_primary' => $isPrimary,
                ]);
            }
            // Pastikan minimal ada satu gambar primary jika belum ada yang ter-set
            if (!$isPrimarySet && $destination->images()->count() > 0) {
                // Periksa apakah ada gambar sebelum mencoba mengakses first()
                $firstImage = $destination->images()->first();
                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }
        }
    }
}
