<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Destination; // Pastikan Anda sudah membuat model Destination
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Menggunakan Faker lokal Indonesia
        $categories = Category::all();
        $adminUsers = User::where('role', 'admin')->get();
        $visitorUsers = User::where('role', 'visitor')->get(); // Bisa juga visitor yang membuat destinasi jika diizinkan

        if ($categories->isEmpty() || $adminUsers->isEmpty()) {
            $this.command->info('Tidak ada kategori atau user admin. Silakan jalankan CategorySeeder dan UserSeeder terlebih dahulu.');
            return;
        }

        $destinationsData = [
            // Pantai
            ['name' => 'Pantai Kuta Bali', 'category_name' => 'Pantai', 'address' => 'Kuta, Badung, Bali', 'lat' => -8.7183, 'long' => 115.1686, 'price' => 0, 'hours' => '24 Jam', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Pantai Parangtritis', 'category_name' => 'Pantai', 'address' => 'Kretek, Bantul, Yogyakarta', 'lat' => -8.0218, 'long' => 110.3212, 'price' => 10000, 'hours' => '07:00 - 18:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Pantai Pink Lombok', 'category_name' => 'Pantai', 'address' => 'Jerowaru, Lombok Timur, NTB', 'lat' => -8.8667, 'long' => 116.4333, 'price' => 25000, 'hours' => '08:00 - 17:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],

            // Gunung
            ['name' => 'Gunung Bromo', 'category_name' => 'Gunung', 'address' => 'Probolinggo, Jawa Timur', 'lat' => -7.9425, 'long' => 112.9533, 'price' => 220000, 'hours' => '24 Jam (tergantung izin pendakian)', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Gunung Rinjani', 'category_name' => 'Gunung', 'address' => 'Lombok, NTB', 'lat' => -8.4100, 'long' => 116.4600, 'price' => 150000, 'hours' => 'Pendakian terjadwal', 'phone' => $faker->phoneNumber, 'email' => $faker->email],

            // Budaya & Sejarah
            ['name' => 'Candi Borobudur', 'category_name' => 'Budaya & Sejarah', 'address' => 'Magelang, Jawa Tengah', 'lat' => -7.6079, 'long' => 110.2038, 'price' => 350000, 'hours' => '08:00 - 15:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Candi Prambanan', 'category_name' => 'Budaya & Sejarah', 'address' => 'Sleman, Yogyakarta', 'lat' => -7.7519, 'long' => 110.4914, 'price' => 325000, 'hours' => '08:00 - 15:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Museum Nasional Indonesia', 'category_name' => 'Budaya & Sejarah', 'address' => 'Jakarta Pusat, DKI Jakarta', 'lat' => -6.1760, 'long' => 106.8220, 'price' => 15000, 'hours' => '09:00 - 16:00 (Selasa-Minggu)', 'phone' => $faker->phoneNumber, 'email' => $faker->email],

            // Kuliner
            ['name' => 'Sate Klathak Pak Pong', 'category_name' => 'Kuliner', 'address' => 'Bantul, Yogyakarta', 'lat' => -7.8519, 'long' => 110.3500, 'price' => 30000, 'hours' => '10:00 - 23:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
            ['name' => 'Gudeg Yu Djum Wijilan', 'category_name' => 'Kuliner', 'address' => 'Wijilan, Yogyakarta', 'lat' => -7.8050, 'long' => 110.3700, 'price' => 25000, 'hours' => '06:00 - 22:00', 'phone' => $faker->phoneNumber, 'email' => $faker->email],
        ];

        foreach ($destinationsData as $data) {
            $category = $categories->firstWhere('name', $data['category_name']);
            Destination::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $faker->paragraphs(3, true),
                'location_address' => $data['address'],
                'latitude' => $data['lat'],
                'longitude' => $data['long'],
                'category_id' => $category ? $category->id : $categories->random()->id,
                'created_by' => $adminUsers->random()->id, // Dibuat oleh admin random
                'ticket_price' => $data['price'],
                'operational_hours' => $data['hours'],
                'contact_phone' => $data['phone'],
                'contact_email' => $data['email'],
                'average_rating' => $faker->randomFloat(2, 3, 5), // Rating antara 3.00 - 5.00
                'total_reviews' => $faker->numberBetween(10, 500),
                'is_active' => true,
            ]);
        }

        // Membuat beberapa destinasi tambahan dengan Faker
        for ($i = 0; $i < 10; $i++) {
            $name = $faker->city . ' ' . $faker->randomElement(['Park', 'View', 'Island', 'Point', 'Garden']);
            Destination::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $faker->paragraphs(3, true),
                'location_address' => $faker->address,
                'latitude' => $faker->latitude(-8.5, -6.0), // Sesuaikan rentang lat/long Indonesia
                'longitude' => $faker->longitude(105.0, 115.0),
                'category_id' => $categories->random()->id,
                'created_by' => $adminUsers->random()->id,
                'ticket_price' => $faker->numberBetween(1, 20) * 10000,
                'operational_hours' => $faker->randomElement(['08:00 - 17:00', '09:00 - 21:00', '24 Jam']),
                'contact_phone' => $faker->phoneNumber,
                'contact_email' => $faker->safeEmail,
                'average_rating' => $faker->randomFloat(2, 3, 5),
                'total_reviews' => $faker->numberBetween(5, 200),
                'is_active' => $faker->boolean(90), // 90% aktif
            ]);
        }
    }
}
