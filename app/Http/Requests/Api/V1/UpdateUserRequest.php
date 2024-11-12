<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Use the Sanctum guard to get the authenticated user
        $authenticatedUser = Auth::guard('sanctum')->user();

        // Get the user ID to be updated from the route or request
        /**
         * When this throws an error because of laravel trying to resolve
         * route parameter into model instance of an id that doesn't exist,
         * laravel triggers ModelNotFoundException which has
         * NotFoundHttpException which gets triggered which is custom handled in bootstrap/app
         */
        $userIdToBeUpdated = $this->route('user'); // Here, 'user' has to match the route parameter e.g. ...api/v1/user/{user}

        // Check if the authenticated user matches the user being updated
        return $authenticatedUser && $authenticatedUser->id === $userIdToBeUpdated->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'image', 'max: 2048'],
            'first_name' => ['required', 'string', 'max: 100', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'max: 100', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => ['required', 'string', 'email', 'max: 248', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'password' => ['required', 'string', 'min: 8', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/', 'confirmed'],
            'phone' => ['nullable', 'string', 'max: 20', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique('users', 'phone')->ignore($this->user()->id)],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'suburb' => ['nullable', 'string', 'max: 248'],
            'state' => [
                'sometimes',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    // Retrieve the value of 'country' from the request data
                    $country = request('country');

                    // Use getStateItems function to get the valid states for the given country
                    if ($country && !in_array($value, getStates($country))) {
                        $fail("The $attribute is invalid for the selected country.");
                    }
                }
            ],
            'post_code' => ['nullable', 'string', 'max: 20'],
            'country' => ['nullable', 'string', 'max: 100'],
        ];
    }

    /**
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->firstName) {
            $this->merge([
                'first_name' => $this->firstName,
            ]);
        }

        if ($this->middleName) {
            $this->merge([
                'middle_name' => $this->middleName,
            ]);
        }

        if ($this->lastName) {
            $this->merge([
                'last_name' => $this->lastName,
            ]);
        }

        if ($this->dateOfBirth) {
            $this->merge([
                'date_of_birth' => $this->dateOfBirth,
            ]);
        }

        if ($this->postCode) {
            $this->merge([
                'post_code' => $this->postCode,
            ]);
        }

        // we also have more dynamic wa of doing the above actions
        /*$fields = [
            'firstName' => 'first_name',
            'middleName' => 'middle_name',
            'lastName' => 'last_name',
            'dateOfBirth' => 'date_of_birth',
            'postCode' => 'post_code',
        ];

        foreach ($fields as $inputKey => $dbKey) {
            if ($this->$inputKey) {
                $this->merge([$dbKey => $this->$inputKey]);
            }
        }*/
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'Names should not contain numbers or special characters',
            'last_name.regex' => 'Names should not contain numbers or special characters',
            'password.regex' => 'The password must contain at least one uppercase letter, one number, and one special character.',
            'phone.regex' => 'The phone number must be a valid phone number.',
            // add more custom validation error messages
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'date_of_birth' => 'Date of Birth',
            'post_code' => 'Post Code',
        ];
    }

    /**
     * Get the data to be validated from the request.
     *
     * @return array
     */
    public function validationData(): array
    {
        $data = parent::validationData();
        $data['phone'] = str_replace(['-', ' '], '', $data['phone'] ?? '');
        return $data;
    }

    /**
     * Handle logic after validation passes.
     *
     * @return void
     */
    protected function passedValidation()
    {
        // Example: Normalize case for email
        $this->merge([
            'email' => strtolower($this->email),
        ]);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();

        // Customize the error response format
        $response = response()->json([
            'isSuccess' => false,
            'message' => 'Validation errors occurred.',
            'errors' => $errors->messages(),
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'isSuccess' => false,
            'message' => 'You are not authorized to make this request.',
        ], 403));
    }
}
