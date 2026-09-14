<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    /**
     * REGISTER User with OTP Dispatch (Without auto-login token)
     */
    public function register(Request $request) 
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'contact_number' => 'required|string|max:20', 
            'address' => 'required|string', 
            'role' => 'nullable|string|in:client,vendor', 
        ]);

        $otp = rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'contact_number' => $data['contact_number'],
            'address' => $data['address'],
            'role' => $data['role'] ?? 'client', 
            'otp' => $otp,
            'otp_expires_at' => $expiresAt,
        ]);

        // Wrapped in try/catch so registration doesn't crash if SMTP fails during your consultation,
        // while also returning the debug_otp directly in the JSON response so you can see it instantly on screen.
        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::error("Failed to send registration OTP email: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'User registered successfully. Verification OTP sent to email.',
            'debug_otp' => $otp, // <--- This guarantees you can see the OTP right away without waiting for emails
            'user' => $user
        ], 201);
    }

    /**
     * LOGIN User (Blocked if OTP is not verified)
     */
    public function login(Request $request) 
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!is_null($user->otp)) {
            return response()->json([
                'message' => 'Please verify your email using the OTP sent before logging in.',
                'requires_verification' => true
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful', 
            'token' => $token, 
            'user' => $user
        ], 200);
    }

    /**
     * GET User Profile with Safe Null-Checks to Avoid 500 Errors
     */
    public function userProfile(Request $request)
    {
        $user = $request->user();
        
        $verificationStatus = $user->verification_status ?? 'pending'; 
        $permitPath = $user->permit_path ?? null;

        if ($user->role === 'vendor') {
            try {
                $verifiedService = DB::table('services')->where('user_id', $user->id)->where('verification_status', 'verified')->exists();
                if ($verifiedService || $user->verification_status === 'verified') {
                    $verificationStatus = 'verified';
                } else {
                    $service = DB::table('services')->where('user_id', $user->id)->first();
                    if ($service) {
                        $verificationStatus = $service->verification_status ?? 'pending';
                        $permitPath = $permitPath ?? ($service->permit_path ?? null);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Skipping services check for profile: " . $e->getMessage());
            }
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'verification_status' => $verificationStatus,
            'permit_path' => $permitPath,
        ], 200);
    }

    /**
     * UPDATE User Profile
     */
    public function updateProfile(Request $request)
    {
        Log::info('Update Data Received:', $request->all());

        $user = $request->user();

        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'contact_number' => 'sometimes|nullable|string|max:20',
            'address'        => 'sometimes|nullable|string',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user'    => $user->fresh() 
        ], 200);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * SEND OTP
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();
        
        $otp = rand(100000, 999999);

        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'OTP sent successfully to your email address.',
            'debug_otp' => $otp // Also returned here for backup
        ], 200);
    }

    /**
     * VERIFY OTP (Now grants login token upon success)
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp !== $request->otp) {
            return response()->json(['error' => 'Invalid OTP code.'], 400);
        }

        if (Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['error' => 'OTP code has expired.'], 400);
        }

        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully!',
            'token' => $token,
            'user' => $user
        ], 200);
    }
}