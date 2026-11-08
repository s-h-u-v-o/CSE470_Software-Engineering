<?php

namespace App\Services;

use App\Exceptions\TicketWorkflowException;
use App\Models\SlaPause;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Support\Role;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F18: SLA clock with authorized pauses (brief Section 9).
 *
 * - Response clock:   reported -> first assignment.
 * - Resolution clock: reported -> first passed verification.
 * - Elapsed = floor((end - start - merged pause seconds) / 60). Integer arithmetic only.
 * - Breach = elapsed strictly greater than target (equal to target is met).
 * - Targets come from the ticket's current severity (a reclassification
 *   re-evaluates the whole clock against the new severity's policy).
 * - Pauses: supervisor only, only while awaiting_parts / awaiting_vendor,
 *   only if the policy allows; leaving the waiting state ends the pause.
 */
class SlaEngine
{
    public const PAUSE_REASONS = [
        'parts_on_order' => 'Spare parts on order',
        'vendor_visit_pending' => 'Vendor engineer visit pending',
        'vendor_quote_pending' => 'Vendor quotation/approval pending',
        'site_access_unavailable' => 'Site or equipment access unavailable',
    ];

    public function pause(Ticket $ticket, User $actor, string $reasonCode, ?string $note = null): SlaPause
    {
        Role::require($actor, 'pause an SLA clock', Role::SUPERVISOR);
        if (!array_key_exists($reasonCode, self::PAUSE_REASONS)) {
            throw new TicketWorkflowException("Unknown SLA pause reason code '{$reasonCode}'.");
        }

        return DB::transaction(function () use ($ticket, $actor, $reasonCode, $note) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->state, TicketService::WAITING_STATES, true)) {
                throw new TicketWorkflowException(
                    "SLA can only be paused while awaiting parts or vendor; ticket #{$locked->id} is '{$locked->state}'."
                );
            }
            $policy = SlaPolicy::forSeverity($locked->severity);
            if (!$policy || !$policy->pause_allowed) {
                throw new TicketWorkflowException(
                    "The SLA policy for severity '{$locked->severity}' does not permit pauses."
                );
            }
            if ($this->activePause($locked)) {
                throw new TicketWorkflowException("Ticket #{$locked->id} already has an active SLA pause.");
            }

            $now = now();
            $pause = SlaPause::create([
                'ticket_id' => $locked->id,
                'reason_code' => $reasonCode,
                'note' => $note,
                'ticket_state' => $locked->state,
                'paused_at' => $now,
                'paused_by' => $actor->id,
                'paused_by_role' => Role::of($actor),
            ]);
            $this->record($locked, $actor, 'sla_paused', $reasonCode . ($note ? ": {$note}" : ''));

