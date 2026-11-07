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
    public function test_asset_cannot_be_commissioned_without_a_checklist()
    {
        $user = User::factory()->create();
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'installed' 
        ]);

        $service = new LifecycleService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Cannot commission asset: No mandatory checklist items found.");
        
        $service->transition($asset, 'commissioned', $user->id);
    }

    public function test_asset_can_be_commissioned_when_all_mandatory_items_pass()
    {
        $user = User::factory()->create();
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'installed' 
        ]);

        // Add a passing checklist item
        $asset->commissioningChecklists()->create([
            'item_name' => 'Electrical Safety',
            'is_mandatory' => true,
            'is_passed' => true,
            'checked_by' => $user->id
        ]);

        $service = new LifecycleService();
        $service->transition($asset, 'commissioned', $user->id);

        $this->assertEquals('commissioned', $asset->fresh()->lifecycle_state);
    }
}