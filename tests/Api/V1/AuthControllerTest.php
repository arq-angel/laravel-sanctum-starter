<?php

namespace Tests\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that a user can log in with valid credentials and a verified email.
     */
    public function test_user_can_login_with_valid_credentials_and_verified_email()
    {
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
            'deviceName' => 'Postman',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'isSuccess',
                'message',
                'data' => [
                    'accessToken',
                    'refreshToken',
                    'expiresIn',
                    'deviceName',
                ],
            ]);
    }

    /**
     * Test that a user cannot log in without a verified email.
     */
    public function test_user_cannot_login_without_verified_email()
    {
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password123'),
            'email_verified_at' => null, // Email not verified
            'is_verified' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
            'deviceName' => 'Postman',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'isSuccess' => false,
                'message' => 'Access denied.',
            ]);
    }

    /**
     * Test that a verified user can log out successfully.
     */
    public function test_verified_user_can_logout()
    {
        $deviceName = 'Test Device';
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);
        $token = $user->createToken($deviceName)->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout', [
                'deviceName' => $deviceName,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'isSuccess' => true,
                'message' => 'Log out successful.',
                'data' => [
                    'deviceName' => $deviceName,
                ]
            ]);
    }

    /**
     * Test that an unverified user cannot log out.
     */
    public function test_unverified_user_cannot_logout()
    {
        $deviceName = 'Test Device';
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => null, // Email not verified
            'is_verified' => false,
        ]);
        $token = $user->createToken($deviceName)->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout', [
                'deviceName' => $deviceName,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'isSuccess' => false,
                'message' => 'Your email address is not verified.',
            ]);
    }

    /**
     * Test that a verified user can refresh their access token with a valid refresh token.
     */
    public function test_verified_user_can_refresh_access_token_with_valid_refresh_token()
    {
        $deviceName = 'Test Device';
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        $refreshTokenValue = Str::random(64);
        $hashedRefreshToken = Hash::make($refreshTokenValue);
        \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token' => $hashedRefreshToken,
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/refresh-token', [
            'refreshToken' => $refreshTokenValue,
            'deviceName' => $deviceName,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'isSuccess',
                'message',
                'data' => [
                    'accessToken',
                    'refreshToken',
                    'expiresIn',
                    'deviceName',
                ],
            ]);
    }

    /**
     * Test that an unverified user cannot refresh their access token.
     */
    public function test_unverified_user_cannot_refresh_access_token()
    {
        $deviceName = 'Test Device';
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => null, // Email not verified
            'is_verified' => false,
        ]);

        $refreshTokenValue = Str::random(64);
        $hashedRefreshToken = Hash::make($refreshTokenValue);
        \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token' => $hashedRefreshToken,
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/refresh-token', [
            'refreshToken' => $refreshTokenValue,
            'deviceName' => $deviceName,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'isSuccess' => false,
                'message' => 'Access denied.',
            ]);
    }
}
