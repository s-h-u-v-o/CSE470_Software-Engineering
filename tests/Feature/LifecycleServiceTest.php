<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Services\LifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_succeeds_and_records_history()
    {
        $user = User::factory()->create(); 
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'received_crated'
        ]);

        $service = new LifecycleService();
        $service->transition($asset, 'installed', $user->id);

        $this->assertEquals('installed', $asset->fresh()->lifecycle_state);
        
        $this->assertDatabaseHas('status_events', [
            'asset_id' => $asset->id,
            'previous_state' => 'received_crated',
            'next_state' => 'installed'
        ]);
    }

    public function test_invalid_transition_is_rejected()
    {
        $user = User::factory()->create();
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'received_crated'
        ]);

        $service = new LifecycleService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invalid lifecycle transition from 'received_crated' to 'commissioned'.");
        
        // Try to skip 'installed' and go straight to 'commissioned'
        $service->transition($asset, 'commissioned', $user->id);
    }
}