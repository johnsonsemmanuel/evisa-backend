<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'role',
                    'permissions'
                ],
                'token'
            ]);
    }

    /** @test */
    public function it_rejects_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /** @test */
    public function it_requires_email_and_password()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function it_can_register_new_user()
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone' => '+233123456789',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'role'
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
    }

    /** @test */
    public function it_validates_registration_data()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name', 
                'email',
                'password'
            ]);
    }

    /** @test */
    public function it_rejects_duplicate_email_registration()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'phone' => '+233123456789',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_can_logout_user()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    /** @test */
    public function it_gets_authenticated_user_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'role',
                    'permissions'
                ]
            ]);
    }

    /** @test */
    public function it_rejects_unauthenticated_profile_request()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
