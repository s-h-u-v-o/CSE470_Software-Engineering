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