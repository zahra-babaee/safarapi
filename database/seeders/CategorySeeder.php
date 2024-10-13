<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $categories = [
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'historical',
            ]
        ];

        // ثبت اعلانات در دیتابیس
        foreach ($categories as $category) {
            Category::query()->create($category);
        }
    }
}
