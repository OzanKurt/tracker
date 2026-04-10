@extends('tracker::layout')

@section('title', 'Overview')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Overview</h1>
            <p class="mt-1 text-sm text-slate-600">Visitor analytics for the last 24 hours.</p>
        </div>
    </div>

    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @include('tracker::partials.stat-card', ['label' => 'Unique visitors', 'value' => number_format($uniqueVisitors)])
        @include('tracker::partials.stat-card', ['label' => 'Sessions', 'value' => number_format($sessionsCount)])
        @include('tracker::partials.stat-card', ['label' => 'Page views', 'value' => number_format($pageViewsCount)])
        @include('tracker::partials.stat-card', ['label' => 'Online now', 'value' => number_format($onlineCount), 'sublabel' => 'last 3 minutes'])
    </dl>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Sessions over time</h2>
        <div class="mt-4 h-64">
            <canvas id="sessions-chart"
                    data-labels="{{ json_encode($sessionsOverTime->pluck('bucket')) }}"
                    data-values="{{ json_encode($sessionsOverTime->pluck('sessions')) }}"></canvas>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top pages</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topPages as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="truncate text-slate-700">{{ $row->path }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->views) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top countries</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topCountries as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="text-slate-700">{{ $row->country_name ?? $row->country_code }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->sessions) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Top browsers</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topBrowsers as $row)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="text-slate-700">{{ $row->browser }}</span>
                        <span class="ml-2 tabular-nums text-slate-900">{{ number_format((int) $row->sessions) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-slate-500">No data.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('sessions-chart');
            if (!canvas) return;

            const labels = JSON.parse(canvas.dataset.labels || '[]');
            const values = JSON.parse(canvas.dataset.values || '[]');

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sessions',
                        data: values,
                        borderColor: 'rgb(79 70 229)',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } },
                    },
                },
            });
        });
    </script>
@endsection
