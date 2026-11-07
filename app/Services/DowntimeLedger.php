<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DowntimeLedger
{
    /**
     * Derive clipped, merged technical downtime from paired append-only events.
     * Intervals are half-open [start, end), so touching intervals have no gap.
     */
    public function forAsset(Asset $asset, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $to ??= now();
        $from ??= Carbon::createFromTimestamp(0, $to->getTimezone());
        if ($to->lte($from)) {
            throw ValidationException::withMessages(['window' => 'The report end must be after its start.']);
        }

        $asset->loadMissing('statusEvents');
        $events = $asset->statusEvents
            ->filter(fn ($event) => $event->downtime_action && $event->cause_key)
            ->sort(fn ($a, $b) => [$a->occurred_at->getTimestamp(), $a->id] <=> [$b->occurred_at->getTimestamp(), $b->id]);

        $active = [];
        $raw = [];
        foreach ($events as $event) {
            $key = $event->cause_key;
            if ($event->downtime_action === 'start') {
                if (isset($active[$key])) {
                    throw ValidationException::withMessages(['events' => "Downtime cause {$key} was started twice without an end event."]);
                }
                $active[$key] = $event;
                continue;
            }

            if (!isset($active[$key])) {
                throw ValidationException::withMessages(['events' => "Downtime cause {$key} has an end event without a start."]);
            }
            $start = $active[$key];
            unset($active[$key]);
            if ($event->occurred_at->gt($start->occurred_at)) {
                $raw[] = ['start' => $start->occurred_at->copy(), 'end' => $event->occurred_at->copy(), 'cause' => $start->cause_type];
            }
        }

        $now = now();
        foreach ($active as $start) {
            $raw[] = ['start' => $start->occurred_at->copy(), 'end' => $now->copy(), 'cause' => $start->cause_type];
        }

        // Operational downtime starts at the asset's first commissioning event.
        $commissionedAt = $asset->statusEvents
            ->filter(fn ($event) => $event->next_state === 'commissioned')
            ->sortBy('occurred_at')->first()?->occurred_at;
        if (!$commissionedAt) {
            return ['intervals' => [], 'planned_minutes' => 0, 'unplanned_minutes' => 0, 'total_minutes' => 0];
        }
        $clipStart = $from->copy()->max($commissionedAt);
        $clipEnd = $to->copy()->min($now);
        if ($clipEnd->lte($clipStart)) {
            return ['intervals' => [], 'planned_minutes' => 0, 'unplanned_minutes' => 0, 'total_minutes' => 0];
        }

        $segments = [];
        foreach ($raw as $interval) {
            $start = $interval['start']->copy()->max($clipStart);
            $end = $interval['end']->copy()->min($clipEnd);
            if ($end->gt($start)) {
                $segments[] = ['start' => $start, 'end' => $end, 'cause' => $interval['cause'] === 'planned' ? 'planned' : 'unplanned'];
            }
        }

        // Sweep all boundaries; unplanned wins wherever both causes overlap.
        $points = collect($segments)->flatMap(fn ($i) => [$i['start'], $i['end']])
            ->unique(fn ($point) => $point->getTimestamp())
            ->sortBy(fn ($point) => $point->getTimestamp())->values();
        $merged = [];
        for ($i = 0; $i < $points->count() - 1; $i++) {
            $start = $points[$i];
            $end = $points[$i + 1];
            $covering = collect($segments)->filter(fn ($interval) => $interval['start']->lt($end) && $interval['end']->gt($start));
            if ($covering->isEmpty()) {
                continue;
            }
            $cause = $covering->contains(fn ($interval) => $interval['cause'] === 'unplanned') ? 'unplanned' : 'planned';
            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['cause'] === $cause && $merged[$last]['end']->equalTo($start)) {
                $merged[$last]['end'] = $end->copy();
            } else {
                $merged[] = ['start' => $start->copy(), 'end' => $end->copy(), 'cause' => $cause];
            }
        }

        $planned = 0;
        $unplanned = 0;
        foreach ($merged as &$interval) {
            $interval['minutes'] = (int) floor($interval['start']->diffInSeconds($interval['end']) / 60);
            if ($interval['cause'] === 'planned') {
                $planned += $interval['minutes'];
            } else {
                $unplanned += $interval['minutes'];
            }
        }
        unset($interval);

        return [
            'intervals' => $merged,
            'planned_minutes' => $planned,
            'unplanned_minutes' => $unplanned,
            'total_minutes' => $planned + $unplanned,
        ];
    }
}
