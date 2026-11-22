<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
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
}
