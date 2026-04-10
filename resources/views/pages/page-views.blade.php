@extends('tracker::layout')

@section('title', 'Page views')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Page views</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $pageViews->total() }} total</p>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="text" name="path" placeholder="Path contains..." value="{{ $filters['path'] }}"
               class="flex-1 min-w-[16rem] rounded-md border-slate-300 text-sm">
        <input type="text" name="route" placeholder="Exact route name" value="{{ $filters['route'] }}"
               class="rounded-md border-slate-300 text-sm">
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Path</th>
                    <th class="px-4 py-3">Route</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Session</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pageViews as $view)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $view->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $view->method }}</td>
                        <td class="px-4 py-3 text-slate-900 truncate max-w-md">{{ $view->path }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $view->route_name ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-700">{{ $view->status_code ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($view->session)
                                <a href="{{ route('tracker.sessions.show', $view->session->uuid) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                    {{ Str::limit($view->session->uuid, 12, '…') }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No page views.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $pageViews->links() }}</div>
@endsection
