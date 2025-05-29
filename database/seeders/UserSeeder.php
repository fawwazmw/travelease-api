<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin Utama (seperti yang sudah Anda buat)
        User::create([
            'name' => 'Admin Utama TravelEase',
            'email' => 'admin@travel.ease',
            'password' => Hash::make('password'), // Ganti dengan password yang kuat
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // User Admin Tambahan
        User::create([
            'name' => 'Admin Staff',
            'email' => 'staff@travel.ease',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Beberapa User Visitor
        User::factory()->count(10)->create([
            'role' => 'visitor', // Pastikan factory Anda tidak menimpa role ini jika ada default lain
            'email_verified_at' => now(), // Anggap semua user visitor sudah verifikasi email untuk kemudahan
        ]);

        // User Visitor Spesifik untuk Testing
        User::create([
            'name' => 'Budi Pengunjung',
            'email' => 'budi@example.com',
            'password' => Hash::make('password'),
            'role' => 'visitor',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Ani Pelancong',
            'email' => 'ani@example.com',
            'password' => Hash::make('password'),
            'role' => 'visitor',
            'email_verified_at' => now(),
        ]);
    }
}
