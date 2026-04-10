<nav class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('tracker.overview') }}" class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                    <svg class="size-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                    </svg>
                    Tracker
                </a>
                <div class="flex gap-1 text-sm">
                    @php
                        $navItems = [
                            ['route' => 'tracker.overview', 'label' => 'Overview'],
                            ['route' => 'tracker.sessions.index', 'label' => 'Sessions'],
                            ['route' => 'tracker.page-views', 'label' => 'Page views'],
                            ['route' => 'tracker.events', 'label' => 'Events'],
                        ];
                    @endphp
                    @foreach ($navItems as $item)
                        @if (\Illuminate\Support\Facades\Route::has($item['route']))
                            <a href="{{ route($item['route']) }}"
                               class="rounded-md px-3 py-1.5 transition {{ request()->routeIs($item['route']) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</nav>
