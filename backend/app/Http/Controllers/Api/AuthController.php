<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone_number' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'community',
            'vendor_status' => 'approved', // Default was 'none'; set to 'approved' for demo/testing
        ]);

        return $this->respondWithToken($user, '201 Created: Account registered successfully.', 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['401 Unauthorized: Invalid email or password.'],
            ]);
        }

        return $this->respondWithToken($user, '200 OK: Authentication successful.');
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => '200 OK: Session terminated successfully.',
        ]);
    }

    private function respondWithToken(User $user, string $message, int $status = 200)
    {
        $token = $user->createToken('carboot-cmart-web')->plainTextToken;

        return response()->json([
            'message' => $message,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], $status);
    }

    // ==========================================
    // --- SIGN IN WITH GOOGLE ---
    // ==========================================

    public function redirectToGoogle()
    {
        // Use stateless() because this API serves a SPA frontend
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find user by email, or create a new account if none exists
            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'name' => $googleUser->name,
                    'vendor_status' => 'approved', // Auto-approve so Google users can book immediately
                    'role' => 'community', 
                    'password' => Hash::make(Str::random(16)) // Random password; users sign in via Google OAuth
                ]
            );

            // Reuse respondWithToken() for a consistent JSON response shape
            return $this->respondWithToken($user, '200 OK: Google authentication successful.');

        } catch (\Exception $e) {
            return response()->json([
                'message' => '500 Internal Server Error: Failed to authenticate with Google.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}