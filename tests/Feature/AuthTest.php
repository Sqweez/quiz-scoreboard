<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_logout_and_protected_pages(): void
    {
        $this->withoutVite();
        $this->get('/games/new')->assertRedirect('/login');
        $this->postJson('/register', ['email' => 'owner@example.com', 'password' => 'long-password'])->assertCreated();
        $this->assertAuthenticated();
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('long-password', $user->password));
        $this->assertTrue(\Illuminate\Support\Str::isUuid($user->id));
        $this->get('/games/new')->assertOk();
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest();
        $this->postJson('/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/login', ['email' => 'owner@example.com', 'password' => 'long-password'])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validates_email_uniqueness_and_password(): void
    {
        $this->postJson('/register', ['email' => 'invalid', 'password' => 'short'])->assertUnprocessable();
        $this->postJson('/register', ['email' => 'owner@example.com', 'password' => 'long-password'])->assertCreated();
        $this->postJson('/logout');
        $this->postJson('/register', ['email' => 'owner@example.com', 'password' => 'long-password'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 1);
    }
}
