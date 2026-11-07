<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AssetCategory;
use App\Models\Asset;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a default User (so we have an actor)
        $user = User::firstOrCreate(
            ['email' => 'admin@biomedops.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );

        // 2. Create two categories
        $xray = AssetCategory::firstOrCreate(['name' => 'X-Ray'], ['description' => 'X-Ray Machines']);
        $ultrasound = AssetCategory::firstOrCreate(['name' => 'Ultrasound'], ['description' => 'Ultrasound Scanners']);

        // 3. Create some dummy assets to test the lifecycle
        Asset::create([
            'asset_category_id' => $xray->id,
            'name' => 'X-Ray Machine 01',
            'serial_number' => 'SN-XR-001',
            'lifecycle_state' => 'received_crated'
        ]);

        Asset::create([
            'asset_category_id' => $ultrasound->id,
            'name' => 'Ultrasound 02',
            'serial_number' => 'SN-US-002',
            'lifecycle_state' => 'installed' // This one is ready to be commissioned
        ]);
        
        $this->command->info('Dummy assets created successfully!');
    }
}