<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; // Pastikan namespace benar
use Illuminate\Support\Facades\Hash; // Gunakan Hash facade

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@travel.ease', // Ganti dengan email admin
            'password' => Hash::make('rahasia123'), // GANTI dengan password kuat
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Anda bisa menambahkan user admin lain jika perlu
    }
}