            return $pause;
        });
    }

    public function resume(Ticket $ticket, User $actor, ?string $note = null): SlaPause
    {
        Role::require($actor, 'resume an SLA clock', Role::SUPERVISOR);

        return DB::transaction(function () use ($ticket, $actor, $note) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $pause = $this->endActivePause($locked, $actor, $note ?? 'Resumed by supervisor');
            if (!$pause) {
                throw new TicketWorkflowException("Ticket #{$locked->id} has no active SLA pause to resume.");
            }

            return $pause;
        });
    }

    /**
     * End the active pause, if any. Must be called inside a transaction that
     * already holds the ticket row lock (TicketService does this on state exit).
     */
    public function endActivePause(Ticket $lockedTicket, User $actor, string $reason): ?SlaPause
    {
        $pause = $this->activePause($lockedTicket);
        if (!$pause) {
            return null;
        }

        $pause->update([
            'resumed_at' => now(),
            'resumed_by' => $actor->id,
            'resumed_by_role' => Role::of($actor),
            'resume_reason' => $reason,
        ]);
        $this->record($lockedTicket, $actor, 'sla_resumed', $reason);

        return $pause;
    }

    /**
     * Evaluate both SLA clocks at time $at (default now).
     *
     * @return array{severity: ?string, policy: ?SlaPolicy, paused: bool, response: array, resolution: array}
     */
    public function evaluate(Ticket $ticket, ?CarbonInterface $at = null): array
    {
        $at = Carbon::instance($at ?? now());
        $events = TicketEvent::where('ticket_id', $ticket->id)->orderBy('occurred_at')->orderBy('id')->get();
        $pauses = SlaPause::where('ticket_id', $ticket->id)->where('paused_at', '<=', $at)->get();
        $policy = SlaPolicy::forSeverity($ticket->severity);

        $start = Carbon::instance($ticket->recorded_at);
        $entered = fn (string $state) => $events
            ->first(fn (TicketEvent $e) => $e->to_state === $state && $e->from_state !== $state)?->occurred_at;
        $assignedAt = $entered('assigned');
        $verifiedAt = $entered('verified');

        $intervals = $pauses->map(fn (SlaPause $p) => [
            $p->paused_at,
            $p->resumed_at && $p->resumed_at->lte($at) ? $p->resumed_at : $at,
        ])->all();
        $paused = $pauses->contains(fn (SlaPause $p) => $p->resumed_at === null || $p->resumed_at->gt($at));

        return [
            'severity' => $ticket->severity,
            'policy' => $policy,
            'paused' => $paused,
            'response' => $this->clock($start, $assignedAt, $at, $policy?->response_minutes, $intervals, $paused),
            'resolution' => $this->clock($start, $verifiedAt, $at, $policy?->resolution_minutes, $intervals, $paused),
        ];
    }

    /**
     * Whole minutes between $start and $end, minus the union of pause
     * intervals clipped to [start, end). Floors once, after subtraction.
     *
     * @param  array<int, array{0: CarbonInterface, 1: ?CarbonInterface}>  $pauses
     */
    public function elapsedMinutes(CarbonInterface $start, CarbonInterface $end, array $pauses = []): int
    {
        $s = $start->getTimestamp();
        $e = $end->getTimestamp();
        if ($e <= $s) {
            return 0;
        }

        $clipped = [];
        foreach ($pauses as [$pStart, $pEnd]) {
            $a = max($s, $pStart->getTimestamp());
            $b = min($e, ($pEnd ?? $end)->getTimestamp());
            if ($b > $a) {
                $clipped[] = [$a, $b];
            }
        }
        usort($clipped, fn ($x, $y) => $x[0] <=> $y[0]);

        $pausedSeconds = 0;
        $curStart = $curEnd = null;
        foreach ($clipped as [$a, $b]) {
            if ($curEnd === null || $a > $curEnd) {
                $pausedSeconds += $curEnd === null ? 0 : $curEnd - $curStart;
                [$curStart, $curEnd] = [$a, $b];
            } else {
                $curEnd = max($curEnd, $b);
            }
        }
        $pausedSeconds += $curEnd === null ? 0 : $curEnd - $curStart;

        return intdiv(($e - $s) - $pausedSeconds, 60);
    }

    private function clock(Carbon $start, ?CarbonInterface $stoppedAt, Carbon $at, ?int $target, array $pauses, bool $paused): array
    {
        $stopped = $stoppedAt !== null && $stoppedAt->lte($at);
        $end = $stopped ? $stoppedAt : $at;
        $elapsed = $this->elapsedMinutes($start, $end, $pauses);
        $breached = $target !== null && $elapsed > $target;

        $status = match (true) {
            $target === null => 'no_policy',
            $stopped => $breached ? 'breached' : 'met',
            $breached => 'breached',
            $paused => 'paused',
            default => 'running',
        };

        return [
            'target_minutes' => $target,
            'elapsed_minutes' => $elapsed,
            'remaining_minutes' => $target === null ? null : $target - $elapsed,
            'breached' => $breached,
            'stopped' => $stopped,
            'stopped_at' => $stopped ? $stoppedAt : null,
            'status' => $status,
        ];
    }

    private function activePause(Ticket $ticket): ?SlaPause
    {
        return SlaPause::where('ticket_id', $ticket->id)->whereNull('resumed_at')->lockForUpdate()->first();
    }

    private function record(Ticket $ticket, User $actor, string $type, ?string $reason): void
    {
        $now = now();
        TicketEvent::create([
            'ticket_id' => $ticket->id,
            'event_type' => $type,
            'from_state' => $ticket->state,
            'to_state' => $ticket->state,
            'actor_id' => $actor->id,
            'actor_role' => Role::of($actor),
            'reason' => $reason,
            'occurred_at' => $now,
            'recorded_at' => $now,
        ]);
    }
}
