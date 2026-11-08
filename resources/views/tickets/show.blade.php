<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #{{ $ticket->id }} - BioMedOps</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
<div class="max-w-3xl mx-auto">
    <a href="{{ route('tickets.index') }}" class="text-blue-500 hover:underline mb-4 inline-block">&larr; All tickets</a>

    <h1 class="text-2xl font-bold">Ticket #{{ $ticket->id }}: {{ str_replace('_', ' ', $ticket->state) }}</h1>
    <p class="text-gray-600 mb-4">
        <a class="underline" href="{{ route('assets.show', $ticket->asset) }}">{{ $ticket->asset->name }}</a>
        · severity <strong>{{ $ticket->severity ?? 'not triaged' }}</strong>
        · reported by {{ $ticket->reporter?->name }}
        · assignee {{ $ticket->assignee?->name ?? '—' }}
    </p>
    <p class="mb-4">{{ $ticket->description }}</p>
    <p class="mb-6 font-semibold {{ $blocks ? 'text-red-700' : 'text-green-700' }}">
        {{ $blocks ? 'Blocks technical readiness of this asset' : 'Does not block technical readiness' }}
    </p>

    @if(session('success'))
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><strong>Rejected:</strong> {{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white shadow rounded p-4 mb-6">
        <h2 class="text-lg font-bold mb-3">SLA clock {{ $sla['paused'] ? '(paused)' : '' }}</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            @foreach(['response' => 'Response (reported → assigned)', 'resolution' => 'Resolution (reported → verified)'] as $key => $label)
                @php $c = $sla[$key]; @endphp
                <div class="border rounded p-3 {{ $c['breached'] ? 'border-red-500' : '' }}">
                    <p class="font-semibold">{{ $label }}</p>
                    <p>Elapsed: <strong>{{ $c['elapsed_minutes'] }} min</strong></p>
                    <p>Target: {{ $c['target_minutes'] !== null ? $c['target_minutes'] . ' min' : 'no policy until severity is set' }}</p>
                    <p>Status: <strong class="{{ $c['breached'] ? 'text-red-700' : '' }}">{{ str_replace('_', ' ', $c['status']) }}</strong></p>
                </div>
            @endforeach
        </div>
    </section>

    @if($ticket->state !== 'closed')
    <section class="bg-white shadow rounded p-4 mb-6 space-y-4">
        <h2 class="text-lg font-bold">Actions</h2>

        @if($ticket->state === 'reported')
            <form action="{{ route('tickets.triage', $ticket) }}" method="POST" class="flex flex-wrap gap-2">
                @csrf
                <select name="severity" class="border rounded p-2" required>
                    @foreach(['critical', 'major', 'minor'] as $sev)
                        <option value="{{ $sev }}" @selected($ticket->severity === $sev)>{{ ucfirst($sev) }}</option>
                    @endforeach
                </select>
                <input name="note" class="border rounded p-2 flex-1" placeholder="Triage note">
                @include('tickets._actor')
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Triage</button>
            </form>
        @endif

        @if($ticket->state === 'triaged')
            <form action="{{ route('tickets.assign', $ticket) }}" method="POST" class="flex flex-wrap gap-2">
                @csrf
                <select name="engineer_id" class="border rounded p-2" required>
                    @foreach($engineers as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
                @include('tickets._actor')
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Assign engineer</button>
            </form>
        @endif

        @php
            $actions = match ($ticket->state) {
                'assigned' => ['await_parts' => 'Awaiting parts', 'await_vendor' => 'Awaiting vendor', 'repaired' => 'Mark repaired'],
                'awaiting_parts', 'awaiting_vendor' => ['resume_work' => 'Resume work'],
                'repaired' => ['verify_pass' => 'Verify (pass)', 'verify_fail' => 'Verification failed'],
                'verified' => ['close' => 'Close ticket'],
                default => [],
            };
        @endphp
        @if($actions)
            <form action="{{ route('tickets.transition', $ticket) }}" method="POST" class="flex flex-wrap gap-2">
                @csrf
                <select name="action" class="border rounded p-2">
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input name="reason" class="border rounded p-2 flex-1" placeholder="Reason / note (required for waiting and failed verification)">
                @include('tickets._actor')
                <button class="bg-gray-800 text-white px-4 py-2 rounded">Apply</button>
            </form>
        @endif

        @if(in_array($ticket->state, ['awaiting_parts', 'awaiting_vendor']))
            @if($sla['paused'])
                <form action="{{ route('tickets.sla.resume', $ticket) }}" method="POST" class="flex flex-wrap gap-2">
                    @csrf
                    <input name="note" class="border rounded p-2 flex-1" placeholder="Resume note">
                    @include('tickets._actor')
                    <button class="bg-amber-600 text-white px-4 py-2 rounded">Resume SLA clock</button>
                </form>
            @else
                <form action="{{ route('tickets.sla.pause', $ticket) }}" method="POST" class="flex flex-wrap gap-2">
                    @csrf
                    <select name="reason_code" class="border rounded p-2" required>
                        @foreach($pauseReasons as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input name="note" class="border rounded p-2 flex-1" placeholder="Pause note">
                    @include('tickets._actor')
                    <button class="bg-amber-600 text-white px-4 py-2 rounded">Pause SLA clock</button>
                </form>
            @endif
        @endif

        @if($ticket->state !== 'reported')
            <form action="{{ route('tickets.reclassify', $ticket) }}" method="POST" class="flex flex-wrap gap-2 border-t pt-4">
                @csrf
                <select name="severity" class="border rounded p-2" required>
                    @foreach(['critical', 'major', 'minor'] as $sev)
                        <option value="{{ $sev }}" @disabled($ticket->severity === $sev)>{{ ucfirst($sev) }}</option>
                    @endforeach
                </select>
                <input name="reason" class="border rounded p-2 flex-1" placeholder="Reason for reclassification" required>
                @include('tickets._actor')
                <button class="bg-purple-700 text-white px-4 py-2 rounded">Reclassify (supervisor)</button>
            </form>
        @endif
    </section>
    @endif

    <section class="bg-white shadow rounded p-4 mb-6">
        <h2 class="text-lg font-bold mb-3">SLA pauses</h2>
        @forelse($ticket->slaPauses as $p)
            <p class="text-sm border-b py-1">
                {{ $p->paused_at->format('Y-m-d H:i') }} → {{ $p->resumed_at?->format('Y-m-d H:i') ?? 'active' }}
                · {{ $pauseReasons[$p->reason_code] ?? $p->reason_code }}
                · by {{ $p->pausedBy?->name }} ({{ $p->paused_by_role }})
                @if($p->resumed_at) · resumed by {{ $p->resumedBy?->name }} ({{ $p->resumed_by_role }}): {{ $p->resume_reason }} @endif
            </p>
        @empty
            <p class="text-gray-500 text-sm">No pauses.</p>
        @endforelse
    </section>

    <section class="bg-white shadow rounded p-4">
        <h2 class="text-lg font-bold mb-3">History (append-only)</h2>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th>Time (UTC)</th><th>Event</th><th>State</th><th>Severity</th><th>Actor</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach($ticket->events as $e)
                <tr class="border-t align-top">
                    <td class="py-1 pr-2 whitespace-nowrap">{{ $e->occurred_at->format('Y-m-d H:i:s') }}</td>
                    <td class="pr-2">{{ str_replace('_', ' ', $e->event_type) }}</td>
                    <td class="pr-2">{{ $e->from_state }}{{ $e->from_state !== $e->to_state ? ' → ' . $e->to_state : '' }}</td>
                    <td class="pr-2">{{ $e->from_severity || $e->to_severity ? ($e->from_severity ?? '—') . ' → ' . $e->to_severity : '' }}</td>
                    <td class="pr-2">{{ $e->actor?->name }} ({{ $e->actor_role }})</td>
                    <td>{{ $e->reason }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
