<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Carbon;

class ReadinessService
{
    private const OPEN_TICKET_STATES = [
        'reported', 'triaged', 'assigned', 'awaiting_parts', 'awaiting_vendor', 'repaired',
    ];

    /** Return a derived result; readiness is never persisted on the asset. */
    public function for(Asset $asset, ?Carbon $at = null): array
    {
        $at ??= now();
        $asset->loadMissing(['commissioningChecklists', 'tickets', 'holds']);

        $activeHolds = $asset->holds
            ->filter(fn ($hold) => $hold->started_at->lte($at) && (!$hold->ended_at || $hold->ended_at->gt($at)));
        $quarantine = $activeHolds->firstWhere('type', 'quarantine');
        $outOfService = $activeHolds->firstWhere('type', 'out_of_service');
        $blockingTicket = $asset->tickets
            ->filter(fn ($ticket) => in_array($ticket->state, self::OPEN_TICKET_STATES, true))
            ->first(fn ($ticket) => $ticket->severity === 'critical'
                || ($ticket->severity === 'major' && config('readiness.major_blocks', false)));
        $failedChecks = $asset->commissioningChecklists
            ->filter(fn ($item) => $item->is_mandatory && !$item->is_passed);

        // The precedence is part of the displayed result, while blockers are reported independently.
        if ($quarantine) {
            $state = 'quarantined';
            $blocker = $quarantine->reason;
        } elseif ($blockingTicket) {
            $state = 'blocked_by_ticket';
            $blocker = $blockingTicket->description;
        } elseif ($outOfService) {
            $state = 'out_of_service';
            $blocker = $outOfService->reason;
        } elseif ($asset->lifecycle_state !== 'commissioned' || $failedChecks->isNotEmpty()) {
            $state = 'not_ready';
            $blocker = $asset->lifecycle_state !== 'commissioned'
                ? 'Lifecycle is ' . str_replace('_', ' ', $asset->lifecycle_state)
                : 'Required checks pending: ' . $failedChecks->pluck('item_name')->join(', ');
        } else {
            $state = 'technically_ready';
            $blocker = null;
        }

        return [
            'state' => $state,
            'technically_ready' => $state === 'technically_ready',
            'blocker' => $blocker,
            'active_holds' => $activeHolds->values(),
            'blocking_ticket' => $blockingTicket,
            'failed_checks' => $failedChecks->values(),
        ];
    }
}
