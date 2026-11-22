@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</p>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="mb-4 text-lg font-semibold text-slate-900">Paramètres d’adhésion</h1>

            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf

                <fieldset>
                    <legend class="mb-2 block text-sm font-medium text-slate-700">Mode de calcul de la fin d’adhésion</legend>

                    <label class="mb-2 flex items-start gap-2 text-sm">
                        <input type="radio" name="subscription_mode" value="floating" class="mt-1 text-indigo-600"
                               @checked($mode === 'floating')>
                        <span>
                            <strong class="text-slate-800">Date flottante</strong>
                            <span class="block text-slate-500">Fin d’adhésion = date d’adhésion + 1 an (réadhésion reportée depuis la période en cours).</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="subscription_mode" value="fixed" class="mt-1 text-indigo-600"
                               @checked($mode === 'fixed')>
                        <span>
                            <strong class="text-slate-800">Période fixe annuelle</strong>
                            <span class="block text-slate-500">Fin d’adhésion à la date de fin de période (avant la date de bascule) ou à la date de fin de période de l’année suivante (à partir de la date de bascule).</span>
                        </span>
                    </label>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="fixed_period_end" class="mb-1 block text-sm font-medium text-slate-700">Date de fin de période (JJ/MM)</label>
                            <input id="fixed_period_end" name="fixed_period_end" type="text" inputmode="numeric" placeholder="31/07" value="{{ old('fixed_period_end', $fixedPeriodEnd) }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                            @error('fixed_period_end')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="fixed_switch_date" class="mb-1 block text-sm font-medium text-slate-700">Date de bascule (JJ/MM)</label>
                            <input id="fixed_switch_date" name="fixed_switch_date" type="text" inputmode="numeric" placeholder="01/06" value="{{ old('fixed_switch_date', $fixedSwitchDate) }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                            <p class="mt-1 text-xs text-slate-500">Adhésion avant cette date → fin de période de l’année en cours ; à partir de cette date → fin de période de l’année suivante.</p>
                            @error('fixed_switch_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        Enregistrer
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Import / export CSV</h2>

            @if (session('import_created') !== null || session('import_updated') !== null || session('import_errors'))
                <div class="mb-4 space-y-2 rounded-md bg-slate-50 p-3 text-sm">
                    <p class="text-emerald-700">Import terminé : {{ session('import_created', 0) }} créés, {{ session('import_updated', 0) }} mis à jour.</p>
                    @foreach (session('import_errors', []) as $error)
                        <p class="text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <h3 class="mb-1 text-sm font-medium text-slate-800">Export</h3>
                    <a href="{{ route('members.export') }}"
                       class="inline-block rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50">
                        Télécharger tous les adhérents (CSV)
                    </a>
                </div>

                <div>
                    <h3 class="mb-1 text-sm font-medium text-slate-800">Import</h3>
                    <p class="mb-2 text-xs text-slate-500">
                        En-têtes attendus : <code class="rounded bg-slate-100 px-1">morphy;firstname;lastname;pseudo;company;email;created_at;subscription_end</code> (dates AAAA-MM-JJ ; la date d'adhésion est déduite de la fin d'adhésion moins un an).
                        Délimiteur auto-détecté (`,` ou `;`), UTF-8, dates au format AAAA-MM-JJ.
                        Les lignes dont l’email correspond à un adhérent existant le mettent à jour, les autres créent un adhérent.
                    </p>
                    <form method="POST" action="{{ route('members.import') }}" enctype="multipart/form-data" class="flex items-center gap-3">
                        @csrf
                        <input type="file" name="file" accept=".csv,text/csv"
                               class="text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:text-slate-700 hover:file:bg-slate-200">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                            Importer
                        </button>
                    </form>
                    @error('file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>
    </div>
@endsection
