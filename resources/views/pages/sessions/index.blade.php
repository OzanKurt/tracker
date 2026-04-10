@extends('tracker::layout')

@section('title', 'Sessions')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Sessions</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $sessions->total() }} total</p>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="country" placeholder="Country code (e.g. TR)" value="{{ $filters['country'] }}"
               class="rounded-md border-slate-300 text-sm">
        <select name="device" class="rounded-md border-slate-300 text-sm">
            <option value="">Any device</option>
            @foreach (['desktop', 'mobile', 'tablet', 'bot'] as $kind)
                <option value="{{ $kind }}" @selected($filters['device'] === $kind)>{{ ucfirst($kind) }}</option>
            @endforeach
        </select>
        <input type="text" name="browser" placeholder="Browser" value="{{ $filters['browser'] }}"
               class="rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
        <a href="{{ route('tracker.sessions.index') }}" class="rounded-md px-4 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
            Reset
        </a>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Country</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Browser</th>
                    <th class="px-4 py-3">Views</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-indigo-600">{{ $session->uuid }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $session->client_ip }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->country_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->device_kind }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->browser }} {{ $session->browser_version }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-900">{{ $session->page_views_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No sessions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sessions->links() }}
    </div>
@endsection
