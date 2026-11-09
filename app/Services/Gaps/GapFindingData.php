<?php

namespace App\Services\Gaps;

/** One detected record problem. It describes records, not a physical fault. */
final class GapFindingData
{
    public function __construct(
        public readonly string $code,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $message,
        public readonly array $records,
        public readonly string $nextStep,
        public readonly string $detail = '',
    ) {
    }

    /** Stable identity so repeated scans update the same finding instead of duplicating it. */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [$this->code, $this->subjectType, $this->subjectId, $this->detail]));
    }
}
