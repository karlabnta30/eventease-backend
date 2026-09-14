<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function storeFeedback(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Not Authenticated'], 401);
        }

        try {
            return DB::transaction(function () use ($validated, $user) {
                // We use the columns verified in your DESCRIBE screenshot
                DB::table('contacts')->insert([
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'subject'    => $validated['subject'],
                    'message'    => $validated['message'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 1. Notification for User (Only if NOT an admin to prevent double pop-ups)
                if ($user->role !== 'admin') {
                    DB::table('notifications')->insert([
                        'user_id'    => $user->id,
                        'title'      => 'Feedback Received',
                        'message'    => 'Hi ' . $user->name . ', we received your feedback: ' . $validated['subject'],
                        'is_read'    => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 2. Notification for Admins
                $admins = DB::table('users')->where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    DB::table('notifications')->insert([
                        'user_id'    => $admin->id,
                        'title'      => 'New System Feedback',
                        'message'    => $user->name . ' sent feedback regarding: ' . $validated['subject'],
                        'is_read'    => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return response()->json(['message' => 'Success'], 201);
            });
        } catch (\Exception $e) {
            Log::error("Feedback Store Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getFeedback()
    {
        try {
            // Fetch directly from the contacts table
            $feedback = DB::table('contacts')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $feedback
            ], 200);
            
        } catch (\Exception $e) {
            Log::error("Admin Feedback Fetch Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to load feedback'], 500);
        }
    }
}