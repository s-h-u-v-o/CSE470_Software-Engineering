<?php

namespace App\Services\Gaps;

use App\Models\Asset;
use App\Models\Ticket;
use App\Services\DowntimeLedger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * G8 (redefined for the current code): records contradict each other.
 * The code derives readiness and never stores it, so there is no cached status to compare.
 * SRS wording for G8 must be updated to match. Checks:
 *   a) a retired asset still has an open ticket or an active hold
 *   b) a ticket is in a work state but has no assignee
 *   c) a closed ticket still has an unresolved SLA pause
 *   d) the downtime ledger cannot be derived (end without start, or start twice)
 */
class ContradictoryRecordsRule implements GapRule
{
    public function __construct(private DowntimeLedger $ledger)
    {
    }

    public function code(): string
    {
        return 'G8';
    }

    public function title(): string
    {
        return 'Contradictory records';
    }

    public function evaluate(Carbon $now): array
    {
        return array_merge(
            $this->retiredAssetsStillInUse(),
            $this->workStateWithoutAssignee(),
            $this->closedTicketsWithOpenPause(),
            $this->brokenDowntimeEvents(),
        );
    }

    private function retiredAssetsStillInUse(): array
    {
        $findings = [];
        $assets = Asset::query()->where('lifecycle_state', 'retired')->with(['tickets', 'holds'])->get();

        foreach ($assets as $asset) {
            foreach ($asset->tickets->where('state', '!=', 'closed') as $ticket) {
                $findings[] = new GapFindingData(
                    'G8', 'asset', $asset->id,
                    "Asset #{$asset->id} is retired but ticket #{$ticket->id} is still {$ticket->state}.",
                    ['asset_id' => $asset->id, 'ticket_id' => $ticket->id, 'ticket_state' => $ticket->state],
                    'Close the ticket, or correct the retirement record.',
                    "retired_open_ticket:{$ticket->id}",
                );
            }
            foreach ($asset->holds->whereNull('ended_at') as $hold) {
                $findings[] = new GapFindingData(
                    'G8', 'asset', $asset->id,
                    "Asset #{$asset->id} is retired but {$hold->type} hold #{$hold->id} is still active.",
                    ['asset_id' => $asset->id, 'hold_id' => $hold->id, 'hold_type' => $hold->type],
                    'Release the hold, or correct the retirement record.',
                    "retired_active_hold:{$hold->id}",
                );
            }
        }

        return $findings;
    }

    private function workStateWithoutAssignee(): array
    {
        $tickets = Ticket::query()
            ->whereIn('state', ['assigned', 'awaiting_parts', 'awaiting_vendor', 'repaired', 'verified'])
            ->whereNull('assigned_to')
            ->get();

        return $tickets->map(fn (Ticket $t) => new GapFindingData(
            'G8', 'ticket', $t->id,
            "Ticket #{$t->id} is {$t->state} but has no assigned engineer.",
            ['ticket_id' => $t->id, 'asset_id' => $t->asset_id, 'state' => $t->state],
            'Record who owns the work, or correct the ticket state.',
            'work_state_without_assignee',
        ))->all();
    }

    private function closedTicketsWithOpenPause(): array
    {
        $tickets = Ticket::query()
            ->where('state', 'closed')
            ->whereHas('slaPauses', fn ($q) => $q->whereNull('resumed_at'))
            ->with(['slaPauses' => fn ($q) => $q->whereNull('resumed_at')])
            ->get();

        return $tickets->map(fn (Ticket $t) => new GapFindingData(
            'G8', 'ticket', $t->id,
            "Ticket #{$t->id} is closed but still has an unresolved SLA pause.",
            ['ticket_id' => $t->id, 'open_pause_ids' => $t->slaPauses->pluck('id')->all()],
            'End the pause with a recorded reason, or review the closure.',
            'closed_with_open_pause',
        ))->all();
    }

    private function brokenDowntimeEvents(): array
    {
        $findings = [];

        foreach (Asset::query()->get() as $asset) {
            try {
                $this->ledger->forAsset($asset);
            } catch (ValidationException $e) {
                $message = (string) collect($e->errors())->flatten()->first();
                $findings[] = new GapFindingData(
                    'G8', 'asset', $asset->id,
                    "Asset #{$asset->id} has inconsistent downtime events: {$message}",
                    ['asset_id' => $asset->id, 'ledger_error' => $message],
                    'Review the downtime status events for this asset and correct them with a compensating record.',
                    'downtime:' . md5($message),
                );
            }
        }

        return $findings;
    }
}
