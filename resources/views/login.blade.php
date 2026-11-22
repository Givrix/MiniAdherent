@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-6 text-xl font-semibold text-slate-900">Connexion</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                @if ($errors->has('email') && ! str_contains($errors->first('email'), '@'))
                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('email') }}</p>
                @endif
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Mot de passe</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600">
                    Se souvenir de moi
                </label>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                    Se connecter
                </button>
            </div>
        </form>
    </div>
@endsection
