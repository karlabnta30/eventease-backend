<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * SAVE Feedback (Called by Client via Contact Page)
     */
    public function storeFeedback(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);

            DB::table('contacts')->insert([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Success'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * VIEW Feedback (Called by Admin Dashboard)
     */
    public function getFeedback()
    {
        try {
            $feedbacks = DB::table('contacts')->orderBy('created_at', 'desc')->get();
            return response()->json(['data' => $feedbacks], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Feedback records not found.'], 500);
        }
    }

    /**
     * GET Registered Clients ONLY
     */
    public function getUsers()
    {
        try {
            $users = DB::table('users')
                ->where('role', 'client')
                ->select('id', 'name', 'email', 'role', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'User records not found.'], 500);
        }
    }

    /**
     * GET Vendors for Moderation (Original endpoint)
     */
    public function getVendors()
    {
        try {
            $vendors = DB::table('users')
                ->where('role', 'vendor')
                ->select('id', 'name', 'email', 'role', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['data' => $vendors], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Vendor records not found.'], 500);
        }
    }

    /**
     * UPDATED: GET Vendor Accounts with their global Permit details for Admin Verification
     */
    public function getVendorsForVerification()
    {
        try {
            // Fetch all vendor accounts cleanly from users table with leftJoin to handle users with or without services
            $vendors = DB::table('users')
                ->where('users.role', 'vendor')
                ->leftJoin('services', 'users.id', '=', 'services.user_id')
                ->select(
                    'users.id', 
                    'users.name as owner_name', 
                    'users.email as owner_email', 
                    'users.permit_path', 
                    'users.verification_status',
                    'services.name as business_name',
                    'services.category'
                )
                ->get()
                ->unique('id')
                ->values()
                ->map(function ($vendor) {
                    if (!$vendor->verification_status) {
                        $vendor->verification_status = 'pending';
                    }
                    if (!$vendor->business_name) {
                        $vendor->business_name = $vendor->owner_name . "'s Business";
                    }
                    return $vendor;
                });

            return response()->json(['data' => $vendors], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch vendor permits: ' . $e->getMessage()], 500);
        }
    }

    /**
     * UPDATED: Update Vendor Global Account Verification Status (Approve/Reject Permit)
     */
    public function updateVerificationStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:verified,rejected,pending'
        ]);

        try {
            // Update global status on the user account table
            DB::table('users')->where('id', $id)->update([
                'verification_status' => $request->status,
                'updated_at' => now()
            ]);

            // Sync verification status across all services owned by this vendor
            DB::table('services')->where('user_id', $id)->update([
                'verification_status' => $request->status,
                'updated_at' => now()
            ]);

            return response()->json(['message' => "Vendor status updated to {$request->status} successfully."], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET Notifications for the logged-in user
     */
    public function getNotifications(Request $request)
    {
        try {
            $notifications = DB::table('notifications')
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * MARK Notification as read
     */
    public function markAsRead($id)
    {
        try {
            DB::table('notifications')->where('id', $id)->update(['is_read' => true]);
            return response()->json(['message' => 'Notification updated'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin Dashboard Statistics & Analytics
     */
    public function stats()
    {
        try {
            $categories = DB::table('bookings')
                ->select('category', DB::raw('count(*) as total'))
                ->groupBy('category')->get();

            return response()->json([
                'total_users'    => DB::table('users')->where('role', 'client')->count(),
                'total_bookings' => DB::table('bookings')->count(),
                'total_revenue'  => (float) DB::table('bookings')->sum('budget') ?: 0,
                'category_data'  => $categories,
            ], 200);
            
        } catch (\Exception $e) {
            Log::error("Stats Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to calculate statistics.'], 500);
        }
    }
}