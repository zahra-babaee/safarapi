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
                'attraction_type' => 'tourism',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'historical',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'fun',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'celebrities',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'cultural',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'food',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'tourism news',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'religious',
            ],
            [
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'attraction_type' => 'natural',
            ]
        ];

        // ثبت اعلانات در دیتابیس
        foreach ($categories as $category) {
            Category::query()->create($category);
        }
    }
}
