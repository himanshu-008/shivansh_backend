<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'contact' => 'required|string', // Can be email or phone
        ]);

        $user = User::where('email', $request->contact)
                    ->orWhere('phone', $request->contact)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Account not found with this email or phone number.'], 404);
        }

        // Generate a 4-digit OTP
        $otp = rand(1000, 9999);
        
        $user->otp = (string)$otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        // In a real app, you would send an SMS or Email here based on what they entered.
        return response()->json([
            'message' => 'OTP generated successfully.',
            'dev_otp' => $otp // REMOVE IN PRODUCTION
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'contact' => 'required|string',
            'otp' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->contact)
                    ->orWhere('phone', $request->contact)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->otp !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired.'], 400);
        }

        // Reset password and clear OTP
        $user->password = bcrypt($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }
}
