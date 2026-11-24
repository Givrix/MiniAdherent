<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authenticated_users_can_update_or_delete_members(): void
    {
        $member = Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->putJson("/api/members/{$member->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/members/{$member->id}")->assertUnauthorized();
    }

    public function test_guests_can_create_a_member_without_subscription_dates(): void
    {
        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'invite@exemple.org',
        ])->assertCreated()
            ->assertJsonPath('firstname', 'J**n')
            ->assertJsonPath('email', 'i****e@e*****e.org');

        $member = Member::query()->where('email', 'invite@exemple.org')->first();
        $this->assertNotNull($member);
        $this->assertNull($member->subscription_date);
        $this->assertNull($member->subscription_end);
    }

    public function test_guests_only_see_masked_results(): void
    {
        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'pseudo' => 'jojo', 'email' => 'jean@exemple.org', 'subscription_date' => '2026-05-01', 'subscription_end' => '2027-05-01']);

        $this->getJson('/api/members?lastname=dupont')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.firstname', 'J**n')
            ->assertJsonPath('0.lastname', 'D****T')
            ->assertJsonPath('0.pseudo', 'j**o')
            ->assertJsonPath('0.email', 'j**n@e*****e.org')
            ->assertJsonPath('0.subscription_end', '2027-05-01');

        $this->getJson('/api/members')->assertOk()->assertJsonCount(0);

        $member = Member::query()->where('lastname', 'DUPONT')->first();
        $this->getJson("/api/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('email', 'j**n@e*****e.org');
    }

    public function test_the_self_registration_view_of_a_connected_user_only_sees_masked_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'pseudo' => 'jojo', 'email' => 'jean@exemple.org', 'subscription_date' => '2026-05-01', 'subscription_end' => '2027-05-01']);

        $this->getJson('/api/members?lastname=dupont&mode=self')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.firstname', 'J**n')
            ->assertJsonPath('0.email', 'j**n@e*****e.org');

        $this->getJson('/api/members?mode=self')->assertOk()->assertJsonCount(0);

        $member = Member::query()->where('lastname', 'DUPONT')->first();
        $this->getJson("/api/members/{$member->id}?mode=self")
            ->assertOk()
            ->assertJsonPath('email', 'j**n@e*****e.org');

        $this->getJson('/api/members?lastname=dupont')
            ->assertOk()
            ->assertJsonPath('0.firstname', 'Jean');
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

    public function test_email_is_optional(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-03-15',
        ])->assertCreated()
            ->assertJsonPath('email', null);
    }

    public function test_created_at_matches_the_subscription_date_on_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'pseudo' => 'blendax',
            'email' => 'blendax@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertCreated();

        $member = Member::latest('id')->first();
        $this->assertSame('2026-03-15 00:00:00', $member->created_at->format('Y-m-d H:i:s'));
    }

    public function test_it_blocks_creation_when_a_pseudo_only_identity_already_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'pseudo' => 'blendax', 'email' => 'blendax@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'pseudo' => 'Blendax',
            'email' => 'autre@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['pseudo'])
            ->assertJsonFragment(['Membre déjà existant (pseudo).']);

        $this->assertDatabaseCount('members', 1);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'pseudo' => 'Blendax',
            'subscription_date' => '2026-03-15',
        ])->assertCreated();
    }

    public function test_it_blocks_creation_when_the_email_already_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'email' => 'jean@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Autre',
            'lastname' => 'Personne',
            'email' => 'Jean@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['email'])
            ->assertJsonFragment(['Membre déjà existant (email).']);

        $this->assertDatabaseCount('members', 1);
    }

    public function test_update_ignores_the_current_member_for_duplicate_checks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'email' => 'jean@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->putJson("/api/members/{$member->id}", [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'DUPONT',
            'email' => 'jean@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertOk();

        Member::create(['morphy' => 'phy', 'firstname' => 'Marie', 'lastname' => 'MARTIN', 'email' => 'marie@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->putJson("/api/members/{$member->id}", [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'DUPONT',
            'email' => 'marie@exemple.org',
            'subscription_date' => '2026-03-15',
        ])->assertJsonValidationErrors(['email']);
    }

    public function test_the_exists_endpoint_reports_duplicates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = Member::create(['morphy' => 'phy', 'pseudo' => 'blendax', 'email' => 'blendax@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->getJson('/api/members/exists?pseudo=Blendax&email=blendax@exemple.org')
            ->assertOk()
            ->assertJsonPath('pseudo_exists', true)
            ->assertJsonPath('email_exists', true);

        $this->getJson("/api/members/exists?pseudo=blendax&email=blendax@exemple.org&ignore={$member->id}")
            ->assertOk()
            ->assertJsonPath('pseudo_exists', false)
            ->assertJsonPath('email_exists', false);
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

    public function test_empty_search_lists_members_without_a_subscription_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'En', 'lastname' => 'ATTENTE', 'email' => 'en@exemple.org']);
        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.lastname', 'ATTENTE');
    }

    public function test_member_creation_has_an_independent_rate_limit_from_searches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/api/members')->assertOk();
        }

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-03-15',
        ])->assertCreated();
    }

    public function test_search_is_limited_to_five_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 1; $i <= 6; $i++) {
            Member::create(['morphy' => 'phy', 'firstname' => "S{$i}", 'lastname' => 'HOMONYME', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);
        }

        $this->getJson('/api/members?lastname=homonyme')
            ->assertOk()
            ->assertJsonCount(5);
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
