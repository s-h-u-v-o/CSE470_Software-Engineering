<?php

namespace Tests\Feature;

use App\Exceptions\TicketWorkflowException;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\ReadinessService;
use App\Services\TicketService;
use App\Support\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** F16: ticket workflow with severity and blocking rules. */
class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $tickets;
    private Asset $asset;
    private User $reporter;
    private User $engineer;
    private User $otherEngineer;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-08 08:00:00'));
        $this->tickets = app(TicketService::class);

        $this->reporter = $this->user(Role::DEPARTMENT_USER);
        $this->engineer = $this->user(Role::ENGINEER);
        $this->otherEngineer = $this->user(Role::ENGINEER);
        $this->supervisor = $this->user(Role::SUPERVISOR);

        $category = AssetCategory::create(['name' => 'Ultrasound']);
        $this->asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'Ultrasound 01',
            'serial_number' => 'US-TKT-001',
            'lifecycle_state' => 'commissioned',
        ]);
        $this->asset->commissioningChecklists()->create([
            'item_name' => 'Electrical safety', 'is_mandatory' => true, 'is_passed' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- Happy path and history ------------------------------------------

    public function test_full_workflow_records_every_transition_in_order(): void
    {
        $t = $this->tickets->report($this->asset, $this->reporter, 'Probe image artefacts');
        $this->tickets->triage($t, $this->engineer, 'major');
        $this->tickets->assign($t, $this->engineer, $this->engineer);
        $this->tickets->awaitParts($t, $this->engineer, 'Replacement probe ordered');
        $this->tickets->resumeWork($t, $this->engineer, 'Probe received');
        $this->tickets->markRepaired($t, $this->engineer);
        $this->tickets->verify($t, $this->supervisor, true, 'Image quality OK');
        $this->tickets->close($t, $this->supervisor);

        $this->assertSame('closed', $t->fresh()->state);
        $this->assertSame($this->engineer->id, $t->fresh()->assigned_to);
        $this->assertSame(
            ['reported', 'triaged', 'assigned', 'awaiting_parts', 'work_resumed', 'repaired', 'verified', 'closed'],
            $t->events()->pluck('event_type')->all()
        );
        $this->assertSame(
            [null, 'reported', 'triaged', 'assigned', 'awaiting_parts', 'assigned', 'repaired', 'verified'],
            $t->events()->pluck('from_state')->all()
        );
        $triage = $t->events()->where('event_type', 'triaged')->first();
        $this->assertSame([null, 'major', Role::ENGINEER], [$triage->from_severity, $triage->to_severity, $triage->actor_role]);
    }

    // --- Exhaustive transition guard -------------------------------------

    public static function stateActionMatrix(): array
    {
        $states = ['reported', 'triaged', 'assigned', 'awaiting_parts', 'awaiting_vendor', 'repaired', 'verified', 'closed'];
        $allowedFrom = [
            'triage' => ['reported'],
            'assign' => ['triaged'],
            'await_parts' => ['assigned'],
            'await_vendor' => ['assigned'],
            'resume_work' => ['awaiting_parts', 'awaiting_vendor'],
            'repaired' => ['assigned'],
            'verify_pass' => ['repaired'],
            'verify_fail' => ['repaired'],
            'close' => ['verified'],
        ];
        $cases = [];
        foreach ($states as $state) {
            foreach ($allowedFrom as $action => $from) {
                $cases["{$action} from {$state}"] = [$state, $action, in_array($state, $from, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('stateActionMatrix')]
    public function test_every_action_is_accepted_only_from_its_valid_states(string $state, string $action, bool $allowed): void
    {
        $t = $this->ticketIn($state, $state === 'reported' || $state === 'triaged' ? null : $this->engineer->id);
        $eventsBefore = TicketEvent::count();

        try {
            match ($action) {
                'triage' => $this->tickets->triage($t, $this->supervisor, 'major'),
                'assign' => $this->tickets->assign($t, $this->supervisor, $this->engineer),
                'await_parts' => $this->tickets->awaitParts($t, $this->supervisor, 'parts'),
                'await_vendor' => $this->tickets->awaitVendor($t, $this->supervisor, 'vendor'),
                'resume_work' => $this->tickets->resumeWork($t, $this->supervisor),
                'repaired' => $this->tickets->markRepaired($t, $this->supervisor),
                'verify_pass' => $this->tickets->verify($t, $this->supervisor, true),
                'verify_fail' => $this->tickets->verify($t, $this->supervisor, false, 'Still faulty'),
                'close' => $this->tickets->close($t, $this->supervisor),
            };
            $this->assertTrue($allowed, "{$action} from {$state} should have been rejected");
            $this->assertSame($eventsBefore + 1, TicketEvent::count());
        } catch (TicketWorkflowException $e) {
            $this->assertFalse($allowed, "{$action} from {$state} was rejected: {$e->getMessage()}");
            $this->assertSame($state, $t->fresh()->state, 'Rejected action must not change state');
            $this->assertSame($eventsBefore, TicketEvent::count(), 'Rejected action must not write events');
        }
    }

    // --- Verification and closure ----------------------------------------

    public function test_failed_verification_returns_to_assigned_and_keeps_history(): void
    {
        $t = $this->ticketIn('repaired', $this->engineer->id);

        $this->tickets->verify($t, $this->supervisor, false, 'Leakage current out of range');

        $this->assertSame('assigned', $t->fresh()->state);
        $event = $t->events()->latest('id')->first();
        $this->assertSame(['verification_failed', 'repaired', 'assigned'], [$event->event_type, $event->from_state, $event->to_state]);
        $this->assertSame('Leakage current out of range', $event->reason);
    }

    public function test_failed_verification_requires_a_reason(): void
    {
        $t = $this->ticketIn('repaired', $this->engineer->id);
        $this->expectException(TicketWorkflowException::class);
        $this->tickets->verify($t, $this->supervisor, false);
    }

    public function test_only_a_supervisor_can_verify_or_close(): void
    {
        $t = $this->ticketIn('repaired', $this->engineer->id);
        $this->assertThrows(fn () => $this->tickets->verify($t, $this->engineer, true), AuthorizationException::class);

        $v = $this->ticketIn('verified', $this->engineer->id);
        $this->assertThrows(fn () => $this->tickets->close($v, $this->engineer), AuthorizationException::class);
    }

    public function test_closed_ticket_is_immutable_including_severity(): void
    {
        $t = $this->ticketIn('closed', $this->engineer->id, 'minor');
        $this->expectException(TicketWorkflowException::class);
        $this->expectExceptionMessage('closed and immutable');
        $this->tickets->reclassify($t, $this->supervisor, 'critical', 'Late finding');
    }

    public function test_ticket_events_are_append_only(): void
    {
        $t = $this->tickets->report($this->asset, $this->reporter, 'No power');
        $event = $t->events()->first();

        $this->assertThrows(fn () => $event->update(['reason' => 'edited']), LogicException::class);
        $this->assertThrows(fn () => $event->delete(), LogicException::class);
    }

    // --- Severity and reclassification -----------------------------------

    public function test_triage_requires_a_valid_severity(): void
    {
        $t = $this->tickets->report($this->asset, $this->reporter, 'Noise');
        $this->expectException(TicketWorkflowException::class);
        $this->tickets->triage($t, $this->engineer, 'urgent');
    }

    public function test_supervisor_reclassification_records_old_new_actor_reason_and_time(): void
    {
        $t = $this->ticketIn('assigned', $this->engineer->id, 'minor');
        Carbon::setTestNow('2026-11-08 09:15:00');

        $this->tickets->reclassify($t, $this->supervisor, 'critical', 'Fault affects patient-facing output');

        $this->assertSame('critical', $t->fresh()->severity);
        $this->assertSame('assigned', $t->fresh()->state);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $t->id,
            'event_type' => 'severity_reclassified',
            'from_severity' => 'minor',
            'to_severity' => 'critical',
            'actor_id' => $this->supervisor->id,
            'actor_role' => Role::SUPERVISOR,
            'reason' => 'Fault affects patient-facing output',
        ]);
        $this->assertSame('2026-11-08 09:15:00', $t->events()->latest('id')->first()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_reclassification_is_rejected_for_non_supervisors(): void
    {
        $t = $this->ticketIn('assigned', $this->engineer->id, 'minor');
        $this->expectException(AuthorizationException::class);
        $this->tickets->reclassify($t, $this->engineer, 'critical', 'I think so');
    }

    public function test_reclassification_requires_reason_triage_and_a_change(): void
    {
        $assigned = $this->ticketIn('assigned', $this->engineer->id, 'minor');
        $reported = $this->ticketIn('reported', null, 'minor');

        $this->assertThrows(fn () => $this->tickets->reclassify($assigned, $this->supervisor, 'major', '  '), TicketWorkflowException::class);
        $this->assertThrows(fn () => $this->tickets->reclassify($assigned, $this->supervisor, 'minor', 'same'), TicketWorkflowException::class);
        $this->assertThrows(fn () => $this->tickets->reclassify($reported, $this->supervisor, 'major', 'early'), TicketWorkflowException::class);
        $this->assertSame(0, TicketEvent::where('event_type', 'severity_reclassified')->count());
    }

    // --- Roles and assignment --------------------------------------------

    public function test_role_rules_are_enforced_by_the_service(): void
    {
        $auditor = $this->user(Role::AUDITOR);
        $t = $this->tickets->report($this->asset, $this->reporter, 'Display flicker');

        $this->assertThrows(fn () => $this->tickets->report($this->asset, $auditor, 'x'), AuthorizationException::class);
        $this->assertThrows(fn () => $this->tickets->triage($t, $this->reporter, 'minor'), AuthorizationException::class);

        $this->tickets->triage($t, $this->engineer, 'minor');
        $this->assertThrows(fn () => $this->tickets->assign($t, $this->supervisor, $this->reporter), TicketWorkflowException::class);
        $this->assertThrows(fn () => $this->tickets->assign($t, $this->engineer, $this->otherEngineer), TicketWorkflowException::class);

        $this->tickets->assign($t, $this->engineer, $this->engineer); // self-claim
        $this->assertThrows(fn () => $this->tickets->markRepaired($t, $this->otherEngineer), TicketWorkflowException::class);
        $this->assertThrows(fn () => $this->tickets->markRepaired($t, $this->reporter), AuthorizationException::class);

        $this->tickets->markRepaired($t, $this->engineer);
        $this->assertSame('repaired', $t->fresh()->state);
    }

    public function test_waiting_states_require_a_reason(): void
    {
        $t = $this->ticketIn('assigned', $this->engineer->id);
        $this->expectException(TicketWorkflowException::class);
        $this->tickets->awaitVendor($t, $this->engineer, '');
    }

    public function test_cannot_report_on_retired_asset(): void
    {
        $this->asset->update(['lifecycle_state' => 'retired']);
        $this->expectException(TicketWorkflowException::class);
        $this->tickets->report($this->asset, $this->reporter, 'Fault');
    }

    // --- Blocking rules ---------------------------------------------------

    public function test_critical_ticket_blocks_readiness_immediately_before_triage_or_assignment(): void
    {
        $readiness = app(ReadinessService::class);
        $this->assertSame('technically_ready', $readiness->for($this->asset->fresh())['state']);

        $t = $this->tickets->report($this->asset, $this->reporter, 'Smoke from power supply', 'critical');

        $this->assertTrue($this->tickets->blocksReadiness($t));
        $this->assertSame('blocked_by_ticket', $readiness->for($this->asset->fresh())['state']);
    }

    public function test_minor_ticket_does_not_block_and_open_ticket_alone_is_not_unavailability(): void
    {
        $t = $this->tickets->report($this->asset, $this->reporter, 'Cosmetic scratch', 'minor');

        $this->assertFalse($this->tickets->blocksReadiness($t));
        $this->assertSame('technically_ready', app(ReadinessService::class)->for($this->asset->fresh())['state']);
    }

    public function test_major_ticket_blocks_only_when_facility_rule_enables_it(): void
    {
        $t = $this->ticketIn('assigned', $this->engineer->id, 'major');

        config(['readiness.major_blocks' => false]);
        $this->assertFalse($this->tickets->blocksReadiness($t));
        $this->assertSame('technically_ready', app(ReadinessService::class)->for($this->asset->fresh())['state']);

        config(['readiness.major_blocks' => true]);
        $this->assertTrue($this->tickets->blocksReadiness($t));
        $this->assertSame('blocked_by_ticket', app(ReadinessService::class)->for($this->asset->fresh())['state']);
    }

    public function test_verified_and_closed_critical_tickets_no_longer_block(): void
    {
        foreach (['verified', 'closed'] as $state) {
            $this->assertFalse($this->tickets->blocksReadiness($this->ticketIn($state, $this->engineer->id, 'critical')));
        }
        $this->assertSame('technically_ready', app(ReadinessService::class)->for($this->asset->fresh())['state']);
    }

    public function test_reclassifying_critical_to_minor_releases_the_block(): void
    {
        $t = $this->ticketIn('assigned', $this->engineer->id, 'critical');
        $this->assertSame('blocked_by_ticket', app(ReadinessService::class)->for($this->asset->fresh())['state']);

        $this->tickets->reclassify($t, $this->supervisor, 'minor', 'Fault confined to optional accessory');

        $this->assertFalse($this->tickets->blocksReadiness($t));
        $this->assertSame('technically_ready', app(ReadinessService::class)->for($this->asset->fresh())['state']);
    }

    // ---------------------------------------------------------------------

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function ticketIn(string $state, ?int $assignee, ?string $severity = 'major'): Ticket
    {
        return Ticket::create([
            'asset_id' => $this->asset->id,
            'reported_by' => $this->reporter->id,
            'assigned_to' => $assignee,
            'state' => $state,
            'severity' => $state === 'reported' ? null : $severity,
            'description' => "Fixture ticket in {$state}",
            'occurred_at' => now(),
            'recorded_at' => now(),
        ]);
    }
}
