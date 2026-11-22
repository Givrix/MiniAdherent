<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Setting;
use App\Services\SubscriptionPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings', [
            'mode' => SubscriptionPeriod::mode(),
            'fixedPeriodEnd' => SubscriptionPeriod::fixedPeriodEnd(),
            'fixedSwitchDate' => SubscriptionPeriod::fixedSwitchDate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscription_mode' => ['required', 'in:'.implode(',', SubscriptionPeriod::MODES)],
            'fixed_period_end' => ['nullable', 'regex:/^\d{1,2}\/\d{1,2}$/'],
            'fixed_switch_date' => ['nullable', 'regex:/^\d{1,2}\/\d{1,2}$/'],
        ], [
            'subscription_mode.required' => 'Le mode d’adhésion est obligatoire.',
            'subscription_mode.in' => 'Le mode d’adhésion est invalide.',
            'fixed_period_end.regex' => 'La date de fin de période doit être au format JJ/MM.',
            'fixed_switch_date.regex' => 'La date de bascule doit être au format JJ/MM.',
        ]);

        foreach (['fixed_period_end', 'fixed_switch_date'] as $key) {
            if (! empty($data[$key])) {
                [$day, $month] = array_map('intval', explode('/', $data[$key]));

                if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
                    return back()->withErrors([
                        $key => 'Cette date JJ/MM est invalide (jour 1-31, mois 1-12).',
                    ])->withInput();
                }

                $data[$key] = sprintf('%02d/%02d', $day, $month);
            }
        }

        if (isset($data['fixed_period_end'])) {
            Setting::set('fixed_period_end', $data['fixed_period_end']);
        }

        if (isset($data['fixed_switch_date'])) {
            Setting::set('fixed_switch_date', $data['fixed_switch_date']);
        }

        Setting::set('subscription_mode', $data['subscription_mode']);

        return redirect()->route('settings.edit')->with('status', 'Paramètres enregistrés.');
    }

    public function exportMembers(): StreamedResponse
    {
        $filename = 'adherents-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['morphy', 'firstname', 'lastname', 'pseudo', 'company', 'email', 'subscription_date', 'subscription_end'], ';');

            Member::query()->orderBy('lastname')->orderBy('firstname')->chunk(500, function ($members) use ($out) {
                foreach ($members as $member) {
                    fputcsv($out, [
                        $member->morphy,
                        $member->firstname,
                        $member->lastname,
                        $member->pseudo,
                        $member->company,
                        $member->email,
                        $member->subscription_date?->toDateString(),
                        $member->subscription_end?->toDateString(),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importMembers(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Choisir un fichier CSV.',
            'file.mimes' => 'Le fichier doit être un CSV.',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $separator = $this->detectSeparator($handle);

        $header = fgetcsv($handle, 0, $separator);
        if ($header === false) {
            fclose($handle);

            return redirect()->route('settings.edit')->with('import_errors', ['Fichier vide ou illisible.']);
        }

        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        $columns = array_map(fn ($column) => Str::of(trim((string) $column))->lower()->__toString(), $header);
        $required = ['firstname', 'lastname', 'pseudo', 'company', 'email', 'morphy', 'subscription_date'];

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $line++;
            $memberData = [];

            foreach ($required as $index => $name) {
                $position = array_search($name, $columns, true);
                $memberData[$name] = $position === false ? '' : trim((string) ($row[$position] ?? ''));
            }

            if (implode('', array_map('strval', $row)) === '') {
                continue;
            }

            $morphy = in_array($memberData['morphy'], ['phy', 'mor'], true) ? $memberData['morphy'] : 'phy';

            $hasIdentity = ($memberData['firstname'] !== '' && $memberData['lastname'] !== '')
                || ($morphy === 'phy' && $memberData['pseudo'] !== '');

            if ($morphy === 'phy' && ! $hasIdentity) {
                $errors[] = "Ligne {$line} : prénom et nom requis, ou un pseudo.";

                continue;
            }

            if ($morphy === 'mor' && $memberData['company'] === '') {
                $errors[] = "Ligne {$line} : société requise pour une personne morale.";

                continue;
            }

            if (! filter_var($memberData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Ligne {$line} : email invalide.";

                continue;
            }

            $subscriptionDate = null;
            if ($memberData['subscription_date'] !== '') {
                $subscriptionDate = $this->parseDate($memberData['subscription_date']);
                if ($subscriptionDate === null) {
                    $errors[] = "Ligne {$line} : date d’adhésion illisible (attendu AAAA-MM-JJ).";

                    continue;
                }
                if ($subscriptionDate->isFuture()) {
                    $errors[] = "Ligne {$line} : date d’adhésion dans le futur.";

                    continue;
                }
            }

            $attributes = [
                'morphy' => $morphy,
                'firstname' => $memberData['firstname'] !== '' ? mb_strtoupper($memberData['firstname']) : null,
                'lastname' => $memberData['lastname'] !== '' ? mb_strtoupper($memberData['lastname']) : null,
                'pseudo' => $memberData['pseudo'] !== '' ? $memberData['pseudo'] : null,
                'company' => $memberData['company'] !== '' ? $memberData['company'] : null,
                'email' => $memberData['email'] !== '' ? $memberData['email'] : null,
                'subscription_date' => $subscriptionDate?->toDateString(),
                'subscription_end' => $subscriptionDate !== null ? SubscriptionPeriod::computeEnd($subscriptionDate->toDateString())->toDateString() : null,
            ];

            $existing = $attributes['email'] !== null
                ? Member::query()->where('email', $attributes['email'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $updated++;
            } else {
                if ($subscriptionDate !== null) {
                    $attributes['created_at'] = $subscriptionDate->toDateTimeString();
                }

                Member::create($attributes);
                $created++;
            }
        }

        fclose($handle);

        return redirect()->route('settings.edit')->with([
            'import_created' => $created,
            'import_updated' => $updated,
            'import_errors' => $errors,
        ]);
    }

    private function detectSeparator($handle): string
    {
        $firstLine = fgets($handle) ?: '';
        rewind($handle);

        return substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    }

    private function parseDate(string $value): ?Carbon
    {
        $date = Carbon::createFromFormat('Y-m-d', $value);

        return $date instanceof Carbon && $date->format('Y-m-d') === $value ? $date : null;
    }
}
