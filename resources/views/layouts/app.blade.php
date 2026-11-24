<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MiniAdherent') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <span class="text-lg font-semibold text-slate-900">{{ config('app.name', 'MiniAdherent') }}</span>
            @auth
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}"
                       class="text-sm transition {{ request()->routeIs('home') && request()->query('mode') !== 'self' ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Saisie</a>
                    <a href="{{ route('home', ['mode' => 'self']) }}"
                       class="text-sm transition {{ request()->routeIs('home') && request()->query('mode') === 'self' ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Autoinscription</a>
                    <a href="{{ route('settings.edit') }}"
                       class="text-sm transition {{ request()->routeIs('settings.edit') ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Paramètres</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50">
                        Déconnexion
                    </button>
                </form>
                </div>
            @endauth
            @guest
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}"
                       class="text-sm transition {{ request()->routeIs('home') ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Saisie</a>
                    <a href="{{ route('login') }}"
                       class="text-sm transition {{ request()->routeIs('login') ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Connexion</a>
                </div>
            @endguest
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
