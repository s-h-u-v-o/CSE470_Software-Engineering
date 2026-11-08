<?php

namespace App\Services;

use App\Exceptions\TicketWorkflowException;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Support\Role;
use Illuminate\Support\Facades\DB;

/**
 * F16: ticket workflow with severity and blocking rules (brief Section 6B).
 *
 * reported -> triaged -> assigned -> (awaiting_parts | awaiting_vendor) -> repaired -> verified -> closed
 *
 * Every change runs in a transaction, locks the ticket row, re-checks the
 * current state, and appends a TicketEvent. Closed tickets are immutable.
 */
class TicketService
{
    public const SEVERITIES = ['critical', 'major', 'minor'];

    public const WAITING_STATES = ['awaiting_parts', 'awaiting_vendor'];

    /** States in which a ticket can block technical readiness (same set as ReadinessService). */
    public const BLOCKING_STATES = [
        'reported', 'triaged', 'assigned', 'awaiting_parts', 'awaiting_vendor', 'repaired',
    ];

    public const TRANSITIONS = [
        'reported' => ['triaged'],
        'triaged' => ['assigned'],
        'assigned' => ['awaiting_parts', 'awaiting_vendor', 'repaired'],
        'awaiting_parts' => ['assigned'],
        'awaiting_vendor' => ['assigned'],
        'repaired' => ['verified', 'assigned'], // failed verification returns to assigned
        'verified' => ['closed'],
        'closed' => [],
    ];

    public function __construct(private SlaEngine $sla)
    {
    }

    /**
     * Report a fault. The reporter may flag a provisional severity so that a
     * critical fault blocks readiness immediately, before triage.
     */
    public function report(Asset $asset, User $reporter, string $description, ?string $provisionalSeverity = null): Ticket
    {
        Role::require($reporter, 'report a fault', ...array_diff(Role::ALL, [Role::AUDITOR]));
        $this->assertSeverity($provisionalSeverity, nullable: true);
        if (trim($description) === '') {
            throw new TicketWorkflowException('A fault description is required.');
        }
        if ($asset->lifecycle_state === 'retired') {
            throw new TicketWorkflowException('Cannot report a fault on a retired asset.');
        }

        return DB::transaction(function () use ($asset, $reporter, $description, $provisionalSeverity) {
            $now = now();
            $ticket = Ticket::create([
                'asset_id' => $asset->id,
                'reported_by' => $reporter->id,
                'state' => 'reported',
                'severity' => $provisionalSeverity,
                'description' => $description,
                'occurred_at' => $now,
                'recorded_at' => $now,
            ]);
            $this->record($ticket, $reporter, 'reported', null, 'reported', null, $provisionalSeverity, 'Fault reported');

            return $ticket;
        });
    }

    /** Triage sets (or confirms) the severity. */
    public function triage(Ticket $ticket, User $actor, string $severity, ?string $note = null): Ticket
    {
        Role::require($actor, 'triage tickets', Role::ENGINEER, Role::SUPERVISOR);
        $this->assertSeverity($severity);

        return $this->move($ticket, $actor, 'triaged', 'triaged', $note, function (Ticket $t) use ($severity) {
            $from = $t->severity;
            $t->severity = $severity;

            return [$from, $severity];
        });
    }

    /** Assign a biomedical engineer. An engineer assigning themselves is a claim. */
    public function assign(Ticket $ticket, User $actor, User $engineer, ?string $note = null): Ticket
    {
        Role::require($actor, 'assign tickets', Role::ENGINEER, Role::SUPERVISOR);
        if (!Role::is($engineer, Role::ENGINEER)) {
            throw new TicketWorkflowException("User #{$engineer->id} is not a biomedical engineer and cannot be assigned.");
        }
        if (Role::is($actor, Role::ENGINEER) && $actor->id !== $engineer->id) {
            throw new TicketWorkflowException('Engineers may only assign tickets to themselves; a supervisor assigns others.');
        }

        return $this->move($ticket, $actor, 'assigned', 'assigned', $note, function (Ticket $t) use ($engineer) {
            if ($t->assigned_to !== null) {
                throw new TicketWorkflowException("Ticket #{$t->id} is already assigned.");
            }
            $t->assigned_to = $engineer->id;
        });
    }

    public function awaitParts(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return $this->workStep($ticket, $actor, 'awaiting_parts', 'awaiting_parts', $reason);
    }

    public function awaitVendor(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return $this->workStep($ticket, $actor, 'awaiting_vendor', 'awaiting_vendor', $reason);
    }

    /** Parts/vendor arrived: back to assigned. Any active SLA pause ends here. */
    public function resumeWork(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        return $this->workStep($ticket, $actor, 'assigned', 'work_resumed', $reason, self::WAITING_STATES);
    }

    public function markRepaired(Ticket $ticket, User $actor, ?string $note = null): Ticket
    {
        return $this->workStep($ticket, $actor, 'repaired', 'repaired', $note);
    }

    /** Supervisor verification. A failure returns the ticket to assigned; history is kept. */
    public function verify(Ticket $ticket, User $actor, bool $passed, ?string $note = null): Ticket
    {
        Role::require($actor, 'verify repairs', Role::SUPERVISOR);
        if (!$passed && blank($note)) {
            throw new TicketWorkflowException('A reason is required when verification fails.');
        }

        return $passed
            ? $this->move($ticket, $actor, 'verified', 'verified', $note)
            : $this->move($ticket, $actor, 'assigned', 'verification_failed', $note, null, ['repaired']);
    }

