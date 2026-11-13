<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function test_legacy_2a_password_authenticates_and_rehashes(): void
    {
        $this->assertLegacyBcryptLogin('2a');
    }

    public function test_legacy_2b_password_authenticates_and_rehashes(): void
    {
        $this->assertLegacyBcryptLogin('2b');
    }

    private function assertLegacyBcryptLogin(string $prefix): void
    {
        $hash = substr_replace(password_hash('synthetic-password', PASSWORD_BCRYPT, ['cost' => 4]), $prefix, 1, 2);
        DB::table('users')->insert(['id' => '10000000-0000-4000-8000-000000000001', 'email' => 'legacy@example.test', 'password' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(config('hashing.bcrypt.verify'));
        $this->assertFalse(Auth::attempt(['email' => 'legacy@example.test', 'password' => 'wrong-password']));
        $this->assertSame($hash, DB::table('users')->value('password'));
        $this->assertTrue(Auth::attempt(['email' => 'legacy@example.test', 'password' => 'synthetic-password']));
        $rehash = DB::table('users')->value('password');
        $this->assertStringStartsWith('$2y$', $rehash);
        $this->assertTrue(password_verify('synthetic-password', $rehash));
    }

    public function test_unsupported_algorithms_and_malformed_legacy_bcrypt_remain_rejected(): void
    {
        $this->assertSame('unknown', Hash::info('$2a$03$'.str_repeat('a', 53))['algoName']);
        $this->assertSame('unknown', Hash::info('$2b$04$'.str_repeat('a', 52))['algoName']);
        $this->assertTrue(config('hashing.bcrypt.verify'));
        $this->expectException(\RuntimeException::class);
        Hash::check('synthetic-password', password_hash('synthetic-password', PASSWORD_ARGON2ID));
    }
}
