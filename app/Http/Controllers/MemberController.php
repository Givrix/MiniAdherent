<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\SubscriptionPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->limit(10);

        foreach (['firstname', 'lastname', 'company', 'email'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '') {
                $query->where($field, 'like', "%{$value}%");
            }
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if (! empty($data['subscription_date'])) {
            $data['subscription_end'] = SubscriptionPeriod::computeEnd($data['subscription_date']);
        }

        $member = Member::create($data);

        return response()->json($member, 201);
    }

    public function show(Member $member): JsonResponse
    {
        return response()->json($member);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $data = $this->validated($request);

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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'morphy' => ['required', 'in:phy,mor'],
            'firstname' => ['nullable', 'string', 'max:190', 'required_if:morphy,phy'],
            'lastname' => ['nullable', 'string', 'max:190', 'required_if:morphy,phy'],
            'company' => ['nullable', 'string', 'max:190', 'required_if:morphy,mor'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'subscription_date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'morphy.required' => 'La nature de l’adhérent est obligatoire.',
            'morphy.in' => 'La nature de l’adhérent est invalide.',
            'firstname.required_if' => 'Le prénom est obligatoire pour un adhérent physique.',
            'lastname.required_if' => 'Le nom est obligatoire pour un adhérent physique.',
            'company.required_if' => 'La raison sociale est obligatoire pour un adhérent moral.',
            'email.email' => 'L’adresse email est invalide.',
            'subscription_date.date' => 'La date d’adhésion est invalide.',
            'subscription_date.before_or_equal' => 'La date d’adhésion ne peut pas être dans le futur.',
        ]);

        $data['lastname'] = isset($data['lastname']) ? mb_strtoupper(trim($data['lastname'])) : null;
        $data['firstname'] = isset($data['firstname']) ? trim($data['firstname']) : null;
        $data['company'] = isset($data['company']) ? trim($data['company']) : null;
        $data['email'] = isset($data['email']) ? trim($data['email']) : null;

        return $data;
    }
}
