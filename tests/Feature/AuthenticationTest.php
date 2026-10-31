<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::forgetGuards();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->delete('/settings/clear-all')->assertRedirect('/login');
    }

    public function test_login_redirects_to_setup_while_no_user_exists(): void
    {
        $this->get('/login')->assertRedirect('/setup');
    }

    public function test_setup_creates_user_and_logs_in(): void
    {
        $this->get('/setup')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Setup', false));

        $this->post('/setup', [
            'name' => 'Tobias',
            'email' => 'tobias@example.com',
            'password' => 'geheim123',
            'password_confirmation' => 'geheim123',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('geheim123', User::sole()->password));
    }

    public function test_setup_is_closed_once_a_user_exists(): void
    {
        User::factory()->create();

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', [
            'name' => 'Angreifer',
            'email' => 'attacker@example.com',
            'password' => 'geheim123',
            'password_confirmation' => 'geheim123',
        ])->assertForbidden();

        $this->assertSame(1, User::count());
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'tobias@example.com']);

        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login', false));

        $this->post('/login', ['email' => 'tobias@example.com', 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'tobias@example.com']);

        $this->post('/login', ['email' => 'tobias@example.com', 'password' => 'falsch'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        User::factory()->create(['email' => 'tobias@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => 'tobias@example.com', 'password' => 'falsch']);
        }

        $this->post('/login', ['email' => 'tobias@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Zu viele Anmeldeversuche. Bitte in 60 Sekunden erneut versuchen.']);
        $this->assertGuest();
    }

    public function test_password_can_be_changed_with_current_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put('/settings/password', [
            'current_password' => 'falsch',
            'password' => 'neuesPasswort',
            'password_confirmation' => 'neuesPasswort',
        ])->assertSessionHasErrors('current_password');

        $this->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'neuesPasswort',
            'password_confirmation' => 'neuesPasswort',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('neuesPasswort', $user->fresh()->password));
    }

    public function test_migration_removes_seeded_default_admin_only_with_default_password(): void
    {
        $migration = require database_path('migrations/2026_10_30_120326_remove_seeded_default_admin_user.php');

        $user = User::factory()->create(['email' => 'admin@finanzpilot.local', 'password' => 'geändert']);
        $migration->up();
        $this->assertModelExists($user);

        $user->update(['password' => 'finanzpilot']);
        $migration->up();
        $this->assertModelMissing($user);
    }
}
