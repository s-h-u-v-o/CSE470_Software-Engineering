<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AssetCategory;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\StatusEvent;
use App\Models\CommissioningChecklist;
use App\Models\OperatorAuthorization;
use App\Models\RosterShift;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users (Actors)
        $admin = User::create(['name' => 'Admin User', 'email' => 'admin@biomedops.com', 'password' => bcrypt('password'), 'role' => 'admin']);
        $engineer = User::create(['name' => 'Biomed Engineer', 'email' => 'engineer@biomedops.com', 'password' => bcrypt('password'), 'role' => 'engineer']);
        $operator = User::create(['name' => 'Qualified Operator', 'email' => 'operator@biomedops.com', 'password' => bcrypt('password'), 'role' => 'operator']);

        // 2. Create Categories
        $xray = AssetCategory::create(['name' => 'X-Ray', 'description' => 'X-Ray Machines', 'requires_calibration' => true]);
        $ultrasound = AssetCategory::create(['name' => 'Ultrasound', 'description' => 'Ultrasound Scanners', 'requires_calibration' => false]);

        // 3. Create Assets (Various States)
        // Asset 1: Perfectly healthy X-Ray
        $asset1 = Asset::create([
            'asset_category_id' => $xray->id,
            'name' => 'X-Ray Machine 01',
            'serial_number' => 'SN-XR-001',
            'lifecycle_state' => 'commissioned'
        ]);
        $asset1->commissioningChecklists()->create(['item_name' => 'Electrical Safety', 'is_mandatory' => true, 'is_passed' => true, 'checked_by' => $engineer->id]);

        // Asset 2: Ultrasound with a Critical Ticket (Should be blocked)
        $asset2 = Asset::create([
            'asset_category_id' => $ultrasound->id,
            'name' => 'Ultrasound 02',
            'serial_number' => 'SN-US-002',
            'lifecycle_state' => 'commissioned'
        ]);
        $ticket = Ticket::create([
            'asset_id' => $asset2->id,
            'reported_by' => $operator->id,
            'state' => 'reported',
            'severity' => 'critical',
            'description' => 'Screen is completely blank.',
            'occurred_at' => now()->subHours(2),
            'recorded_at' => now()
        ]);
        
        // Log downtime event for the critical ticket
        StatusEvent::create([
            'asset_id' => $asset2->id,
            'previous_state' => 'technically_ready',
            'next_state' => 'blocked_by_ticket',
            'occurred_at' => now()->subHours(2),
            'recorded_at' => now(),
            'cause_type' => 'unplanned',
            'ticket_id' => $ticket->id,
            'actor_id' => $operator->id,
            'reason' => 'Critical fault reported'
        ]);

        // Asset 3: Installed but not yet commissioned (Should trigger Gap Rule G7 if forced)
        $asset3 = Asset::create([
            'asset_category_id' => $xray->id,
            'name' => 'X-Ray Machine 02',
            'serial_number' => 'SN-XR-003',
            'lifecycle_state' => 'installed'
        ]);

        // 4. Create Staffing Data (Rosters & Authorizations)
        // Authorize Operator for Ultrasound only
        OperatorAuthorization::create([
            'user_id' => $operator->id,
            'asset_category_id' => $ultrasound->id,
            'valid_from' => now()->subMonths(1),
            'valid_to' => now()->addMonths(6),
        ]);

        // Roster the Operator for today
        RosterShift::create([
            'user_id' => $operator->id,
            'start_time' => now()->startOfDay(),
            'end_time' => now()->endOfDay(),
        ]);
        
        // NOTE: The operator is NOT authorized for X-Ray, so Asset 1 will show 
        // as technically ready, but "No qualified operator rostered".

        $this->command->info('BioMedOps seed data generated successfully!');
        $this->generateGroundTruth($asset1, $asset2, $asset3);
    }

    /**
     * Sprint 1 Requirement: Independently recorded ground truth
     */
    private function generateGroundTruth($asset1, $asset2, $asset3)
    {
        $groundTruth = [
            'generated_at' => now()->toIso8601String(),
            'expected_system_states' => [
                [
                    'asset_serial' => $asset1->serial_number,
                    'lifecycle' => 'commissioned',
                    'expected_technical_readiness' => 'technically_ready',
                    'expected_staffing_readiness' => 'unstaffed',
                    'expected_service_availability' => 'unavailable',
                    'reason' => 'No operator authorized for X-Ray category.'
                ],
                [
                    'asset_serial' => $asset2->serial_number,
                    'lifecycle' => 'commissioned',
                    'expected_technical_readiness' => 'blocked_by_ticket',
                    'expected_staffing_readiness' => 'staffed',
                    'expected_service_availability' => 'unavailable',
                    'reason' => 'Critical ticket SN-US-002 is blocking readiness.'
                ],
                [
                    'asset_serial' => $asset3->serial_number,
                    'lifecycle' => 'installed',
                    'expected_technical_readiness' => 'not_ready',
                    'expected_staffing_readiness' => 'unstaffed',
                    'expected_service_availability' => 'unavailable',
                    'reason' => 'Not yet commissioned.'
                ]
            ]
        ];

        // Save to storage/app/ground_truth.json
        file_put_contents(
            storage_path('app/ground_truth.json'), 
            json_encode($groundTruth, JSON_PRETTY_PRINT)
        );
        
        $this->command->info('Ground truth saved to storage/app/ground_truth.json');
    }
}