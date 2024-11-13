<?php

namespace App\Http\Controllers\Api\V1;

use App\Exception\Api\V1\ImageUploadFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Traits\Api\V1\AuthTraits\TokenRevokeTrait;
use App\Traits\Api\V1\CRUDTraits\RegistrationTrait;
use App\Traits\Api\V1\ResponseTrait;
use App\Traits\Api\V1\AuthTraits\RetrieveUserTrait;
use App\Traits\Api\V1\CRUDTraits\ImageUploadTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function Illuminate\Events\queueable;

class UserController extends Controller
{
    use ImageUploadTrait, ResponseTrait, RetrieveUserTrait, RegistrationTrait, TokenRevokeTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            // Step 1: Retrieve the authenticated user
            $user = $this->getAuthUserFromSanctum();

            // Step 2: Prepare success response
            $response = $this->successResponse(
                message: 'User information retrieved successfully.',
                data: [
                    'user' => new UserResource($user),
                ]
            );

            // Step 3: Mark operation as successful
            $success = true;
        } catch (Throwable $throwable) {
            // Step 4: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred while retrieving user information!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 5: Log the login attempt
            Log::info('Fetch user information attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 6: Return response
        return $response;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            // Step 1: Get the validated user data
            $validated = $request->validated();

            // Step 2: Store the validated user data
            $user = $this->storeUserProfile(validated: $validated, request: $request);

            // Step 3: Send a custom message if the $user has to be verified
            if ($this->isEmailVerificationRequired($user)) {
                $message = 'User created successfully! Please verify email to login.';
            }

            // Step 4: Prepare success response
            $response = $this->successResponse(
                message: $message ?? 'User created successfully.',
                data: [
                    'user' => new UserResource($user),
                ]
            );

            // Step 5: Mark operation as successful
            $success = true;
        } catch (Throwable $throwable) {
            // Step 6: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred while creating user!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 7: Log the login attempt
            Log::info('Create user attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 8: Return response
        return $response;
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // using index to return the data instead of this method, also in except method in route in routes/api
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $success = false;
        $response = null;

        try {
            // Step 1: Get the validated user data
            $validated = $request->validated();

            // Step 2: Update the validated user data
            $user = $this->updateUserProfile(validated: $validated, user: $user, request: $request);

            // Step 3: Prepare success response
            $response = $this->successResponse(
                message: 'User created successfully.',
                data: [
                    'user' => new UserResource($user),
                ]
            );

            // Step 4: Mark operation as successful
            $success = true;

        } catch (Throwable $throwable) {
            // Step 5: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred while creating user!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Update user attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 8: Return response
        return $response;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $success = false;
        $response = null;

        try {
            // Step 1: Delete the stored image
            if ($user->image) {
                $this->deleteImage($user->image);
            }

            // Step 2: Revoke all refresh and access tokens
            // Refresh tokens are cascade on delete but the access tokens are not so i am manually deleting all tokens here for safety
            $this->revokeAllTokensForUser($user);

            // Step 3: Delete the user record
            $user->delete();

            // Step 4: Create success response
            $response = $this->successResponse(
                message: 'User deleted successfully.',
            );

        } catch (Throwable $throwable) {
            // Step 5: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred while retrieving user information!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Delete user attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 7: Return response
        return $response;
    }

    private function storeUserProfile(array $validated, $request): User
    {
        return DB::transaction(function () use ($validated, $request) {
            // Step 1: Create a new user object
            $user = new User();

            // Step 2: Handle image upload if applicable
            if ($request && $request->hasFile('image')) {
                $imagePath = $this->updateImage($request, 'image', 'uploads', $user->image ?? null);
                if (!$imagePath) {
                    throw new ImageUploadFailedException("Image upload failed");
                }
                $user->image = $imagePath;
            }

            // Step 3: Fill user attributes
            $user->first_name = $validated['first_name'] ?? null;
            $user->last_name = $validated['last_name'] ?? null;
            $user->email = $validated['email'] ?? null;
            $user->password = $validated['password'] ?? null;
            $user->phone = $validated['phone'] ?? null;
            $user->date_of_birth = $validated['date_of_birth'] ?? null;
            $user->address = $validated['address'] ?? null;
            $user->suburb = $validated['suburb'] ?? null;
            $user->state = $validated['state'] ?? null;
            $user->post_code = $validated['post_code'] ?? null;
            $user->country = $validated['country'] ?? null;

            // Step 4: Save the user
            $user->save();

            // Step 5: Send email verification if it is required
            if ($this->isEmailVerificationRequired($user)) {
                $user->sendEmailVerificationNotification();
            }

            // Step 6: Create remember token for $user
            $this->saveRememberToken($user);

            // Step : Return the saved user
            return $user;
        });
    }

    private function updateUserProfile(array $validated, $user, $request)
    {
        return DB::transaction(function () use ($validated, $user, $request) {
            // Handle image upload
            if ($validated['image'] ?? null) {
                $imagePath = $this->updateImage($request, 'image', 'uploads', $user->image);

                if (!$imagePath) {
                    throw new ImageUploadFailedException("Image upload failed"); // This will be caught and handled by try catch block in update method
                }

                $user->image = $imagePath;
            }

            // Verbose implementation - more control by my choice
            $user->first_name = $validated['first_name'] ?? $user->first_name;
            $user->last_name = $validated['last_name'] ?? $user->last_name;
            $user->email = $validated['email'] ?? $user->email;
            $user->phone = $validated['phone'] ?? $user->phone;
            $user->date_of_birth = $validated['date_of_birth'] ?? $user->date_of_birth;
            $user->address = $validated['address'] ?? $user->address;
            $user->suburb = $validated['suburb'] ?? $user->suburb;
            $user->state = $validated['state'] ?? $user->state;
            $user->post_code = $validated['post_code'] ?? $user->post_code;
            $user->country = $validated['country'] ?? $user->country;

            // Save the updated user
            $user->update();

            return $user; // Return the updated user
        });
    }

    private function saveRememberToken(User $user): void
    {
        $user->remember_token = Str::random(10);
        $user->save();
    }

}

