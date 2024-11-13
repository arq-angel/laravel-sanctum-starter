<?php

namespace Tests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiEmailVerificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_send_verification_email_successfully()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'is_verified' => false
        ]);

        $payload = [
            'email' => $user->email,
        ];

        $response = $this->postJson(route('api.verification.send'), $payload);

        $response->assertOk()
            ->assertJson([
                'message' => 'Verification link has been sent to your email',
            ]);
    }

    public function test_cannot_send_verification_email_if_already_verified()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_verified' => true
        ]);

        $payload = [
            'email' => $user->email,
        ];

        $response = $this->postJson(route('api.verification.send'), $payload);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email already verified',
            ]);
    }

    public function test_cannot_send_verification_email_to_non_existent_user()
    {
        $payload = [
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson(route('api.verification.send'), $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'email',
                ],
            ]);
    }

    public function test_can_verify_email_successfully()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'is_verified' => false
        ]);

        $hash = sha1($user->getEmailForVerification());
        $payload = [
            'id' => $user->id,
            'hash' => $hash,
        ];

        $response = $this->postJson(route('api.verification.verify'), $payload);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email has been verified',
            ]);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_cannot_verify_email_with_invalid_hash()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'is_verified' => false
        ]);

        $payload = [
            'id' => $user->id,
            'hash' => 'invalid_hash',
        ];

        $response = $this->postJson(route('api.verification.verify'), $payload);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid verification token',
            ]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_cannot_verify_email_for_non_existent_user()
    {
        $payload = [
            'id' => 9999, // Non-existent user ID
            'hash' => sha1('nonexistent@example.com'),
        ];

        $response = $this->postJson(route('api.verification.verify'), $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => [
                    'id',
                ],
            ]);
    }

    public function test_cannot_verify_already_verified_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        $hash = sha1($user->getEmailForVerification());
        $payload = [
            'id' => $user->id,
            'hash' => $hash,
        ];

        $response = $this->postJson(route('api.verification.verify'), $payload);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email has been verified',
            ]);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

}
