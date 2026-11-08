<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BioMedOps - Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
<div class="max-w-5xl mx-auto">
    <a href="{{ route('assets.index') }}" class="text-blue-500 hover:underline mb-4 inline-block">&larr; Asset dashboard</a>
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Maintenance Tickets</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><strong>Rejected:</strong> {{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white shadow rounded p-4 mb-6">
        <h2 class="text-lg font-bold mb-3">Report a fault</h2>
        <form action="{{ route('tickets.store') }}" method="POST" class="flex flex-wrap gap-2">
            @csrf
            <select name="asset_id" class="border rounded p-2" required>
                @foreach($assets as $asset)
                    <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->serial_number }})</option>
                @endforeach
            </select>
            <input name="description" class="border rounded p-2 flex-1" placeholder="What is wrong?" value="{{ old('description') }}" required>
            <select name="severity" class="border rounded p-2" title="Provisional severity; confirmed at triage">
                <option value="">Severity: unknown</option>
                <option value="critical">Critical (blocks now)</option>
                <option value="major">Major</option>
                <option value="minor">Minor</option>
            </select>
            @include('tickets._actor')
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Report</button>
        </form>
    </section>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full table-auto text-sm">
            <thead class="bg-gray-800 text-white">
            <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">Asset</th>
                <th class="px-3 py-2 text-left">State</th>
                <th class="px-3 py-2 text-left">Severity</th>
                <th class="px-3 py-2 text-left">Assignee</th>
                <th class="px-3 py-2 text-left">Response SLA</th>
                <th class="px-3 py-2 text-left">Resolution SLA</th>
            </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr class="border-b">
                    <td class="px-3 py-2"><a class="text-blue-600 underline" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->id }}</a></td>
                    <td class="px-3 py-2">{{ $ticket->asset->name }}</td>
                    <td class="px-3 py-2">{{ str_replace('_', ' ', $ticket->state) }}</td>
                    <td class="px-3 py-2">{{ $ticket->severity ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $ticket->assignee?->name ?? '—' }}</td>
                    @foreach(['response', 'resolution'] as $clock)
                        @php $c = $sla[$ticket->id][$clock]; @endphp
                        <td class="px-3 py-2 {{ $c['breached'] ? 'text-red-700 font-semibold' : '' }}">
                            {{ $c['elapsed_minutes'] }}{{ $c['target_minutes'] !== null ? ' / ' . $c['target_minutes'] : '' }} min
                            <span class="text-gray-500">({{ str_replace('_', ' ', $c['status']) }})</span>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No tickets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
