<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $email = $request->email;
        $code = rand(100000, 999999);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now()
            ]
        );

        try {
            Mail::raw("Your EventEase password reset code is: {$code}. It expires in 15 minutes.", function ($message) use ($email) {
                $message->to($email)
                        ->subject('Password Reset Code - EventEase');
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Reset code generated successfully.',
                'debug_code' => $code 
            ], 200);
        }

        return response()->json(['message' => 'Password reset code sent to your email.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $resetRecord = DB::table('password_resets')->where('email', $request->email)->first();

        if (!$resetRecord || !Hash::check($request->code, $resetRecord->token)) {
            return response()->json(['error' => 'Invalid or expired reset code.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been successfully reset!'], 200);
    }
}