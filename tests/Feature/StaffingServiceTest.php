<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Models\OperatorAuthorization;
use App\Models\RosterShift;
use App\Services\StaffingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_is_unstaffed_without_authorized_operator()
    {
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'commissioned'
        ]);

        $service = new StaffingService();
        $this->assertFalse($service->isStaffed($asset));
    }

    public function test_asset_is_staffed_with_valid_authorization_and_shift()
    {
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'commissioned'
        ]);

        $user = User::factory()->create();

        // Authorize user for X-Ray today
        OperatorAuthorization::create([
            'user_id' => $user->id,
            'asset_category_id' => $category->id,
            'valid_from' => now()->subDays(1),
            'valid_to' => now()->addDays(1),
        ]);

        // Create a shift covering right now
        RosterShift::create([
            'user_id' => $user->id,
            'start_time' => now()->subHours(1),
            'end_time' => now()->addHours(1),
        ]);

        $service = new StaffingService();
        $this->assertTrue($service->isStaffed($asset));
    }
    
    public function test_asset_is_unstaffed_if_authorized_but_not_rostered()
    {
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'SN-123',
            'lifecycle_state' => 'commissioned'
        ]);

        $user = User::factory()->create();

        // Authorized, but no shift created
        OperatorAuthorization::create([
            'user_id' => $user->id,
            'asset_category_id' => $category->id,
            'valid_from' => now()->subDays(1),
            'valid_to' => now()->addDays(1),
        ]);

        $service = new StaffingService();
        $this->assertFalse($service->isStaffed($asset));
    }
}