<?php

namespace Tests\Feature;

use App\Exceptions\TicketWorkflowException;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\SlaPause;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaEngine;
use App\Services\TicketService;
use App\Support\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F18: SLA clock with authorized pauses.
 *
 * Test policies (hand-calculation basis):
 *   critical: response 30, resolution 240, pauses allowed
 *   major:    response 60, resolution 480, pauses allowed
 *   minor:    response 120, resolution 1440, pauses NOT allowed
 *
 * All times are on 2026-01-01 (UTC). Rounding rule: floor once, after
 * subtracting merged pause seconds. Breach = elapsed > target.
 */
class SlaEngineTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-01-01 ';

    private TicketService $tickets;
    private SlaEngine $sla;
    private Asset $asset;
    private User $reporter;
    private User $engineer;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tickets = app(TicketService::class);
        $this->sla = app(SlaEngine::class);

        foreach ([['critical', 30, 240, true], ['major', 60, 480, true], ['minor', 120, 1440, false]] as [$sev, $r, $res, $pause]) {
            SlaPolicy::updateOrCreate(['severity' => $sev], [
                'response_minutes' => $r, 'resolution_minutes' => $res, 'pause_allowed' => $pause,
            ]);
        }

        $this->reporter = $this->user(Role::DEPARTMENT_USER);
        $this->engineer = $this->user(Role::ENGINEER);
        $this->supervisor = $this->user(Role::SUPERVISOR);
        $category = AssetCategory::create(['name' => 'X-Ray']);
        $this->asset = Asset::create([
            'asset_category_id' => $category->id,
            'name' => 'X-Ray 01',
            'serial_number' => 'XR-SLA-001',
            'lifecycle_state' => 'commissioned',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Hand-calculated cases.
     * [provisional severity, reported at, steps, evaluate at,
     *  [response elapsed, response breached, resolution elapsed, resolution breached], ?resolution status]
     *
     * Common prefix used below: triage 08:05, assign 08:10 (response = 10).
     */
    public static function handCalculatedCases(): array
    {
        $t = [['08:05', 'triage'], ['08:10', 'assign']];

        return [
            // --- Response boundaries (critical, target 30) ---
            '01 response exactly at target' => ['critical', '08:00', [['08:05', 'triage'], ['08:30', 'assign']], '08:30', [30, false, 30, false]],
            '02 response one minute over' => ['critical', '08:00', [['08:05', 'triage'], ['08:31', 'assign']], '08:31', [31, true, 31, false]],
            '03 response one minute under' => ['critical', '08:00', [['08:05', 'triage'], ['08:29', 'assign']], '08:29', [29, false, 29, false]],
            '04 response running past target' => ['critical', '08:00', [['08:05', 'triage']], '08:45', [45, true, 45, false], 'running'],
            '05 response running at target' => ['critical', '08:00', [], '08:30', [30, false, 30, false]],

            // --- Floor rounding ---
            '06 floor drops 59 seconds' => ['critical', '08:00', [], '08:30:59', [30, false, 30, false]],
            '07 floor rolls at whole minute' => ['critical', '08:00', [], '08:31:00', [31, true, 31, false]],
            '08 floor with second offset report' => ['critical', '08:00:40', [['08:05', 'triage'], ['08:30:39', 'assign']], '08:30:39', [29, false, 29, false]],

            // --- Resolution boundaries (critical, target 240) ---
            '09 resolution exactly at target' => ['critical', '08:00', [...$t, ['11:00', 'repaired'], ['12:00', 'verify']], '13:00', [10, false, 240, false], 'met'],
            '10 resolution one minute over' => ['critical', '08:00', [...$t, ['11:00', 'repaired'], ['12:01', 'verify']], '13:00', [10, false, 241, true], 'breached'],

            // --- Single pause (ended automatically when work resumes) ---
            '11 single pause meets target' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['09:20', 'work'], ['12:00', 'repaired'], ['13:00', 'verify']], '13:00', [10, false, 240, false], 'met'],
            '12 single pause one minute over' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['09:20', 'work'], ['12:00', 'repaired'], ['13:01', 'verify']], '13:01', [10, false, 241, true]],

            // --- Repeated pauses ---
            '13 repeated pauses meet target' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['08:50', 'resume'], ['09:00', 'pause'], ['09:30', 'resume'], ['09:40', 'work'], ['12:00', 'repaired'], ['13:00', 'verify']], '13:00', [10, false, 240, false]],
            '14 repeated pauses one over' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['08:50', 'resume'], ['09:00', 'pause'], ['09:30', 'resume'], ['09:40', 'work'], ['12:00', 'repaired'], ['13:01', 'verify']], '13:01', [10, false, 241, true]],
            '15 waiting after resume still counts' => ['critical', '08:00', [...$t, ['08:20', 'vendor'], ['08:20', 'pause'], ['08:50', 'resume'], ['10:00', 'work'], ['12:31', 'verify_via_repair']], '12:31', [10, false, 241, true]],

            // --- Active pause / breach while paused ---
            '16 evaluated during active pause' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['09:00', 'pause']], '11:00', [10, false, 60, false], 'paused'],
            '17 breach persists while paused' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['12:30', 'pause']], '14:00', [10, false, 270, true], 'breached'],
            '18 no new breach accrues during pause' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['11:00', 'pause']], '18:00', [10, false, 180, false], 'paused'],

            // --- Zero-length pause ---
            '19 zero-length pause subtracts nothing' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['09:00', 'pause'], ['09:00', 'resume'], ['09:10', 'work'], ['11:00', 'repaired'], ['12:00', 'verify']], '12:00', [10, false, 240, false]],
            '20 zero-length pause one over' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['09:00', 'pause'], ['09:00', 'resume'], ['09:10', 'work'], ['11:00', 'repaired'], ['12:01', 'verify']], '12:01', [10, false, 241, true]],

            // --- Pause then reopen (failed verification) ---
            '21 pause then reopen meets target' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['08:50', 'work'], ['10:00', 'repaired'], ['10:30', 'fail'], ['11:30', 'repaired'], ['12:30', 'verify']], '12:30', [10, false, 240, false]],
            '22 pause then reopen one over' => ['critical', '08:00', [...$t, ['08:20', 'parts'], ['08:20', 'pause'], ['08:50', 'work'], ['10:00', 'repaired'], ['10:30', 'fail'], ['11:30', 'repaired'], ['12:31', 'verify']], '12:31', [10, false, 241, true]],
            '23 repaired does not stop the clock' => ['critical', '08:00', [...$t, ['10:00', 'repaired'], ['10:30', 'fail'], ['11:00', 'repaired'], ['12:10', 'verify']], '12:10', [10, false, 250, true]],

            // --- Pause rounding: floor applied once to the net ---
            '24 net seconds floor meets target' => ['critical', '08:00', [...$t, ['08:10:30', 'parts'], ['08:10:30', 'pause'], ['08:20:00', 'work'], ['11:00', 'repaired'], ['12:09:30', 'verify']], '12:09:30', [10, false, 240, false]],
            '25 net seconds floor one over' => ['critical', '08:00', [...$t, ['08:10:30', 'parts'], ['08:10:30', 'pause'], ['08:20:00', 'work'], ['11:00', 'repaired'], ['12:10:30', 'verify']], '12:10:30', [10, false, 241, true]],

            // --- Other severities ---
            '26 major response at target' => ['major', '08:00', [['08:05', 'triage'], ['09:00', 'assign']], '09:00', [60, false, 60, false]],
            '27 major response one over' => ['major', '08:00', [['08:05', 'triage'], ['09:01', 'assign']], '09:01', [61, true, 61, false]],
            '28 minor response at target' => ['minor', '08:00', [['08:05', 'triage'], ['10:00', 'assign']], '10:00', [120, false, 120, false]],

            // --- Reclassification uses the current severity's targets ---
            '29 critical to major relaxes target' => ['critical', '08:00', [['08:05', 'triage'], ['08:20', 'reclassify:major'], ['08:45', 'assign']], '08:45', [45, false, 45, false]],
            '30 major to critical tightens target' => ['major', '08:00', [['08:05', 'triage'], ['08:20', 'reclassify:critical'], ['08:40', 'assign']], '08:40', [40, true, 40, false]],

            // --- No severity yet / historical evaluation ---
            '31 untriaged has no target' => [null, '08:00', [], '08:45', [45, false, 45, false], 'no_policy'],
            '32 historical evaluation before stop' => ['critical', '08:00', [...$t, ['11:00', 'repaired'], ['12:00', 'verify']], '09:00', [10, false, 60, false], 'running'],
        ];
    }

    #[DataProvider('handCalculatedCases')]
    public function test_hand_calculated_sla_case(?string $severity, string $reportedAt, array $steps, string $evalAt, array $expected, ?string $resolutionStatus = null): void
    {
        $ticket = $this->play($severity, $reportedAt, $steps);

        $result = $this->sla->evaluate($ticket->fresh(), $this->at($evalAt));

        $this->assertSame(
            $expected,
            [
                $result['response']['elapsed_minutes'], $result['response']['breached'],
                $result['resolution']['elapsed_minutes'], $result['resolution']['breached'],
            ]
        );
        if ($resolutionStatus) {
            $this->assertSame($resolutionStatus, $result['resolution']['status']);
        }
    }

    // --- Pause authorization and policy rules ----------------------------

    public function test_pause_rejected_where_policy_forbids(): void
    {
        $ticket = $this->play('minor', '08:00', [['08:05', 'triage'], ['08:10', 'assign'], ['08:20', 'parts']]);

        $this->expectException(TicketWorkflowException::class);
        $this->expectExceptionMessage('does not permit pauses');
        $this->sla->pause($ticket, $this->supervisor, 'parts_on_order');
    }

    public function test_pause_rejected_outside_waiting_states(): void
    {
        $ticket = $this->play('critical', '08:00', [['08:05', 'triage'], ['08:10', 'assign']]);

        $this->expectException(TicketWorkflowException::class);
        $this->expectExceptionMessage('only be paused while awaiting');
        $this->sla->pause($ticket, $this->supervisor, 'parts_on_order');
    }

    public function test_only_supervisor_can_pause_or_resume(): void
    {
        $ticket = $this->play('critical', '08:00', [['08:05', 'triage'], ['08:10', 'assign'], ['08:20', 'parts']]);

        $this->assertThrows(fn () => $this->sla->pause($ticket, $this->engineer, 'parts_on_order'), AuthorizationException::class);
        $this->sla->pause($ticket, $this->supervisor, 'parts_on_order');
        $this->assertThrows(fn () => $this->sla->resume($ticket, $this->engineer), AuthorizationException::class);
        $this->assertSame(1, SlaPause::whereNull('resumed_at')->count());
    }

    public function test_double_pause_unknown_reason_and_resume_without_pause_are_rejected(): void
    {
        $ticket = $this->play('critical', '08:00', [['08:05', 'triage'], ['08:10', 'assign'], ['08:20', 'parts']]);

        $this->assertThrows(fn () => $this->sla->resume($ticket, $this->supervisor), TicketWorkflowException::class);
        $this->assertThrows(fn () => $this->sla->pause($ticket, $this->supervisor, 'coffee_break'), TicketWorkflowException::class);
        $this->sla->pause($ticket, $this->supervisor, 'parts_on_order');
        $this->assertThrows(fn () => $this->sla->pause($ticket, $this->supervisor, 'vendor_visit_pending'), TicketWorkflowException::class);
        $this->assertSame(1, SlaPause::count());
    }

    public function test_pause_and_resume_are_audited_with_reason_role_and_time(): void
    {
        $ticket = $this->play('critical', '08:00', [['08:05', 'triage'], ['08:10', 'assign'], ['08:20', 'vendor']]);
        Carbon::setTestNow($this->at('08:25'));
        $this->sla->pause($ticket, $this->supervisor, 'vendor_visit_pending', 'Vendor ETA tomorrow');
        Carbon::setTestNow($this->at('09:25'));
        $this->sla->resume($ticket, $this->supervisor, 'Vendor arrived early');

        $pause = SlaPause::sole();
        $this->assertSame('vendor_visit_pending', $pause->reason_code);
        $this->assertSame('awaiting_vendor', $pause->ticket_state);
        $this->assertSame([Role::SUPERVISOR, Role::SUPERVISOR], [$pause->paused_by_role, $pause->resumed_by_role]);
        $this->assertSame(['08:25', '09:25'], [$pause->paused_at->format('H:i'), $pause->resumed_at->format('H:i')]);
        $this->assertSame(['sla_paused', 'sla_resumed'], $ticket->events()->whereIn('event_type', ['sla_paused', 'sla_resumed'])->pluck('event_type')->all());
    }

    public function test_leaving_waiting_state_ends_active_pause_and_records_who(): void
    {
        $ticket = $this->play('critical', '08:00', [['08:05', 'triage'], ['08:10', 'assign'], ['08:20', 'parts'], ['08:30', 'pause'], ['09:00', 'work']]);

        $pause = SlaPause::sole();
        $this->assertSame('09:00', $pause->resumed_at->format('H:i'));
        $this->assertSame($this->engineer->id, $pause->resumed_by);
        $this->assertSame('Ticket left awaiting_parts', $pause->resume_reason);
        $this->assertSame('assigned', $ticket->fresh()->state);
    }

    // --- Pure interval arithmetic ----------------------------------------

    public function test_elapsed_merges_overlapping_and_nested_pauses_and_clips_to_window(): void
    {
        $start = $this->at('08:00');
        $end = $this->at('12:00'); // 240 minutes
        $pauses = [
            [$this->at('07:00'), $this->at('08:30')], // clipped to 08:00-08:30 (30)
            [$this->at('09:00'), $this->at('10:00')], // 60
            [$this->at('09:15'), $this->at('09:45')], // nested, adds 0
            [$this->at('09:50'), $this->at('10:20')], // overlaps, adds 20
            [$this->at('11:30'), null],               // open, runs to end (30)
        ];

        $this->assertSame(240 - 30 - 80 - 30, $this->sla->elapsedMinutes($start, $end, $pauses));
        $this->assertSame(0, $this->sla->elapsedMinutes($end, $start));
    }

    // ---------------------------------------------------------------------

    /** Replay a scripted ticket history with the real services, travelling in time. */
    private function play(?string $severity, string $reportedAt, array $steps): Ticket
    {
        Carbon::setTestNow($this->at($reportedAt));
        $ticket = $this->tickets->report($this->asset, $this->reporter, 'Scripted fault', $severity);

        foreach ($steps as [$time, $action]) {
            Carbon::setTestNow($this->at($time));
            [$verb, $arg] = array_pad(explode(':', $action, 2), 2, null);
            match ($verb) {
                'triage' => $this->tickets->triage($ticket, $this->engineer, $ticket->severity ?? 'major'),
                'assign' => $this->tickets->assign($ticket, $this->supervisor, $this->engineer),
                'parts' => $this->tickets->awaitParts($ticket, $this->engineer, 'Part on order'),
                'vendor' => $this->tickets->awaitVendor($ticket, $this->engineer, 'Vendor visit needed'),
                'work' => $this->tickets->resumeWork($ticket, $this->engineer, 'Ready to continue'),
                'pause' => $this->sla->pause($ticket, $this->supervisor, 'parts_on_order'),
                'resume' => $this->sla->resume($ticket, $this->supervisor),
                'repaired' => $this->tickets->markRepaired($ticket, $this->engineer),
                'fail' => $this->tickets->verify($ticket, $this->supervisor, false, 'Retest failed'),
                'verify' => $this->tickets->verify($ticket, $this->supervisor, true),
                'verify_via_repair' => [$this->tickets->markRepaired($ticket, $this->engineer), $this->tickets->verify($ticket, $this->supervisor, true)],
                'reclassify' => $this->tickets->reclassify($ticket, $this->supervisor, $arg, 'Reassessed'),
            };
        }

        return $ticket;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse(self::DAY . (strlen($time) === 5 ? "{$time}:00" : $time), 'UTC');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }
}
