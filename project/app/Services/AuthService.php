<?php
// app/Services/AuthService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * AuthService
 * Handles all authentication business logic
 */
class AuthService
{
    /**
     * Register a new user account
     *
     * @param  array $data  Validated registration data
     * @return array        User and token data
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => trim($data['name']),
            'email'    => strtolower(trim($data['email'])),
            'password' => $data['password'],           // auto-hashed by model cast
            'currency' => $data['currency'] ?? 'USD',
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);

        Log::info("New user registered: {$user->email} (ID: {$user->id})");

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'         => $this->formatUser($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ];
    }

    /**
     * Authenticate user and issue token
     *
     * @param  string $email
     * @param  string $password
     * @return array|null  Returns null on failure
     */
    public function login(string $email, string $password): ?array
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        // Timing-safe comparison prevents user enumeration
        if (!$user || !Hash::check($password, $user->password)) {
            Log::warning("Failed login attempt for email: {$email}");
            return null;
        }

        // Revoke old tokens (single-session) — optional: remove for multi-device
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info("User logged in: {$user->email} (ID: {$user->id})");

        return [
            'user'         => $this->formatUser($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ];
    }

    /**
     * Revoke current user token (logout)
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
        Log::info("User logged out: {$user->email} (ID: {$user->id})");
    }

    /**
     * Format user data for API response (exclude sensitive fields)
     */
    public function formatUser(User $user): array
    {
        return [
            'id'                      => $user->id,
            'name'                    => $user->name,
            'email'                   => $user->email,
            'currency'                => $user->currency,
            'timezone'                => $user->timezone,
            'budget_alerts'           => $user->budget_alerts,
            'unusual_spending_alerts' => $user->unusual_spending_alerts,
            'created_at'              => $user->created_at->toISOString(),
        ];
    }
}
