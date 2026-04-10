<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tracker') — {{ config('app.name', 'Laravel') }}</title>

    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <link href="https://rsms.me/inter/inter.css" rel="stylesheet">
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    <div class="min-h-full">
        @include('tracker::partials.nav')

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>
</body>
</html>
