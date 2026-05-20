<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AuthController
 * Handles user registration, login, logout, and profile management
 */
class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * POST /api/auth/register
     * Register a new user account
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse($result, 'Account created successfully', 201);
    }

    /**
     * POST /api/auth/login
     * Authenticate user and return access token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        if (!$result) {
            return $this->errorResponse('Invalid email or password', 401);
        }

        return $this->successResponse($result, 'Login successful');
    }

    /**
     * POST /api/auth/logout
     * Revoke current access token
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * GET /api/auth/me
     * Return authenticated user profile
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->authService->formatUser($request->user()),
            'Profile retrieved successfully'
        );
    }

    /**
     * PUT /api/auth/profile
     * Update user profile settings
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                    => ['sometimes', 'string', 'min:2', 'max:100'],
            'currency'                => ['sometimes', 'in:USD,EUR,GBP,JPY,INR,CAD,AUD,CHF'],
            'timezone'                => ['sometimes', 'timezone'],
            'monthly_income_goal'     => ['nullable', 'numeric', 'min:0'],
            'budget_alerts'           => ['sometimes', 'boolean'],
            'unusual_spending_alerts' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = $request->user();
        $user->update($validator->validated());

        return $this->successResponse(
            $this->authService->formatUser($user->fresh()),
            'Profile updated successfully'
        );
    }

    /**
     * PUT /api/auth/password
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed',
                                   'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', 400);
        }

        $user->update(['password' => $request->password]);

        // Revoke all tokens (force re-login on all devices)
        $user->tokens()->delete();

        return $this->successResponse(null, 'Password changed successfully. Please login again.');
    }
}
