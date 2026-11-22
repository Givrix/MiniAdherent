<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authenticated_users_can_manage_members(): void
    {
        $this->getJson('/api/members')->assertUnauthorized();
        $this->postJson('/api/members', [])->assertUnauthorized();
    }

    public function test_it_creates_a_physical_member_with_a_one_year_subscription(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'jean.dupont@exemple.org',
            'subscription_date' => '2026-03-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('lastname', 'DUPONT')
            ->assertJsonPath('subscription_end', '2027-03-15');

        $this->assertDatabaseHas('members', [
            'firstname' => 'Jean',
            'lastname' => 'DUPONT',
            'email' => 'jean.dupont@exemple.org',
        ]);
        $this->assertSame('2027-03-15', Member::latest('id')->first()->subscription_end->toDateString());
    }

    public function test_it_requires_a_company_for_a_legal_entity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'mor',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['company']);

        $this->postJson('/api/members', [
            'morphy' => 'mor',
            'company' => 'Asso Exemple',
            'email' => 'contact@asso-exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertCreated();
    }

    public function test_it_requires_a_name_and_a_pseudo_or_both_for_a_physical_member(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'email' => 'anonyme@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['firstname']);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'pseudo' => 'freenode42',
            'email' => 'freenode42@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertCreated()
            ->assertJsonPath('pseudo', 'freenode42');
    }

    public function test_it_requires_an_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_it_rejects_future_subscription_dates_and_invalid_emails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'not-an-email',
            'subscription_date' => now()->addDay()->toDateString(),
        ])->assertJsonValidationErrors(['email', 'subscription_date']);
    }

    public function test_search_filters_members_by_the_filled_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);
        Member::create(['morphy' => 'phy', 'firstname' => 'Marie', 'lastname' => 'MARTIN', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);
        Member::create(['morphy' => 'mor', 'company' => 'Asso Exemple', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->getJson('/api/members?lastname=dupont')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.firstname', 'Jean');

        $this->getJson('/api/members?company=asso')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.company', 'Asso Exemple');
    }

    public function test_it_updates_a_member(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->putJson("/api/members/{$member->id}", [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'DUPONT',
            'email' => 'jean.dupont@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertOk()
            ->assertJsonPath('email', 'jean.dupont@exemple.org');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'email' => 'jean.dupont@exemple.org']);
    }

    public function test_renewal_extends_from_the_current_period(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'email' => 'jean@exemple.org', 'subscription_date' => '2025-03-15', 'subscription_end' => '2026-12-31']);

        $this->putJson("/api/members/{$member->id}", [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'DUPONT',
            'email' => 'jean@exemple.org',
            'subscription_date' => '2026-11-01',
            'renewal' => true,
        ])->assertOk()
            ->assertJsonPath('subscription_end', '2027-12-31');
    }

    public function test_it_deletes_a_member(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT']);

        $this->deleteJson("/api/members/{$member->id}")->assertNoContent();

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }
}
