<?php

namespace App\Traits\Api\V1\AuthTraits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

trait ValidateUserTrait
{
    use RetrieveUserTrait;
    /**
     * @param string $email
     * @param string $password
     * @return User
     */
    public function getCredentialValidatedUser(string $email, string $password): user
    {
        // Fetch the user by email
        $user = $this->getUserByEmail(email: $email);

        // Validate the user and credentials
        return $this->validateUser($user, $password);
    }

    /**
     * @param $user
     * @param string $password
     * @return User
     */
    public function validateUser($user, string $password): User
    {
        // validate user exists
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account found with the provided email address.'],
            ]);
        }

        // Validate user credentials
        $validation = $this->validateUserCredentials($user, $password);

        // Handle failed credential validation
        if (!$validation['isSuccess']) {
            throw ValidationException::withMessages([
                'email' => $validation['message'],
            ]);
        }

        return $user; // Return the validated user object
    }

    /**
     * @param User $user
     * @param string $password
     * @return array|true[]
     */
    protected function validateUserCredentials(User $user, string $password): array
    {
        // Step 1: Validate the password
        $passwordValidation = $this->validatePassword($user, $password);

        if (!$passwordValidation['isSuccess']) {
            return [
                'isSuccess' => false,
                'message' => $passwordValidation['message'], // Generic message key
            ];
        }

        // Step 2: Add future validations (e.g., IP address validation)
        // Example: $ipValidation = $this->validateIPAddress($user, $currentIp);

        return [
            'isSuccess' => true,
        ];
    }

    /**
     * @param User $user
     * @param string $password
     * @return array
     */
    protected function validatePassword(User $user, string $password): array
    {
        if (!Hash::check($password, $user->password)) {
            return [
                'isSuccess' => false,
                'message' => ['The provided credentials are incorrect.'],
            ];
        }

        return [
            'isSuccess' => true,
            'message' => ['Password validated successfully.'],
        ];
    }

}
