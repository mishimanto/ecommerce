<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Latest electronic gadgets and devices',
                'image' => 'categories/electronics.jpg',
                'order' => 1,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Trendy clothing and accessories',
                'image' => 'categories/fashion.jpg',
                'order' => 2,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Home & Kitchen',
                'slug' => 'home-kitchen',
                'description' => 'Home appliances and kitchenware',
                'image' => 'categories/home-kitchen.jpg',
                'order' => 3,
                'featured' => true,
                'status' => 'active'
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports equipment and accessories',
                'image' => 'categories/sports.jpg',
                'order' => 4,
                'featured' => false,
                'status' => 'active'
            ],
            [
                'name' => 'Books',
                'slug' => 'books',
                'description' => 'Books and educational materials',
                'image' => 'categories/books.jpg',
                'order' => 5,
                'featured' => false,
                'status' => 'active'
            ]
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        // Create sub-categories
        $subCategories = [
            [
                'name' => 'Mobile Phones',
                'slug' => 'mobile-phones',
                'parent_id' => Category::where('slug', 'electronics')->first()->id,
                'description' => 'Smartphones and accessories',
                'order' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Laptops',
                'slug' => 'laptops',
                'parent_id' => Category::where('slug', 'electronics')->first()->id,
                'description' => 'Laptops and computers',
                'order' => 2,
                'status' => 'active'
            ],
            [
                'name' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'parent_id' => Category::where('slug', 'fashion')->first()->id,
                'description' => 'Clothing for men',
                'order' => 1,
                'status' => 'active'
            ],
            [
                'name' => "Women's Fashion",
                'slug' => 'womens-fashion',
                'parent_id' => Category::where('slug', 'fashion')->first()->id,
                'description' => 'Clothing for women',
                'order' => 2,
                'status' => 'active'
            ]
        ];

        foreach ($subCategories as $subCategory) {
            Category::firstOrCreate(
                ['slug' => $subCategory['slug']],
                $subCategory
            );
        }

        $this->command->info('Categories and sub-categories seeded successfully!');
    }
}