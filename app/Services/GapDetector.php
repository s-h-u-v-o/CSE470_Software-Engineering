<?php

namespace App\Services;

use App\Models\GapFinding;
use App\Services\Gaps\CommissionedWithoutChecklistRule;
use App\Services\Gaps\ContradictoryRecordsRule;
use App\Services\Gaps\CrateTooLongRule;
use App\Services\Gaps\GapFindingData;
use App\Services\Gaps\GapRule;
use App\Services\Gaps\TicketUnassignedRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F25: gap detection engine.
 *
 * Core rules run now: G1, G3, G7, G8. The remaining codes are enabled when the
 * feature they depend on is Done: add a GapRule class, register it here, and remove
 * the code from NOT_ENABLED.
 *
 * A finding names the records involved. It does not claim a physical fault.
 */
class GapDetector
{
    /** @var array<string, string> code => dependency that must be delivered first */
    public const NOT_ENABLED = [
        'G2' => 'needs F04 warranty/AMC tracker',
        'G4' => 'needs F19 escalation chain',
        'G5' => 'needs F12 PM plans and due engine',
        'G6' => 'needs F24 calibration records',
        'G9' => 'needs F23 corrective actions',
        'G10' => 'needs F05 operator authorizations and roster',
    ];

    /** @var list<GapRule> */
    private array $rules;

    public function __construct(
        CrateTooLongRule $g1,
        TicketUnassignedRule $g3,
        CommissionedWithoutChecklistRule $g7,
        ContradictoryRecordsRule $g8,
    ) {
        $this->rules = [$g1, $g3, $g7, $g8];
    }

    /**
     * Evaluate all enabled rules without saving anything.
     *
     * @return array{findings: list<GapFindingData>, enabled: list<string>, not_enabled: array<string, string>}
     */
    public function detect(?Carbon $now = null): array
    {
        $now ??= now();
        $findings = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->evaluate($now) as $finding) {
                $findings[] = $finding;
            }
        }

        return [
            'findings' => $findings,
            'enabled' => array_map(fn (GapRule $rule) => $rule->code(), $this->rules),
            'not_enabled' => self::NOT_ENABLED,
        ];
    }

    /**
     * Detect and persist. Findings that disappeared are marked resolved, and a
     * finding that comes back is reopened. Open findings of codes that are not enabled are left alone.
     */
    public function scan(?Carbon $now = null): array
    {
        $now ??= now();
        $result = $this->detect($now);

        DB::transaction(function () use ($result, $now) {
            $seen = [];
            foreach ($result['findings'] as $finding) {
                $fingerprint = $finding->fingerprint();
                $seen[] = $fingerprint;

                $attributes = [
                    'code' => $finding->code,
                    'subject_type' => $finding->subjectType,
                    'subject_id' => $finding->subjectId,
                    'message' => $finding->message,
                    'records' => $finding->records,
                    'next_step' => $finding->nextStep,
                    'last_seen_at' => $now,
                    'resolved_at' => null,
                ];

                $existing = GapFinding::query()->where('fingerprint', $fingerprint)->first();
                if ($existing) {
                    $existing->update($attributes);
                } else {
                    GapFinding::create($attributes + ['fingerprint' => $fingerprint, 'first_detected_at' => $now]);
                }
            }

            GapFinding::query()
                ->whereNull('resolved_at')
                ->whereIn('code', $result['enabled'])
                ->whereNotIn('fingerprint', $seen)
                ->update(['resolved_at' => $now]);
        });

        return $result;
    }
}
