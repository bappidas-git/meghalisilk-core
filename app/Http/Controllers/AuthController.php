<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    //method to change the password
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'wp_user_id' => 'required|integer',
                'current_password' => 'required',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            $user = $request->email ? User::where('email', $request->email)->first() : null;
            if($user === null){
                return response()->json(['message' => 'User not found'], 404);
            }
            $userId = $request->wp_user_id;
            $newPassword = $request->new_password;

            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 401);
            }

            $user->password = bcrypt($request->new_password);
            $user->save();

            $response = Http::withBasicAuth($request->email, $newPassword)
                ->put(env('WP_SITE_URL') . "/users/{$userId}", [
                    'password' => $newPassword
                ]);

            if ($response->failed()) {
                return response()->json(['message' => "WordPress error: " . $response->body()], 500);
            }

            return response()->json(['message' => 'Password changed successfully']);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => 'An error occurred while changing password.',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