    public function close(Ticket $ticket, User $actor, ?string $note = null): Ticket
    {
        Role::require($actor, 'close tickets', Role::SUPERVISOR);

        return $this->move($ticket, $actor, 'closed', 'closed', $note);
    }

    /** Supervisor-only severity change after triage. Old/new value, actor, reason and time are recorded. */
    public function reclassify(Ticket $ticket, User $actor, string $newSeverity, string $reason): Ticket
    {
        Role::require($actor, 'reclassify severity', Role::SUPERVISOR);
        $this->assertSeverity($newSeverity);
        if (trim($reason) === '') {
            throw new TicketWorkflowException('A reason is required to reclassify severity.');
        }

        return DB::transaction(function () use ($ticket, $actor, $newSeverity, $reason) {
            $locked = $this->lock($ticket);
            if ($locked->state === 'closed') {
                throw new TicketWorkflowException("Ticket #{$locked->id} is closed and immutable.");
            }
            if ($locked->state === 'reported') {
                throw new TicketWorkflowException("Ticket #{$locked->id} has not been triaged; set severity through triage.");
            }
            if ($locked->severity === $newSeverity) {
                throw new TicketWorkflowException("Ticket #{$locked->id} is already {$newSeverity}.");
            }

            $from = $locked->severity;
            $locked->severity = $newSeverity;
            $locked->save();
            $this->record($locked, $actor, 'severity_reclassified', $locked->state, $locked->state, $from, $newSeverity, $reason);

            return $this->sync($ticket, $locked);
        });
    }

    /** Whether this ticket blocks technical readiness under the facility's configured rules. */
    public function blocksReadiness(Ticket $ticket): bool
    {
        if (!in_array($ticket->state, self::BLOCKING_STATES, true)) {
            return false;
        }

        return match ($ticket->severity) {
            'critical' => true,
            'major' => (bool) config('readiness.major_blocks', false),
            default => false,
        };
    }

    public function allowedNextStates(Ticket $ticket): array
    {
        return self::TRANSITIONS[$ticket->state] ?? [];
    }

    // ---------------------------------------------------------------------

    /** Steps performed by the assigned engineer (or a supervisor). */
    private function workStep(Ticket $ticket, User $actor, string $to, string $eventType, ?string $reason, ?array $requireFrom = null): Ticket
    {
        Role::require($actor, 'update repair work', Role::ENGINEER, Role::SUPERVISOR);
        if (in_array($to, self::WAITING_STATES, true) && blank($reason)) {
            throw new TicketWorkflowException('A reason is required when waiting for parts or a vendor.');
        }

        return $this->move($ticket, $actor, $to, $eventType, $reason, function (Ticket $t) use ($actor) {
            if (Role::is($actor, Role::ENGINEER) && $t->assigned_to !== $actor->id) {
                throw new TicketWorkflowException("Only the assigned engineer or a supervisor can update ticket #{$t->id}.");
            }
        }, $requireFrom);
    }

    /**
     * Guarded state change. $mutate may adjust the locked ticket and may
     * return [fromSeverity, toSeverity] to record a severity change.
     * $requireFrom narrows the allowed source states for this specific action.
     */
    private function move(Ticket $ticket, User $actor, string $to, string $eventType, ?string $reason, ?callable $mutate = null, ?array $requireFrom = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $to, $eventType, $reason, $mutate, $requireFrom) {
            $locked = $this->lock($ticket);
            $from = $locked->state;

            if ($from === 'closed') {
                throw new TicketWorkflowException("Ticket #{$locked->id} is closed and immutable. Report a new ticket for a new fault.");
            }
            if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new TicketWorkflowException("Invalid ticket transition from '{$from}' to '{$to}'.");
            }
            if ($requireFrom !== null && !in_array($from, $requireFrom, true)) {
                throw new TicketWorkflowException("Action '{$eventType}' is not allowed from '{$from}'.");
            }

            $severityChange = $mutate ? $mutate($locked) : null;
            $locked->state = $to;
            $locked->save();

            $this->record(
                $locked, $actor, $eventType, $from, $to,
                $severityChange[0] ?? null, $severityChange[1] ?? null, $reason
            );

            // Pauses are only eligible while waiting; leaving that state ends any active pause.
            if (in_array($from, self::WAITING_STATES, true) && !in_array($to, self::WAITING_STATES, true)) {
                $this->sla->endActivePause($locked, $actor, "Ticket left {$from}");
            }

            return $this->sync($ticket, $locked);
        });
    }

    private function lock(Ticket $ticket): Ticket
    {
        return Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
    }

    /** Keep the caller's instance in step with the persisted row. */
    private function sync(Ticket $ticket, Ticket $locked): Ticket
    {
        $ticket->setRawAttributes($locked->getAttributes(), true);

        return $ticket;
    }

    private function record(Ticket $ticket, User $actor, string $type, ?string $from, ?string $to, ?string $fromSev, ?string $toSev, ?string $reason): TicketEvent
    {
        $now = now();

        return TicketEvent::create([
            'ticket_id' => $ticket->id,
            'event_type' => $type,
            'from_state' => $from,
            'to_state' => $to,
            'from_severity' => $fromSev,
            'to_severity' => $toSev,
            'actor_id' => $actor->id,
            'actor_role' => Role::of($actor),
            'reason' => $reason,
            'occurred_at' => $now,
            'recorded_at' => $now,
        ]);
    }

    private function assertSeverity(?string $severity, bool $nullable = false): void
    {
        if ($severity === null && $nullable) {
            return;
        }
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new TicketWorkflowException("Invalid severity '{$severity}'. Use critical, major or minor.");
        }
    }
}
