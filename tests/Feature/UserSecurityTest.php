<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function password_is_hashed_when_creating_user()
    {
        $user = User::factory()->create([
            'password' => 'plaintext-password'
        ]);

        $this->assertNotEquals('plaintext-password', $user->password);
        $this->assertTrue(Hash::check('plaintext-password', $user->password));
    }

    /** @test */
    public function password_is_rehashed_when_changed()
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        $user->update([
            'password' => Hash::make('new-password')
        ]);

        $this->assertNotEquals($oldPasswordHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /** @test */
    public function user_can_change_password_with_correct_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password')
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /** @test */
    public function user_cannot_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password')
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    /** @test */
    public function api_tokens_are_unique()
    {
        $user = User::factory()->create();

        $token1 = $user->createToken('token1')->plainTextToken;
        $token2 = $user->createToken('token2')->plainTextToken;

        $this->assertNotEquals($token1, $token2);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    /** @test */
    public function api_tokens_can_be_revoked()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Verify token works
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response->assertStatus(200);

        // Revoke token
        $user->tokens()->delete();

        // Verify token no longer works
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token
        ]);
        $response->assertStatus(401);
    }
}
