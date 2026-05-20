<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use App\Models\Denomination;

class BrandSeeder extends Seeder
{
    public function run()
    {
        // Add initial brands
        $lamsa = Brand::create([
            'name' => 'Lamsa',
            'code' => 'LAMSA',
            'description' => 'Educational content for children',
            'is_active' => true,
        ]);
        
        // You'll add more brands as you discover them
        
        $this->command->info('Brands seeded successfully!');
    }
}
