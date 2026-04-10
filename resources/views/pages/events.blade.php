@extends('tracker::layout')

@section('title', 'Events')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Events</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $events->total() }} total</p>
    </div>

    <form method="GET" class="mb-4 flex gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="name" placeholder="Event name (exact)" value="{{ $filter }}"
               class="flex-1 rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Payload</th>
                    <th class="px-4 py-3">Session</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($events as $event)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $event->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $event->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 truncate max-w-md">
                            {{ $event->payload ? json_encode($event->payload) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($event->session)
                                <a href="{{ route('tracker.sessions.show', $event->session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                    {{ Str::limit($event->session->uuid, 12, '…') }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No events.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
@endsection
