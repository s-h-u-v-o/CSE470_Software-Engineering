<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\StatusEvent;
use App\Models\User;
use App\Services\DowntimeLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DowntimeLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Asset $asset;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00:00'));
        $this->actor = User::factory()->create();
        $category = AssetCategory::create(['name' => 'Test category']);
        $this->asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'Test asset',
            'serial_number' => 'DOWNTIME-001',
            'lifecycle_state' => 'commissioned',
        ]);
        $this->event('commissioned', '2026-11-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unplanned_overlap_with_planned_downtime_is_counted_once_as_unplanned(): void
    {
        $this->interval('planned', 'planned-window', '2026-11-08 09:00:00', '2026-11-08 10:00:00');
        $this->interval('unplanned', 'repair-window', '2026-11-08 09:30:00', '2026-11-08 09:45:00');

        $ledger = app(DowntimeLedger::class)->forAsset($this->asset);

        $this->assertSame(45, $ledger['planned_minutes']);
        $this->assertSame(15, $ledger['unplanned_minutes']);
        $this->assertSame(60, $ledger['total_minutes']);
        $this->assertCount(3, $ledger['intervals']);
    }

    public function test_overlapping_ticket_intervals_are_merged_instead_of_summed(): void
    {
        $this->interval('unplanned', 'ticket-one', '2026-11-08 09:00:00', '2026-11-08 10:00:00');
        $this->interval('unplanned', 'ticket-two', '2026-11-08 09:30:00', '2026-11-08 10:30:00');

        $ledger = app(DowntimeLedger::class)->forAsset($this->asset);

        $this->assertSame(90, $ledger['unplanned_minutes']);
        $this->assertSame(90, $ledger['total_minutes']);
        $this->assertCount(1, $ledger['intervals']);
    }

    public function test_downtime_before_commissioning_is_clipped_from_operational_ledger(): void
    {
        $this->interval('unplanned', 'pre-service', '2026-11-08 08:30:00', '2026-11-08 09:30:00');

        $ledger = app(DowntimeLedger::class)->forAsset($this->asset);

        $this->assertSame(30, $ledger['unplanned_minutes']);
        $this->assertSame(30, $ledger['total_minutes']);
        $this->assertSame('2026-11-08 09:00:00', $ledger['intervals'][0]['start']->format('Y-m-d H:i:s'));
    }

    private function interval(string $cause, string $key, string $start, string $end): void
    {
        $this->event('downtime_started', $start, 'start', $cause, $key);
        $this->event('downtime_ended', $end, 'end', $cause, $key);
    }

    private function event(string $state, string $at, ?string $action = null, ?string $cause = null, ?string $key = null): void
    {
        StatusEvent::create([
            'asset_id' => $this->asset->id,
            'previous_state' => null,
            'next_state' => $state,
            'occurred_at' => Carbon::parse($at),
            'recorded_at' => Carbon::parse($at),
            'cause_type' => $cause,
            'downtime_action' => $action,
            'cause_key' => $key,
            'actor_id' => $this->actor->id,
        ]);
    }
}
