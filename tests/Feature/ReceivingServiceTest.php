<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Models\WarrantyTerm;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_can_be_received_with_warranty_terms()
    {
        $user = User::factory()->create();
        $category = AssetCategory::create(['name' => 'X-Ray']);

        $service = new ReceivingService();
        
        $asset = $service->receiveAsset([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray Machine 01',
            'serial_number' => 'SN-UNIQUE-001',
            'provider_name' => 'GE Healthcare',
            'term_type' => 'warranty',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ], $user->id);

        // Assert Asset was created in the correct state
        $this->assertEquals('received_crated', $asset->fresh()->lifecycle_state);
        
        // Assert Receiving record was created
        $this->assertDatabaseHas('receivings', [
            'asset_id' => $asset->id,
            'received_by' => $user->id,
        ]);

        // Assert Warranty Term was created
        $this->assertDatabaseHas('warranty_terms', [
            'asset_id' => $asset->id,
            'provider_name' => 'GE Healthcare',
            'term_type' => 'warranty'
        ]);

        // Assert initial status event was logged
        $this->assertDatabaseHas('status_events', [
            'asset_id' => $asset->id,
            'next_state' => 'received_crated',
        ]);
    }

    public function test_duplicate_serial_number_is_rejected()
    {
        $user = User::factory()->create();
        $category = AssetCategory::create(['name' => 'X-Ray']);

        // Create an existing asset
        Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'Existing Machine',
            'serial_number' => 'SN-EXISTING-123',
            'lifecycle_state' => 'installed'
        ]);

        $service = new ReceivingService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Duplicate serial number: SN-EXISTING-123 already exists in the system.");

        // Try to receive a new asset with the same serial number
        $service->receiveAsset([
            'asset_category_id' => $category->id,
            'name' => 'New Machine',
            'serial_number' => 'SN-EXISTING-123',
            'provider_name' => 'Siemens',
            'term_type' => 'amc',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ], $user->id);
    }
}