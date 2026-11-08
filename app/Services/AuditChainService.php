<?php

namespace App\Services;

use App\Models\AuditChainHead;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F26: hash-chained audit trail.
 *
 * hash = sha256(canonical JSON of: sequence, event type, actor, subject, payload, occurred_at, prev_hash)
 *
 * Call append() as the LAST write inside the caller's DB transaction, so the audit
 * entry commits or rolls back with the business change and the chain head is locked last.
 * Payloads must contain only strings, ints, bools, null and nested arrays (no floats, no objects).
 */
class AuditChainService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public function append(string $eventType, array $payload = [], ?User $actor = null, ?Model $subject = null): AuditEntry
    {
        return DB::transaction(function () use ($eventType, $payload, $actor, $subject) {
            $head = AuditChainHead::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $fields = [
                'sequence' => $head->last_sequence + 1,
                'event_type' => $eventType,
                'actor_id' => $actor?->id,
                'actor_role' => $actor ? Role::of($actor) : null,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'payload' => $payload,
                'occurred_at' => now()->startOfSecond(), // stored precision is whole seconds
                'prev_hash' => $head->last_hash,
            ];
            $fields['hash'] = $this->hashFor($fields);

            $entry = AuditEntry::create($fields);
            $head->update(['last_sequence' => $fields['sequence'], 'last_hash' => $fields['hash']]);

            return $entry;
        });
    }

    /**
     * Walk the chain from the start and report the first broken link.
     *
     * @return array{valid: bool, entries_checked: int, first_break: array{sequence: int, reason: string, detail: string}|null}
     */
    public function verify(): array
    {
        $expectedSequence = 1;
        $expectedPrev = self::GENESIS_HASH;
        $checked = 0;

        foreach (AuditEntry::query()->orderBy('sequence')->cursor() as $entry) {
            if ($entry->sequence !== $expectedSequence) {
                return $this->broken($checked, $expectedSequence, 'sequence_gap',
                    "Expected entry {$expectedSequence} but found {$entry->sequence}: an entry is missing or reordered.");
            }
            if ($entry->prev_hash !== $expectedPrev) {
                return $this->broken($checked, $entry->sequence, 'prev_hash_mismatch',
                    "Entry {$entry->sequence} does not link to the hash of entry " . ($entry->sequence - 1) . '.');
            }
            $recomputed = $this->hashFor([
                'sequence' => $entry->sequence,
                'event_type' => $entry->event_type,
                'actor_id' => $entry->actor_id,
                'actor_role' => $entry->actor_role,
                'subject_type' => $entry->subject_type,
                'subject_id' => $entry->subject_id,
                'payload' => $entry->payload,
                'occurred_at' => $entry->occurred_at,
                'prev_hash' => $entry->prev_hash,
            ]);
            if (!hash_equals($entry->hash, $recomputed)) {
                return $this->broken($checked, $entry->sequence, 'hash_mismatch',
                    "Entry {$entry->sequence} contents no longer match its stored hash.");
            }

            $expectedPrev = $entry->hash;
            $expectedSequence++;
            $checked++;
        }

        // Detects removal of the newest entries: the head must point at the last entry we walked.
        $head = AuditChainHead::query()->find(1);
        if (!$head || $head->last_sequence !== $expectedSequence - 1 || $head->last_hash !== $expectedPrev) {
            return $this->broken($checked, $head?->last_sequence ?? $expectedSequence, 'head_mismatch',
                'The chain head does not match the last stored entry: recent entries may have been removed.');
        }

        return ['valid' => true, 'entries_checked' => $checked, 'first_break' => null];
    }

    private function broken(int $checked, int $sequence, string $reason, string $detail): array
    {
        return [
            'valid' => false,
            'entries_checked' => $checked,
            'first_break' => ['sequence' => $sequence, 'reason' => $reason, 'detail' => $detail],
        ];
    }

    private function hashFor(array $f): string
    {
        $record = [
            'sequence' => (int) $f['sequence'],
            'event_type' => $f['event_type'],
            'actor_id' => isset($f['actor_id']) ? (int) $f['actor_id'] : null,
            'actor_role' => $f['actor_role'] ?? null,
            'subject_type' => $f['subject_type'] ?? null,
            'subject_id' => isset($f['subject_id']) ? (int) $f['subject_id'] : null,
            'payload' => $f['payload'] ?? [],
            'occurred_at' => Carbon::parse($f['occurred_at'])->clone()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            'prev_hash' => $f['prev_hash'],
        ];

        return hash('sha256', json_encode(
            $this->canonicalize($record),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /** Sort associative keys recursively so key order never changes the hash. Lists keep their order. */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $mapped = array_map(fn ($item) => $this->canonicalize($item), $value);
        if (!array_is_list($mapped)) {
            ksort($mapped);
        }

        return $mapped;
    }
}
