<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\RefreshToken;
use App\Models\User;
use App\Traits\ApiControllerTraits;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;


class AuthController extends Controller
{

    use ApiControllerTraits;

    private array $returnMessage = [
        'isSuccess' => false,
        'message' => 'An error occurred',
        'data' => [],
    ];

    private int $returnMessageStatus = Response::HTTP_BAD_REQUEST;

    public function login(LoginRequest $request)
    {
        try {
            // Validate credentials
            $user = User::where('email', $request->email)->first();
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Generate Access Token
            $token = $user->createToken($request->deviceName);

            // Set token expiration (e.g., 60 minutes from now)
            $token->accessToken->expires_at = now()->addSeconds($this->getAccessTokenExpiryTime());
            $token->accessToken->save();

            // Generate Refresh Token
            $refreshToken = Str::random(64);
            $refreshTokenExpiry = Carbon::now()->addDays(30); // Refresh token expires in 30 days

            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshToken),
                'expires_at' => $refreshTokenExpiry,
            ]);

            $this->returnMessage = [
                'isSuccess' => true,
                'message' => 'Token Generated.',
                'data' => [
                    'accessToken' => $token->plainTextToken,
                    'refreshToken' => $refreshToken,
                    'expiresIn' => $this->getAccessTokenExpiryTime(), // 1 hour in seconds
                ]
            ];
            $this->returnMessageStatus = Response::HTTP_OK;

        } catch (ValidationException $validationException) {
            // Handle validation exceptions
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $validationException->errors(),
            ];
            $this->returnMessageStatus = Response::HTTP_UNPROCESSABLE_ENTITY;

        } catch (\Throwable $throwable) {
            $this->returnMessage = [
                'success' => false,
                'message' => 'Error occurred during authentication!',
                'data' => []
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Delete the current access token
            $request->user()->currentAccessToken()->delete();

            // Delete refresh tokens from the database
            RefreshToken::where('user_id', $request->user()->id)->delete();

            $this->returnMessage = [
                'isSuccess' => true,
                'message' => 'Logged out successfully.',
                'data' => []
            ];
            $this->returnMessageStatus = Response::HTTP_OK;

        } catch (\Throwable $throwable) {
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => 'An error occurred during logout!',
                'data' => []
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
    }

    public function logOutFromAll(Request $request)
    {
        try {
            // Fetch the authenticated user using request()->user() (Sanctum should handle this automatically)
            $employee = $request->user();

            // Ensure that we have an authenticated employee
            if ($employee) {
                // Delete all tokens associated with the employee (logs out from all sessions)
                $employee->tokens()->delete();

                // Delete refresh tokens from the database
                RefreshToken::where('user_id', $request->user()->id)->delete();

                // Success message
                $this->returnMessage = [
                    'success' => true,
                    'message' => "Logged out from all sessions successfully.",
                    'data' => []
                ];
                $this->returnMessageStatus = Response::HTTP_OK;
            } else {
                // Throw an AuthenticationException for unauthenticated users
                throw new AuthenticationException('No user is currently authenticated.');
            }

        } catch (AuthenticationException $authenticationException) {
            // Handle the custom AuthenticationException
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => $authenticationException->getMessage(),
                'data' => []
            ];
            $this->returnMessageStatus = Response::HTTP_UNAUTHORIZED;
        } catch (\Throwable $throwable) {
            // Handle any other exceptions
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => 'An error occurred during logout from all sessions!',
                'data' => []
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $hashedToken = hash('sha256', $request->refreshToken);

            $refreshToken = RefreshToken::where('token', $hashedToken)->first();

            if (!$refreshToken || $refreshToken->expires_at < Carbon::now()) {
                throw new AuthenticationException('Invalid or expired refresh token.');
            }

            // Generate new Access Token
            $user = $refreshToken->user;
            $newAccessToken = $user->createToken($request->deviceName);

            // Set token expiration (e.g., 60 minutes from now)
            $newAccessToken->accessToken->expires_at = now()->addSeconds($this->getAccessTokenExpiryTime());
            $newAccessToken->accessToken->save();

            $this->returnMessage = [
                'isSuccess' => true,
                'message' => 'Token Generated.',
                'data' => [
                    'accessToken' => $newAccessToken->plainTextToken,
                    'refreshToken' => $request->refreshToken,
                    'expiresIn' => $this->getAccessTokenExpiryTime(), // 1 hour in seconds
                ]
            ];
            $this->returnMessageStatus = Response::HTTP_OK;

        } catch (AuthenticationException $authException) {
            // Handle invalid or expired refresh token
            $this->returnMessage = [
                'isSuccess' => false,
                'message' => $authException->getMessage(),
                'data' => []
            ];
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;

        } catch (\Throwable $throwable) {
            $this->returnMessage = [
                'success' => false,
                'message' => 'Error occurred during authentication!',
                'data' => []
            ];
            if ($this->debuggable() && $this->isLocalEnvironment()) {
                $this->returnMessage['debug'] = $throwable->getMessage();
            }
            $this->returnMessageStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response()->json($this->returnMessage, $this->returnMessageStatus);
        }
    }

}
