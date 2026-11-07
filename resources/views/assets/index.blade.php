<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BioMedOps - Asset Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">BioMedOps Asset Dashboard</h1>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <strong>Blocked by Service Layer:</strong> {{ session('error') }}
            </div>
        @endif

        <!-- Asset Table -->
        <div class="bg-white shadow-md rounded my-6">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Asset Name</th>
                        <th class="px-4 py-2 text-left">Serial</th>
                        <th class="px-4 py-2 text-left">Lifecycle State</th>
                        <th class="px-4 py-2 text-left">Checklist Status</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    @forelse($assets as $asset)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $asset->name }}</td>
                            <td class="px-4 py-3">{{ $asset->serial_number }}</td>
                            <td class="px-4 py-3 font-semibold text-blue-600">
                                {{ $asset->lifecycle_state }}
                            </td>
                            <td class="px-4 py-3">
                                @if($asset->commissioningChecklists->isEmpty())
                                    <span class="text-red-500 text-sm">No checklist items</span>
                                @else
                                    <span class="text-green-600 text-sm">{{ $asset->commissioningChecklists->count() }} items</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('assets.show', $asset) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-1 px-3 rounded text-sm mb-2 inline-block">
                                    Manage Checklist
                                </a>
                                <!-- Buttons based on current state -->
                                @if($asset->lifecycle_state === 'received_crated')
                                    <form action="{{ route('assets.transition', $asset) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="state" value="installed">
                                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">Move to Installed</button>
                                    </form>
                                @elseif($asset->lifecycle_state === 'installed')
                                    <form action="{{ route('assets.transition', $asset) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="state" value="commissioned">
                                        <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-sm">Attempt Commission</button>
                                    </form>
                                @elseif($asset->lifecycle_state === 'commissioned')
                                    <form action="{{ route('assets.transition', $asset) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="state" value="retired">
                                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-sm">Retire Asset</button>
                                    </form>
                                @else
                                    <span class="text-gray-400">No actions</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">No assets found in the database.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>