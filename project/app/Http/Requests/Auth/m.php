<?php
// app/Http/Requests/Auth/RegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates user registration input
 * Throws JSON error on failure (not redirect)
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'email:rfc,dns', 'unique:users,email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed',
                           'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            'currency' => ['nullable', 'string', 'in:USD,EUR,GBP,JPY,INR,CAD,AUD,CHF'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'email.unique'   => 'This email address is already registered.',
        ];
    }

    /** Override to return JSON instead of redirect */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
