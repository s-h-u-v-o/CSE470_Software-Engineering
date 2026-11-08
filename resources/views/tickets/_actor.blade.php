{{-- Dev-only placeholder until login exists: the server still enforces each role. --}}
@guest
    <select name="acting_user_id" class="border rounded p-2 text-sm" required title="Acting as (development only)">
        @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(old('acting_user_id') == $u->id)>{{ $u->name }} ({{ str_replace('_', ' ', $u->role) }})</option>
        @endforeach
    </select>
@endguest
