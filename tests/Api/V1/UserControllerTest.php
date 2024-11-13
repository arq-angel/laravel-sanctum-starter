<?php

namespace Tests\Api\V1;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test retrieving user information.
     */
    public function test_can_retrieve_user_information()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson(route('user.index'))
            ->assertOk()
            ->assertJson([
                'isSuccess' => true,
                'message' => 'User information retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'image' => $user->image,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                    ],
                ],
            ]);
    }

    /**
     * Test storing a new user.
     */
    public function test_can_store_user()
    {
        $payload = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => '@Password123',
            'password_confirmation' => '@Password123',
            'phone' => '1234567890',
            'dateOfBirth' => '2000-01-01',
            'address' => '123 Main St',
            'suburb' => 'Sample Suburb',
            'state' => 'New South Wales',
            'postCode' => '12345',
            'country' => 'Australia',
        ];

        $response = $this->postJson(route('user.store'), $payload);

        $user = User::where(['email' => 'john@example.com'])->first();

        $response->assertCreated()
            ->assertJson([
                'isSuccess' => true,
                'message' => "User created successfully! Please verify email to login.",
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'image' => $user->image,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);
    }

    /**
     * Test updating a user.
     */
    public function test_can_update_user()
    {
        $user = User::factory()->create();

        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson(route('user.update', $user), $payload);

        $user = User::where(['email' => 'jane@example.com'])->first();

        $response->assertOk()
            ->assertJson([
                'isSuccess' => true,
                'message' => 'User updated successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'image' => $user->image,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'jane@example.com',
        ]);
    }

    /**
     * Test deleting a user.
     */
    public function test_can_delete_user()
    {
        $deviceName = 'Test Device';
        $user = User::factory()->create();

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => Hash::make(Str::random(64)),
            'deviceName' => $deviceName,
            'expires_at' => now()->addDays(30),
        ]);

        $user->createToken($deviceName);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(route('user.destroy', $user));

        $response->assertOk()
            ->assertJson([
                'isSuccess' => true,
                'message' => 'User deleted successfully.',
                'data' => []
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        // Assert the refresh token is deleted
        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ]);

        // Assert the refresh token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ]);
    }

    /**
     * Test image upload during user creation.
     */
    public function test_can_upload_image_when_storing_user()
    {
        Storage::fake('public');

        $payload = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'password' => '@Password123',
            'password_confirmation' => '@Password123',
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ];

        $response = $this->postJson(route('user.store'), $payload);

        $user = User::where(['email' => 'john@example.com'])->first();

        $response->assertCreated()
            ->assertJson([
                'isSuccess' => true,
                'message' => "User created successfully! Please verify email to login.",
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'image' => $user->image,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                    ]
                ]
            ]);

        Storage::disk('public')->assertExists($user->image);
    }

    /**
     * Test image upload during user update.
     */
    public function test_can_upload_image_when_updating_user()
    {
        // Fake the public storage for testing
        Storage::fake('public');

        // Create a user
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('@Password123'),
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        // Fake an old image file
        $oldImagePath = 'uploads/images/old_avatar.jpg';
        Storage::disk('public')->put($oldImagePath, 'dummy content');
        $user->image = $oldImagePath;
        $user->save();

        // Prepare the payload for the update request
        $payload = [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane@example.com',
            'image' => UploadedFile::fake()->image('new_avatar.jpg'),
        ];

        // Send the update request
        $response = $this->actingAs($user, 'sanctum')
            ->putJson(route('user.update', $user), $payload);

        // Refresh the user instance to get the updated data
        $user->refresh();

        // Assert the response is successful
        $response->assertOk()
            ->assertJson([
                'isSuccess' => true,
                'message' => 'User updated successfully.', // Adjust this if your update message is different
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'image' => $user->image,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                    ]
                ]
            ]);

        // Assert the new image is stored in public disk
        Storage::disk('public')->assertExists($user->image);

        // Assert the old image is deleted
        Storage::disk('public')->assertMissing($oldImagePath);

        // Assert the database contains updated user information
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'image' => $user->image,
        ]);
    }

}
