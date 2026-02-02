<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run()
    {
        $brands = [
            [
                'name' => 'Apple',
                'slug' => 'apple',
                'description' => 'Innovative technology products',
                'logo' => 'brands/apple.png',
                'website' => 'https://www.apple.com',
                'order' => 1,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'description' => 'Electronics and home appliances',
                'logo' => 'brands/samsung.png',
                'website' => 'https://www.samsung.com',
                'order' => 2,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Nike',
                'slug' => 'nike',
                'description' => 'Athletic footwear and apparel',
                'logo' => 'brands/nike.png',
                'website' => 'https://www.nike.com',
                'order' => 3,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Adidas',
                'slug' => 'adidas',
                'description' => 'Sports clothing and shoes',
                'logo' => 'brands/adidas.png',
                'website' => 'https://www.adidas.com',
                'order' => 4,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Sony',
                'slug' => 'sony',
                'description' => 'Consumer electronics',
                'logo' => 'brands/sony.png',
                'website' => 'https://www.sony.com',
                'order' => 5,
                'featured' => false,
                'status' => 'active'
            ],
            [
                'name' => 'LG',
                'slug' => 'lg',
                'description' => 'Home appliances and electronics',
                'logo' => 'brands/lg.png',
                'website' => 'https://www.lg.com',
                'order' => 6,
                'featured' => false,
                'status' => 'active'
            ],
            [
                'name' => 'Levi\'s',
                'slug' => 'levis',
                'description' => 'Denim jeans and casual wear',
                'logo' => 'brands/levis.png',
                'website' => 'https://www.levi.com',
                'order' => 7,
                'featured' => false,
                'status' => 'active'
            ],
            [
                'name' => 'Puma',
                'slug' => 'puma',
                'description' => 'Sports and lifestyle products',
                'logo' => 'brands/puma.png',
                'website' => 'https://www.puma.com',
                'order' => 8,
                'featured' => false,
                'status' => 'active'
            ]
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(
                ['slug' => $brand['slug']],
                $brand
            );
        }

        $this->command->info('Brands seeded successfully!');
    }
}