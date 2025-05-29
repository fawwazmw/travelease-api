<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category; // Pastikan Anda sudah membuat model Category
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Pantai', 'description' => 'Destinasi wisata pantai dan laut.', 'icon_url' => 'fas fa-umbrella-beach'],
            ['name' => 'Gunung', 'description' => 'Destinasi wisata pendakian dan alam pegunungan.', 'icon_url' => 'fas fa-mountain'],
            ['name' => 'Budaya & Sejarah', 'description' => 'Destinasi wisata candi, museum, dan situs bersejarah.', 'icon_url' => 'fas fa-landmark'],
            ['name' => 'Kuliner', 'description' => 'Tempat-tempat makan dan jajanan khas.', 'icon_url' => 'fas fa-utensils'],
            ['name' => 'Taman Hiburan', 'description' => 'Destinasi rekreasi dan taman bermain.', 'icon_url' => 'fas fa-ferris-wheel'],
            ['name' => 'Alam Terbuka', 'description' => 'Air terjun, danau, hutan, dan keindahan alam lainnya.', 'icon_url' => 'fas fa-tree'],
            ['name' => 'Religi', 'description' => 'Tempat ibadah dan ziarah.', 'icon_url' => 'fas fa-place-of-worship'],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'icon_url' => $category['icon_url'], // Anda bisa mengganti ini dengan URL gambar ikon sebenarnya jika ada
            ]);
        }
    }
}
