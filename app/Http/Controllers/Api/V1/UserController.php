<?php

namespace App\Http\Controllers\Api\V1;

use App\Exception\Api\V1\ImageUploadFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Traits\Api\V1\ResponseTraits;
use App\Traits\Api\V1\AuthTraits\RetrieveUserTraits;
use App\Traits\Api\V1\CRUDTraits\ImageUploadTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function Illuminate\Events\queueable;

class UserController extends Controller
{
    use ImageUploadTrait, ResponseTraits, RetrieveUserTraits;

    private array $returnMessage = [
        'isSuccess' => false,
        'message' => 'An error occurred',
        'data' => [],
    ];

    private int $returnMessageStatus = Response::HTTP_BAD_REQUEST;

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
                message: 'An error occurred while retrieving user information!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Create user attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 7: Return response
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
        try {

            $authUser = Auth::guard('sanctum')->user();

            if (!$authUser || $authUser->id !== $user->id) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized access!',
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validated();

            // Perform the update using the new method
            $updatedUser = $this->updateUserProfile($validated, $authUser, $request);

            $this->returnMessage = [
                'isSuccess' => true,
                'message' => 'User updated successfully.',
                'data' => [
                    'user' => new UserResource($updatedUser),
                ]
            ];
            $this->returnMessageStatus = Response::HTTP_OK;

        } catch (\Throwable $throwable) {
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => "An error occurred while updating user!",
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            if ($user->image) {
                $this->deleteImage($user->image);
            }

            // Delete all tokens associated with the employee (logs out from all sessions)
            $user->tokens()->delete();

            // Delete refresh tokens from the database
            RefreshToken::where('user_id', $user->id)->delete();

            // Delete the user record
            $user->delete();

            $this->returnMessage = [
                'isSuccess' => true,
                'message' => 'User deleted successfully.',
            ];
            $this->returnMessageStatus = Response::HTTP_OK;

        } catch (\Throwable $throwable) {
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => "An error occurred while deleting user!",
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
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

            // Step 4: Send email verification


            // Step : Save the user
            $user->save();

            // Step : Return the saved user
            return $user;
        });
    }

    private function updateUserProfile(array $validated, $authUser, $request)
    {
        return DB::transaction(function () use ($validated, $authUser, $request) {
            // Handle image upload
            if ($validated['image'] ?? null) {
                $imagePath = $this->updateImage($request, 'image', 'uploads', $authUser->image);

                if (!$imagePath) {
                    throw new ImageUploadFailedException("Image upload failed"); // This will be caught and handled by try catch block in update method
                }

                $authUser->image = $imagePath;
            }

            // Update other fields
            // $authUser->fill($validated); // uses fillable property in User model to bind the field with its value - cleaner implementation

            // Verbose implementation - more control by my choice
            $authUser->first_name = $validated['first_name'] ?? $authUser->first_name;
            $authUser->last_name = $validated['last_name'] ?? $authUser->last_name;
            $authUser->email = $validated['email'] ?? $authUser->email;
            $authUser->phone = $validated['phone'] ?? $authUser->phone;
            $authUser->date_of_birth = $validated['date_of_birth'] ?? $authUser->date_of_birth;
            $authUser->address = $validated['address'] ?? $authUser->address;
            $authUser->suburb = $validated['suburb'] ?? $authUser->suburb;
            $authUser->state = $validated['state'] ?? $authUser->state;
            $authUser->post_code = $validated['post_code'] ?? $authUser->post_code;
            $authUser->country = $validated['country'] ?? $authUser->country;

            // Save the updated user
            // $authUser->save();
            $authUser->update();

            return $authUser; // Return the updated user
        });
    }

}

