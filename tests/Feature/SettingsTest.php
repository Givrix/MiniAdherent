<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->post('/settings', [])->assertRedirect('/login');
        $this->get('/members/export')->assertRedirect('/login');
        $this->post('/members/import', [])->assertRedirect('/login');
    }

    public function test_admin_can_switch_between_subscription_modes(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings')->assertOk()->assertSee('Période fixe annuelle');

        $this->post('/settings', ['subscription_mode' => 'fixed'])->assertRedirect('/settings');
    }

    public function test_fixed_mode_ends_the_membership_on_the_period_end_depending_on_the_switch_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/settings', [
            'subscription_mode' => 'fixed',
            'fixed_period_end' => '31/07',
            'fixed_switch_date' => '01/06',
        ]);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-05-31',
        ])->assertCreated()
            ->assertJsonPath('subscription_end', '2026-07-31');

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Marie',
            'lastname' => 'Martin',
            'subscription_date' => '2026-06-01',
        ])->assertJsonPath('subscription_end', '2027-07-31');

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Pierre',
            'lastname' => 'Rossi',
            'subscription_date' => '2026-09-02',
        ])->assertJsonPath('subscription_end', '2027-07-31');

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Claire',
            'lastname' => 'Noel',
            'subscription_date' => '2026-03-15',
        ])->assertJsonPath('subscription_end', '2026-07-31');
    }

    public function test_fixed_mode_dates_are_configurable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/settings', [
            'subscription_mode' => 'fixed',
            'fixed_period_end' => '31/08',
            'fixed_switch_date' => '15/06',
        ]);

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-06-15',
        ])->assertJsonPath('subscription_end', '2027-08-31');

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Marie',
            'lastname' => 'Martin',
            'subscription_date' => '2026-06-14',
        ])->assertJsonPath('subscription_end', '2026-08-31');
    }

    public function test_fixed_mode_dates_are_validated(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/settings', [
            'subscription_mode' => 'fixed',
            'fixed_period_end' => '32/13',
        ])->assertSessionHasErrors(['fixed_period_end']);
    }

    public function test_fixed_mode_removal_restores_floating_behavior(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/settings', ['subscription_mode' => 'fixed']);
        app('session.store')->flush();
        $this->post('/settings', ['subscription_mode' => 'floating']);
        app('session.store')->flush();

        $this->postJson('/api/members', [
            'morphy' => 'phy',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'subscription_date' => '2026-03-15',
        ])->assertJsonPath('subscription_end', '2027-03-15');
    }

    public function test_export_returns_all_members_as_csv(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'email' => 'jean@exemple.org', 'subscription_date' => '2026-03-15', 'subscription_end' => '2027-03-14']);

        $response = $this->get('/members/export');
        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('morphy;firstname;lastname;company;email;subscription_date;subscription_end', $content);
        $this->assertStringContainsString('phy;Jean;DUPONT;;jean@exemple.org;2026-03-15;2027-03-14', $content);
    }

    public function test_import_creates_updates_and_reports_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Member::create(['morphy' => 'phy', 'firstname' => 'Jean', 'lastname' => 'DUPONT', 'email' => 'jean@exemple.org']);

        $csv = implode("\n", [
            'morphy;firstname;lastname;company;email;subscription_date',
            'phy;Jeanne;DUPONT;;jean@exemple.org;2026-03-15',
            'phy;Alice;WILSON;;alice@exemple.org;2026-05-01',
            'mor;;;L electro;contact@lelectro.fr;',
            'phy;Bob;;bob@exemple.org;2026-05-01',
            'phy;Claire;NOEL;;pas-un-email;2026-05-01',
        ]);

        $response = $this->post('/members/import', [
            'file' => UploadedFile::fake()->createWithContent('adherents.csv', $csv),
        ])->assertRedirect('/settings');

        $response->assertSessionHas('import_created', 2);
        $response->assertSessionHas('import_updated', 1);
        $response->assertSessionHas('import_errors.0', 'Ligne 5 : prénom et nom requis pour une personne physique.');

        $this->assertDatabaseHas('members', ['email' => 'jean@exemple.org', 'firstname' => 'JEANNE', 'subscription_end' => '2027-03-15']);
        $this->assertDatabaseHas('members', ['email' => 'contact@lelectro.fr', 'morphy' => 'mor', 'company' => 'L electro']);
        $this->assertDatabaseMissing('members', ['email' => 'pas-un-email']);
    }
}
