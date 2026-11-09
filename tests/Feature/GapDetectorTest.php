<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetHold;
use App\Models\CommissioningChecklist;
use App\Models\GapFinding;
use App\Models\SlaPause;
use App\Models\StatusEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Services\GapDetector;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** F25: gap detection. Each enabled code needs a positive and a negative case. */
class GapDetectorTest extends TestCase
{
    use RefreshDatabase;

    private GapDetector $detector;
    private AssetCategory $category;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 09:00:00'));
        config(['gaps.crated_days' => 30, 'gaps.unassigned_hours' => 24]);
        $this->detector = app(GapDetector::class);
        $this->category = AssetCategory::create(['name' => 'X-Ray']);
        $this->user = User::factory()->create();
        $this->user->role = Role::SUPERVISOR;
        $this->user->save();
    }

    // ---- staging -------------------------------------------------------

    public function test_only_core_codes_are_enabled_and_the_rest_report_their_dependency(): void
    {
        $result = $this->detector->detect();

        $this->assertEqualsCanonicalizing(['G1', 'G3', 'G7', 'G8'], $result['enabled']);
        $this->assertEqualsCanonicalizing(['G2', 'G4', 'G5', 'G6', 'G9', 'G10'], array_keys($result['not_enabled']));
        $this->assertSame([], $result['findings']);
    }

    // ---- G1 -------------------------------------------------------------

    public function test_g1_flags_an_asset_crated_longer_than_the_threshold(): void
    {
        $asset = $this->assetCreatedDaysAgo(31, 'received_crated');

        $findings = $this->findingsFor('G1');

        $this->assertCount(1, $findings);
        $this->assertSame($asset->id, $findings[0]->subjectId);
        $this->assertStringContainsString("Asset #{$asset->id}", $findings[0]->message);
    }

    public function test_g1_ignores_recent_crates_and_assets_that_moved_on(): void
    {
        $this->assetCreatedDaysAgo(5, 'received_crated');
        $this->assetCreatedDaysAgo(90, 'installed');

        $this->assertSame([], $this->findingsFor('G1'));
    }

    // ---- G3 -------------------------------------------------------------

    public function test_g3_flags_a_ticket_unassigned_beyond_the_threshold(): void
    {
        $ticket = $this->ticket('reported', null, hoursAgo: 25);

        $findings = $this->findingsFor('G3');

        $this->assertCount(1, $findings);
        $this->assertSame($ticket->id, $findings[0]->subjectId);
    }

    public function test_g3_ignores_recent_assigned_and_closed_tickets(): void
    {
        $this->ticket('reported', null, hoursAgo: 2);
        $this->ticket('assigned', $this->user->id, hoursAgo: 100);
        $this->ticket('closed', null, hoursAgo: 100);

        $this->assertSame([], $this->findingsFor('G3'));
    }

    // ---- G7 -------------------------------------------------------------

    public function test_g7_flags_a_commissioned_asset_with_no_mandatory_items(): void
    {
        $asset = $this->assetCreatedDaysAgo(1, 'commissioned');

        $findings = $this->findingsFor('G7');

        $this->assertCount(1, $findings);
        $this->assertSame($asset->id, $findings[0]->subjectId);
        $this->assertSame('no_mandatory_items', $findings[0]->detail);
    }

    public function test_g7_flags_a_commissioned_asset_with_a_failed_mandatory_item(): void
    {
        $asset = $this->assetCreatedDaysAgo(1, 'commissioned');
        CommissioningChecklist::create(['asset_id' => $asset->id, 'item_name' => 'Electrical safety', 'is_mandatory' => true, 'is_passed' => true]);
        CommissioningChecklist::create(['asset_id' => $asset->id, 'item_name' => 'Site readiness', 'is_mandatory' => true, 'is_passed' => false]);

        $findings = $this->findingsFor('G7');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('Site readiness', $findings[0]->message);
    }

    public function test_g7_is_silent_when_every_mandatory_item_passed_or_the_asset_is_not_commissioned(): void
    {
        $good = $this->assetCreatedDaysAgo(1, 'commissioned');
        CommissioningChecklist::create(['asset_id' => $good->id, 'item_name' => 'Electrical safety', 'is_mandatory' => true, 'is_passed' => true]);
        CommissioningChecklist::create(['asset_id' => $good->id, 'item_name' => 'Optional label', 'is_mandatory' => false, 'is_passed' => false]);
        $this->assetCreatedDaysAgo(1, 'installed'); // not commissioned, no checklist: fine

        $this->assertSame([], $this->findingsFor('G7'));
    }

    // ---- G8 -------------------------------------------------------------

    public function test_g8_flags_a_retired_asset_with_an_open_ticket_or_active_hold(): void
    {
        $asset = $this->assetCreatedDaysAgo(10, 'retired');
        $ticket = $this->ticket('assigned', $this->user->id, hoursAgo: 5, asset: $asset);
        $hold = AssetHold::create([
            'asset_id' => $asset->id, 'type' => 'quarantine', 'reason' => 'x',
            'started_at' => now()->subDay(), 'actor_id' => $this->user->id,
        ]);

        $details = array_map(fn ($f) => $f->detail, $this->findingsFor('G8'));

        $this->assertContains("retired_open_ticket:{$ticket->id}", $details);
        $this->assertContains("retired_active_hold:{$hold->id}", $details);
    }

    public function test_g8_flags_a_work_state_ticket_without_an_assignee(): void
    {
        $ticket = $this->ticket('assigned', null, hoursAgo: 5);

        $findings = $this->findingsFor('G8');

        $this->assertCount(1, $findings);
        $this->assertSame($ticket->id, $findings[0]->subjectId);
        $this->assertSame('work_state_without_assignee', $findings[0]->detail);
    }

    public function test_g8_flags_a_closed_ticket_with_an_unresolved_sla_pause(): void
    {
        $ticket = $this->ticket('closed', $this->user->id, hoursAgo: 5);
        SlaPause::create([
            'ticket_id' => $ticket->id, 'reason_code' => 'parts', 'ticket_state' => 'awaiting_parts',
            'paused_at' => now()->subHours(4), 'paused_by' => $this->user->id, 'paused_by_role' => Role::SUPERVISOR,
        ]);

        $findings = $this->findingsFor('G8');

        $this->assertCount(1, $findings);
        $this->assertSame('closed_with_open_pause', $findings[0]->detail);
    }

    public function test_g8_flags_a_downtime_end_event_without_a_start(): void
    {
        $asset = $this->assetCreatedDaysAgo(10, 'commissioned');
        StatusEvent::create([
            'asset_id' => $asset->id, 'next_state' => 'downtime', 'occurred_at' => now(), 'recorded_at' => now(),
            'cause_type' => 'unplanned', 'downtime_action' => 'end', 'cause_key' => (string) Str::uuid(),
            'actor_id' => $this->user->id,
        ]);

        $findings = array_values(array_filter($this->findingsFor('G8'), fn ($f) => str_starts_with($f->detail, 'downtime:')));

        $this->assertCount(1, $findings);
        $this->assertSame($asset->id, $findings[0]->subjectId);
    }

    public function test_g8_is_silent_for_consistent_records(): void
    {
        $asset = $this->assetCreatedDaysAgo(10, 'commissioned');
        $this->ticket('assigned', $this->user->id, hoursAgo: 5, asset: $asset);
        $this->ticket('closed', $this->user->id, hoursAgo: 5, asset: $asset);

        $this->assertSame([], $this->findingsFor('G8'));
    }

    // ---- scan (persistence) ----------------------------------------------

    public function test_scan_stores_findings_updates_them_and_resolves_them_when_fixed(): void
    {
        $ticket = $this->ticket('reported', null, hoursAgo: 30);

        $this->detector->scan();
        $this->detector->scan();
        $this->assertSame(1, GapFinding::count()); // same fingerprint, not duplicated
        $this->assertNull(GapFinding::first()->resolved_at);

        $ticket->assigned_to = $this->user->id;
        $ticket->state = 'assigned';
        $ticket->save();
        $this->detector->scan();

        $this->assertNotNull(GapFinding::first()->resolved_at);
    }

    public function test_a_resolved_finding_reopens_if_the_problem_returns(): void
    {
        $ticket = $this->ticket('reported', null, hoursAgo: 30);
        $this->detector->scan();
        $ticket->update(['assigned_to' => $this->user->id, 'state' => 'assigned']);
        $this->detector->scan();
        $ticket->update(['assigned_to' => null, 'state' => 'reported']);
        $this->detector->scan();

        $this->assertCount(1, GapFinding::all());
        $this->assertNull(GapFinding::first()->resolved_at);
    }

    // ---- helpers ---------------------------------------------------------

    /** @return list<\App\Services\Gaps\GapFindingData> */
    private function findingsFor(string $code): array
    {
        return array_values(array_filter(
            $this->detector->detect()['findings'],
            fn ($finding) => $finding->code === $code
        ));
    }

    private function assetCreatedDaysAgo(int $days, string $state): Asset
    {
        $asset = Asset::create([
            'asset_category_id' => $this->category->id,
            'name' => 'Asset ' . Str::random(4),
            'serial_number' => 'SN-' . Str::random(8),
            'lifecycle_state' => $state,
        ]);
        $asset->created_at = now()->subDays($days);
        $asset->save();

        return $asset;
    }

    private function ticket(string $state, ?int $assignee, int $hoursAgo, ?Asset $asset = null): Ticket
    {
        $asset ??= $this->assetCreatedDaysAgo(1, 'installed');
        $at = now()->subHours($hoursAgo);

        return Ticket::create([
            'asset_id' => $asset->id,
            'reported_by' => $this->user->id,
            'assigned_to' => $assignee,
            'state' => $state,
            'severity' => 'major',
            'description' => 'Test fault',
            'occurred_at' => $at,
            'recorded_at' => $at,
        ]);
    }
}
