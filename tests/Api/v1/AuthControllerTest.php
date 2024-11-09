<?php

namespace Tests\Api\v1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    /** vendor/bin/phpunit --testsuite=Api */
    /** Use above command to only test this test or php artisan test that will test all test cases */

    use RefreshDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password123'),
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
                ],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'deviceName' => 'Postman',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'isSuccess' => false,
                'message' => 'Validation failed.',
            ]);
    }

    public function test_user_can_logout()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('Test Device')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'isSuccess' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_user_can_logout_from_all_devices()
    {
        $user = \App\Models\User::factory()->create();
        $user->createToken('Device 1');
        $user->createToken('Device 2');

        $token = $user->createToken('Current Device')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout-from-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out from all sessions successfully.',
            ]);
    }

    public function test_user_can_refresh_access_token_with_valid_refresh_token()
    {
        $user = \App\Models\User::factory()->create();

        $refreshTokenValue = Str::random(64);
        \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $refreshTokenValue),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/refresh-token', [
            'refreshToken' => $refreshTokenValue,
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
                ],
            ]);
    }

    public function test_user_cannot_refresh_token_with_invalid_refresh_token()
    {
        $response = $this->postJson('/api/v1/refresh-token', [
            'refreshToken' => 'invalid-refresh-token',
            'deviceName' => 'Postman',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'isSuccess' => false,
                'message' => 'Invalid or expired refresh token.',
            ]);
    }



}
