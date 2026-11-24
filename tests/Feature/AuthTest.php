<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_public_registration_page_and_admin_pages_stay_private(): void
    {
        $this->get('/')->assertOk()->assertSee('data-public="public"', false);
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/members/export')->assertRedirect('/login');
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@exemple.org',
            'password' => bcrypt('M1niAdherent!'),
        ]);

        $this->post('/login', ['email' => 'admin@exemple.org', 'password' => 'M1niAdherent!'])
            ->assertRedirect('/');

        $this->actingAs($user)->get('/')->assertOk();

        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@exemple.org',
            'password' => bcrypt('M1niAdherent!'),
        ]);

        $this->from('/login')
            ->post('/login', ['email' => 'admin@exemple.org', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_connected_user_can_switch_to_the_self_registration_view(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('data-public="auth"', false);
        $this->actingAs($user)->get('/?mode=self')->assertOk()->assertSee('data-public="public"', false);
        $this->get('/?mode=self')->assertOk()->assertSee('data-public="public"', false);
    }
}
