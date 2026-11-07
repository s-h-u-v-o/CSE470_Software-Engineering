<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checklist - {{ $asset->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto">
        <a href="{{ route('assets.index') }}" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Back to Dashboard</a>
        
        <h1 class="text-2xl font-bold mb-2">Commissioning Checklist</h1>
        <p class="text-gray-600 mb-6">Asset: <strong>{{ $asset->name }}</strong> ({{ $asset->serial_number }})</p>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4">{{ session('success') }}</div>
        @endif
        <section class="bg-white shadow rounded p-4 mb-6">
            <h2 class="text-lg font-bold">Derived technical readiness</h2>
            <p class="mt-2 font-semibold {{ $readiness['technically_ready'] ? 'text-green-700' : 'text-red-700' }}">
                {{ str_replace('_', ' ', $readiness['state']) }}
            </p>
            @if($readiness['blocker'])
                <p class="text-gray-700">Blocker: {{ $readiness['blocker'] }}</p>
            @endif
            @if($readiness['blocking_ticket'])
                <p class="text-sm text-gray-600">Ticket #{{ $readiness['blocking_ticket']->id }} ({{ $readiness['blocking_ticket']->severity }}, {{ $readiness['blocking_ticket']->state }})</p>
            @endif
        </section>

        <section class="bg-white shadow rounded p-4 mb-6">
            <h2 class="text-lg font-bold mb-3">Quarantine and out-of-service holds</h2>
            @forelse($readiness['active_holds'] as $hold)
                <div class="flex justify-between items-center border-b py-2">
                    <span>{{ str_replace('_', ' ', $hold->type) }}: {{ $hold->reason }}</span>
                    <form action="{{ route('assets.holds.release', [$asset, $hold]) }}" method="POST">
                        @csrf
                        <button class="text-blue-700 underline">Release hold</button>
                    </form>
                </div>
            @empty
                <p class="text-gray-500 mb-3">No active holds.</p>
            @endforelse
            <form action="{{ route('assets.holds.create', $asset) }}" method="POST" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <select name="type" class="border rounded p-2" required>
                    <option value="quarantine">Quarantine</option>
                    <option value="out_of_service">Out of service</option>
                </select>
                <input name="reason" class="border rounded p-2 flex-1" placeholder="Reason for hold" required>
                <button class="bg-red-600 text-white px-4 py-2 rounded">Activate hold</button>
            </form>
        </section>

        <section class="bg-white shadow rounded p-4 mb-6">
            <h2 class="text-lg font-bold mb-3">Event-derived downtime ledger</h2>
            <div class="grid grid-cols-3 gap-3 mb-4 text-sm">
                <p>Planned: <strong>{{ $downtime['planned_minutes'] }} min</strong></p>
                <p>Unplanned: <strong>{{ $downtime['unplanned_minutes'] }} min</strong></p>
                <p>Total: <strong>{{ $downtime['total_minutes'] }} min</strong></p>
            </div>
            @if($downtime['intervals'])
                <ul class="divide-y mb-4">
                    @foreach($downtime['intervals'] as $interval)
                        <li class="py-2 flex justify-between">
                            <span>{{ ucfirst($interval['cause']) }} · {{ $interval['start']->format('Y-m-d H:i') }} – {{ $interval['end']->format('Y-m-d H:i') }}</span>
                            <strong>{{ $interval['minutes'] }} min</strong>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-500 mb-4">No operational downtime intervals recorded.</p>
            @endif

            @forelse($activeDowntimes as $event)
                <div class="flex justify-between items-center border-t py-2">
                    <span>Open {{ $event->cause_type }} interval · {{ $event->reason }} · started {{ $event->occurred_at->format('Y-m-d H:i') }}</span>
                    <form action="{{ route('assets.downtime.end', [$asset, $event]) }}" method="POST">
                        @csrf
                        <button class="text-blue-700 underline">End interval</button>
                    </form>
                </div>
            @empty
            @endforelse

            <form action="{{ route('assets.downtime.start', $asset) }}" method="POST" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <select name="cause_type" class="border rounded p-2" required>
                    <option value="unplanned">Unplanned</option>
                    <option value="planned">Planned</option>
                </select>
                <select name="ticket_id" class="border rounded p-2">
                    <option value="">No linked ticket</option>
                    @foreach($asset->tickets as $ticket)
                        <option value="{{ $ticket->id }}">Ticket #{{ $ticket->id }} · {{ $ticket->severity ?? 'unclassified' }}</option>
                    @endforeach
                </select>
                <input name="reason" class="border rounded p-2 flex-1" placeholder="Downtime cause" required>
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Start interval</button>
            </form>
        </section>

        <!-- List existing items -->
        <div class="bg-white shadow rounded mb-6">
            <ul class="divide-y divide-gray-200">
                @forelse($asset->commissioningChecklists as $item)
                    <li class="p-4 flex justify-between items-center">
                        <div>
                            <span class="font-semibold">{{ $item->item_name }}</span>
                            @if($item->is_mandatory)
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded ml-2">Mandatory</span>
                            @endif
                        </div>
                        <div class="flex items-center space-x-4">
                            @if($item->is_passed)
                                <span class="text-green-600 font-bold">PASSED</span>
                            @else
                                <span class="text-red-600 font-bold">PENDING</span>
                            @endif
                            
                            <form action="{{ route('assets.checklist.toggle', [$asset, $item]) }}" method="POST">
                                @csrf
                                <button class="text-sm underline {{ $item->is_passed ? 'text-red-500' : 'text-green-500' }}">
                                    Mark as {{ $item->is_passed ? 'Pending' : 'Passed' }}
                                </button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="p-4 text-gray-500 text-center">No checklist items yet.</li>
                @endforelse
            </ul>
        </div>

        <!-- Add new item form -->
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-bold mb-4">Add Checklist Item</h2>
            <form action="{{ route('assets.checklist.add', $asset) }}" method="POST" class="flex items-center space-x-4">
                @csrf
                <input type="text" name="item_name" placeholder="e.g. Electrical Safety Test" class="border p-2 rounded w-full" required>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_mandatory" value="1" checked class="form-checkbox">
                    <span>Mandatory</span>
                </label>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Add</button>
            </form>
        </div>
    </div>
</body>
</html>
