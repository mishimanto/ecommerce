<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $categories = Category::all();
        $brands = Brand::all();

        $products = [
            [
                'name' => 'iPhone 14 Pro Max',
                'slug' => 'iphone-14-pro-max',
                'sku' => 'IPH14PM256',
                'category_id' => $categories->where('slug', 'mobile-phones')->first()->id,
                'brand_id' => $brands->where('slug', 'apple')->first()->id,
                'price' => 1299.99,
                'compare_price' => 1399.99,
                'cost_price' => 1000.00,
                'stock' => 50,
                'description' => 'The latest iPhone with advanced camera system and A16 Bionic chip.',
                'short_description' => 'Pro camera. Pro display. Pro performance.',
                'specifications' => [
                    'Display' => '6.7-inch Super Retina XDR display',
                    'Processor' => 'A16 Bionic chip',
                    'Storage' => '256GB',
                    'Camera' => '48MP Main, 12MP Ultra Wide, 12MP Telephoto',
                    'Battery' => 'Video playback: Up to 29 hours'
                ],
                'attributes' => [
                    'Color' => ['Space Black', 'Silver', 'Gold', 'Deep Purple'],
                    'Storage' => ['128GB', '256GB', '512GB', '1TB']
                ],
                'tags' => 'iphone,apple,smartphone,premium',
                'weight' => 0.24,
                'dimensions' => ['height' => 16.07, 'width' => 7.85, 'depth' => 0.78],
                'is_featured' => true,
                'is_trending' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Samsung Galaxy S23 Ultra',
                'slug' => 'samsung-galaxy-s23-ultra',
                'sku' => 'SGS23U512',
                'category_id' => $categories->where('slug', 'mobile-phones')->first()->id,
                'brand_id' => $brands->where('slug', 'samsung')->first()->id,
                'price' => 1199.99,
                'compare_price' => 1299.99,
                'cost_price' => 900.00,
                'stock' => 75,
                'description' => 'Samsung\'s flagship smartphone with S Pen and 200MP camera.',
                'short_description' => 'Epic moments in epic detail.',
                'specifications' => [
                    'Display' => '6.8-inch Dynamic AMOLED 2X',
                    'Processor' => 'Snapdragon 8 Gen 2',
                    'Storage' => '512GB',
                    'Camera' => '200MP Main, 12MP Ultra Wide, 10MP Telephoto',
                    'Battery' => '5000mAh'
                ],
                'attributes' => [
                    'Color' => ['Phantom Black', 'Cream', 'Green', 'Lavender'],
                    'Storage' => ['256GB', '512GB', '1TB']
                ],
                'tags' => 'samsung,android,smartphone,flagship',
                'weight' => 0.23,
                'dimensions' => ['height' => 16.30, 'width' => 7.79, 'depth' => 0.85],
                'is_featured' => true,
                'is_trending' => true,
                'status' => 'active'
            ],
            [
                'name' => 'MacBook Pro 16-inch',
                'slug' => 'macbook-pro-16-inch',
                'sku' => 'MBP16M1',
                'category_id' => $categories->where('slug', 'laptops')->first()->id,
                'brand_id' => $brands->where('slug', 'apple')->first()->id,
                'price' => 2499.99,
                'compare_price' => 2799.99,
                'cost_price' => 2000.00,
                'stock' => 25,
                'description' => 'Powerful laptop with M1 Pro chip for professionals and creators.',
                'short_description' => 'Supercharged for pros.',
                'specifications' => [
                    'Display' => '16.2-inch Liquid Retina XDR',
                    'Processor' => 'Apple M1 Pro',
                    'RAM' => '16GB',
                    'Storage' => '512GB SSD',
                    'Battery' => 'Up to 21 hours'
                ],
                'attributes' => [
                    'Color' => ['Space Gray', 'Silver'],
                    'Processor' => ['M1 Pro', 'M1 Max'],
                    'RAM' => ['16GB', '32GB', '64GB'],
                    'Storage' => ['512GB', '1TB', '2TB', '4TB', '8TB']
                ],
                'tags' => 'macbook,laptop,apple,pro',
                'weight' => 2.1,
                'dimensions' => ['height' => 1.68, 'width' => 35.57, 'depth' => 24.81],
                'is_featured' => true,
                'is_trending' => false,
                'status' => 'active'
            ],
            [
                'name' => 'Nike Air Max 270',
                'slug' => 'nike-air-max-270',
                'sku' => 'NAM270BK',
                'category_id' => $categories->where('slug', 'sports')->first()->id,
                'brand_id' => $brands->where('slug', 'nike')->first()->id,
                'price' => 149.99,
                'compare_price' => 179.99,
                'cost_price' => 80.00,
                'stock' => 100,
                'description' => 'Comfortable lifestyle shoes with Max Air cushioning.',
                'short_description' => 'All-day comfort. Iconic style.',
                'specifications' => [
                    'Type' => 'Lifestyle Sneakers',
                    'Closure' => 'Lace-up',
                    'Material' => 'Mesh and synthetic',
                    'Cushioning' => 'Max Air unit in heel',
                    'Weight' => 'Approx. 300g per shoe'
                ],
                'attributes' => [
                    'Color' => ['Black/White', 'White/Black', 'Blue/White', 'Red/White'],
                    'Size' => ['7', '8', '9', '10', '11', '12', '13']
                ],
                'tags' => 'nike,shoes,sneakers,casual',
                'weight' => 0.6,
                'dimensions' => ['height' => 12, 'width' => 8, 'depth' => 5],
                'is_featured' => false,
                'is_trending' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'slug' => 'sony-wh-1000xm5',
                'sku' => 'SONYXM5BK',
                'category_id' => $categories->where('slug', 'electronics')->first()->id,
                'brand_id' => $brands->where('slug', 'sony')->first()->id,
                'price' => 399.99,
                'compare_price' => 449.99,
                'cost_price' => 250.00,
                'stock' => 40,
                'description' => 'Premium noise-canceling headphones with exceptional sound quality.',
                'short_description' => 'Industry-leading noise cancellation.',
                'specifications' => [
                    'Type' => 'Over-ear wireless headphones',
                    'Noise Cancellation' => 'Yes, adaptive',
                    'Battery Life' => 'Up to 30 hours',
                    'Connectivity' => 'Bluetooth 5.2',
                    'Weight' => '250g'
                ],
                'attributes' => [
                    'Color' => ['Black', 'Silver', 'Blue'],
                    'Connectivity' => ['Wireless', 'Wired']
                ],
                'tags' => 'sony,headphones,audio,wireless',
                'weight' => 0.25,
                'dimensions' => ['height' => 8.1, 'width' => 7.2, 'depth' => 3.1],
                'is_featured' => true,
                'is_trending' => false,
                'status' => 'active'
            ]
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }

        $this->command->info('Products seeded successfully!');
    }
}