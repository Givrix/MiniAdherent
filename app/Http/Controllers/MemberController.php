<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\SubscriptionPeriod;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function page()
    {
        return view('home');
    }

    public function search(Request $request): JsonResponse
    {
        $query = Member::query()
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(5);

        foreach (['firstname', 'lastname', 'pseudo', 'company', 'email'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '') {
                $query->where($field, 'like', "%{$value}%");
            }
        }

        $members = $query->get();

        if (! auth()->check()) {
            $members->transform(fn (Member $member) => $this->masked($member));
        }

        return response()->json($members);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if (! auth()->check()) {
            $data['subscription_date'] = null;
        }

        $this->rejectDuplicates($data, null);

        if (! empty($data['subscription_date'])) {
            $data['subscription_end'] = SubscriptionPeriod::computeEnd($data['subscription_date']);
            $data['created_at'] = Carbon::parse($data['subscription_date']);
        }

        $member = Member::create($data);

        return response()->json(auth()->check() ? $member : $this->masked($member), 201);
    }

    public function exists(Request $request): JsonResponse
    {
        return response()->json([
            'pseudo_exists' => $this->pseudoExists($request->query('pseudo'), (int) $request->query('ignore', 0)),
            'email_exists' => $this->emailExists($request->query('email'), (int) $request->query('ignore', 0)),
        ]);
    }

    public function show(Member $member): JsonResponse
    {
        return response()->json(auth()->check() ? $member : $this->masked($member));
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $data = $this->validated($request);

        $this->rejectDuplicates($data, $member->id);

        if (! empty($data['subscription_date'])) {
            $data['subscription_end'] = SubscriptionPeriod::computeEnd(
                $data['subscription_date'],
                $member->subscription_end,
                $request->boolean('renewal'),
            );
        } else {
            $data['subscription_end'] = null;
        }

        $member->update($data);

        return response()->json($member);
    }

    public function destroy(Member $member): JsonResponse
    {
        $member->delete();

        return response()->json(null, 204);
    }

    private function rejectDuplicates(array $data, ?int $ignoreId): void
    {
        $nameOnlyIdentity = $data['morphy'] === 'phy'
            && blank($data['firstname'] ?? null)
            && blank($data['lastname'] ?? null);

        if ($nameOnlyIdentity && ! blank($data['pseudo'] ?? null) && $this->pseudoExists($data['pseudo'], $ignoreId)) {
            throw ValidationException::withMessages([
                'pseudo' => 'Membre déjà existant (pseudo).',
            ]);
        }

        if (! blank($data['email'] ?? null) && $this->emailExists($data['email'], $ignoreId)) {
            throw ValidationException::withMessages([
                'email' => 'Membre déjà existant (email).',
            ]);
        }
    }

    private function pseudoExists(?string $pseudo, ?int $ignoreId): bool
    {
        return filled($pseudo) && Member::query()
            ->whereRaw('LOWER(pseudo) = ?', [mb_strtolower(trim($pseudo))])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function emailExists(?string $email, ?int $ignoreId): bool
    {
        return filled($email) && Member::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function masked(Member $member): array
    {
        return [
            'id' => $member->id,
            'morphy' => $member->morphy,
            'firstname' => $this->mask($member->firstname),
            'lastname' => $this->mask($member->lastname),
            'pseudo' => $this->mask($member->pseudo),
            'company' => $this->mask($member->company),
            'email' => $this->maskEmail($member->email),
            'subscription_date' => $member->subscription_date?->toDateString(),
            'subscription_end' => $member->subscription_end?->toDateString(),
        ];
    }

    private function mask(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $length = mb_strlen($value);

        if ($length <= 2) {
            return mb_substr($value, 0, 1).str_repeat('*', max(1, $length - 1));
        }

        return mb_substr($value, 0, 1).str_repeat('*', $length - 2).mb_substr($value, -1);
    }

    private function maskEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $labels = explode('.', $domain);
        $tld = array_pop($labels);

        return $this->mask($local).'@'
            .implode('.', array_map(fn (string $label) => $this->mask($label), $labels))
            .'.'.$tld;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'morphy' => ['required', 'in:phy,mor'],
            'firstname' => ['nullable', 'string', 'max:190'],
            'lastname' => ['nullable', 'string', 'max:190'],
            'pseudo' => ['nullable', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190', 'required_if:morphy,mor'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'subscription_date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'morphy.required' => 'La nature de l’adhérent est obligatoire.',
            'morphy.in' => 'La nature de l’adhérent est invalide.',
            'company.required_if' => 'La raison sociale est obligatoire pour un adhérent moral.',
            'email.email' => 'L’adresse email est invalide.',
            'subscription_date.date' => 'La date d’adhésion est invalide.',
            'subscription_date.before_or_equal' => 'La date d’adhésion ne peut pas être dans le futur.',
        ]);

        if ($data['morphy'] === 'phy'
            && (blank($data['pseudo'] ?? null) && (blank($data['firstname'] ?? null) || blank($data['lastname'] ?? null)))) {
            throw ValidationException::withMessages([
                'firstname' => 'Renseignez le prénom et le nom, ou un pseudo.',
            ]);
        }

        $data['lastname'] = isset($data['lastname']) ? mb_strtoupper(trim($data['lastname'])) : null;
        $data['firstname'] = isset($data['firstname']) ? trim($data['firstname']) : null;
        $data['pseudo'] = isset($data['pseudo']) ? trim($data['pseudo']) : null;
        $data['company'] = isset($data['company']) ? trim($data['company']) : null;
        $data['email'] = isset($data['email']) ? trim($data['email']) : null;

        return $data;
    }
}
