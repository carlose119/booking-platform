<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Booking Platform') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @yield('content')
    </main>
    @livewireScripts
    @stack('scripts')
</body>
</html>
