@extends('tracker::layout')

@section('title', 'User '.$userId)

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">User #{{ $userId }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $sessions->total() }} session(s)</p>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Browser</th>
                    <th class="px-4 py-3">Views</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $session)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('tracker.sessions.show', $session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $session->uuid }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $session->client_ip }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->device_kind }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $session->browser }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-900">{{ $session->page_views_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No sessions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
@endsection
