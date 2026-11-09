<?php

namespace App\Services\Gaps;

use App\Models\Ticket;
use Illuminate\Support\Carbon;

/** G3: ticket remains unassigned beyond a configured threshold. */
class TicketUnassignedRule implements GapRule
{
    public function code(): string
    {
        return 'G3';
    }

    public function title(): string
    {
        return 'Ticket unassigned too long';
    }

    public function evaluate(Carbon $now): array
    {
        $hours = (int) config('gaps.unassigned_hours', 24);
        $cutoff = $now->copy()->subHours($hours);
        $findings = [];

        $tickets = Ticket::query()
            ->whereIn('state', ['reported', 'triaged'])
            ->whereNull('assigned_to')
            ->where('recorded_at', '<=', $cutoff)
            ->get();

        foreach ($tickets as $ticket) {
            $age = (int) floor($ticket->recorded_at->diffInHours($now, true));
            $findings[] = new GapFindingData(
                'G3', 'ticket', $ticket->id,
                "Ticket #{$ticket->id} on asset #{$ticket->asset_id} has been {$ticket->state} and unassigned for {$age} hours (threshold {$hours}).",
                [
                    'ticket_id' => $ticket->id, 'asset_id' => $ticket->asset_id, 'state' => $ticket->state,
                    'severity' => $ticket->severity, 'recorded_at' => $ticket->recorded_at->toIso8601String(),
                ],
                'Assign an engineer, or escalate to a supervisor.',
            );
        }

        return $findings;
    }
}
