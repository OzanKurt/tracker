@extends('tracker::layout')

@section('title', 'Session '.$session->uuid)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-mono text-lg text-slate-900">{{ $session->uuid }}</h1>
            <p class="mt-1 text-sm text-slate-600">Started {{ $session->started_at->diffForHumans() }}</p>
        </div>
        <a href="{{ route('tracker.sessions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to sessions</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Visitor</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Visitor ID</dt>
                    <dd class="truncate font-mono text-xs text-slate-700">{{ $session->visitor_uuid }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">User ID</dt>
                    <dd class="text-slate-700">{{ $session->user_id ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">IP</dt>
                    <dd class="font-mono text-xs text-slate-700">{{ $session->client_ip }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Language</dt>
                    <dd class="text-slate-700">{{ $session->language }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Device</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Kind</dt>
                    <dd class="text-slate-700">{{ $session->device_kind }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Platform</dt>
                    <dd class="text-slate-700">{{ $session->device_platform }} {{ $session->device_platform_ver }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Browser</dt>
                    <dd class="text-slate-700">{{ $session->browser }} {{ $session->browser_version }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Bot</dt>
                    <dd class="text-slate-700">{{ $session->is_robot ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Location &amp; referer</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Country</dt>
                    <dd class="text-slate-700">{{ $session->country_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">City</dt>
                    <dd class="text-slate-700">{{ $session->city ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Referer</dt>
                    <dd class="truncate text-slate-700">{{ $session->referer_domain ?? 'direct' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Medium</dt>
                    <dd class="text-slate-700">{{ $session->referer_medium ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Timeline ({{ $timeline->count() }})</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($timeline as $entry)
                <li class="flex items-start gap-4 px-5 py-3 text-sm">
                    <span class="mt-0.5 rounded px-2 py-0.5 text-xs font-medium {{ $entry['type'] === 'event' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $entry['type'] === 'event' ? 'event' : 'view' }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-slate-900">{{ $entry['label'] }}</p>
                        @if ($entry['meta'])
                            <p class="truncate font-mono text-xs text-slate-500">{{ $entry['meta'] }}</p>
                        @endif
                    </div>
                    <time class="text-xs text-slate-500">{{ $entry['at']->format('H:i:s') }}</time>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-slate-500 text-sm">No activity.</li>
            @endforelse
        </ul>
    </div>
@endsection
