<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    /**
     * Show a specific vendor profile
     */
    public function show($id)
    {
        try {
            $vendor = Vendor::findOrFail($id);
            return response()->json(['data' => $vendor], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service not found'], 404);
        }
    }

    /**
     * Fetch bookings specifically for this vendor
     */
    public function myBookings()
    {
        try {
            // Get all services owned by this user
            $serviceIds = Vendor::where('user_id', Auth::id())->pluck('id');

            // Fetch bookings linked to those services
            $bookings = Booking::whereIn('service_id', $serviceIds)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate Mini Stats
            $stats = [
                // Revenue updates based on Payment Status 'Paid'
                'earnings' => Booking::whereIn('service_id', $serviceIds)
                    ->where('payment_status', 'Paid')
                    ->sum('budget'),
                'pending' => Booking::whereIn('service_id', $serviceIds)
                    ->where('status', 'pending')
                    ->count(),
            ];

            return response()->json([
                'bookings' => $bookings,
                'stats' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Booking Status (Accept/Reject)
     */
    public function updateBookingStatus(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            
            $serviceIds = Vendor::where('user_id', Auth::id())->pluck('id')->toArray();
            if (!in_array($booking->service_id, $serviceIds)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $booking->status = $request->status; 
            $booking->save();

            return response()->json(['message' => "Booking {$request->status} successfully!"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new service (Gated by Account Verification Status)
     */
    public function storeService(Request $request)
    {
        try {
            $user = Auth::user();

            // Check if vendor account is verified globally
            $isVerified = $user->verification_status === 'verified' || 
                          DB::table('services')->where('user_id', $user->id)->where('verification_status', 'verified')->exists();

            if (!$isVerified) {
                return response()->json([
                    'error' => 'Only verified vendors are allowed to add a service. Please wait for admin permit approval.'
                ], 403);
            }

            $validated = $request->validate([
                'business_name'        => 'required|string',
                'category'             => 'required|string',
                'location'             => 'required|string',
                'starting_price'       => 'required|numeric',
                'description'          => 'nullable|string',
                'bio'                  => 'nullable|string',
                'terms_and_conditions' => 'nullable|string',
            ]);

            $vendor = Vendor::create([
                'user_id'              => $user->id, 
                'name'                 => $validated['business_name'], 
                'category'             => $validated['category'],
                'location'             => $validated['location'],
                'price'                => $validated['starting_price'], 
                'description'          => $validated['description'],
                'bio'                  => $validated['bio'] ?? '', 
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'is_available'         => true,
                'service_fee'          => 0.00, 
                'verification_status'  => 'verified',
            ]);

            return response()->json(['message' => 'Service published!', 'data' => $vendor], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * For Client Side: Only show available vendors
     */
    public function index()
    {
        $vendors = Vendor::where('is_available', true)->get();
        return response()->json(['data' => $vendors], 200);
    }

    /**
     * For Vendor Side: Show all their services regardless of availability
     */
    public function myServices()
    {
        $services = Vendor::where('user_id', Auth::id())->get();
        return response()->json(['data' => $services], 200);
    }

    /**
     * The Toggle Logic (Power Button)
     */
    public function updateStatus(Request $request, $id)
    {
        $service = Vendor::findOrFail($id);
        
        // Security check: ensure the logged-in vendor owns this service
        if ($service->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $service->is_available = !$service->is_available;
        $service->save();

        return response()->json(['is_available' => $service->is_available]);
    }

    /**
     * Upload or Update Business Permit using Cloudinary Native Helper
     */
    public function uploadPermit(Request $request)
    {
        $request->validate([
            'permit' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        try {
            $user = Auth::user();

            if ($request->hasFile('permit')) {
                $file = $request->file('permit');

                // Upload directly using Cloudinary's native helper function
                $uploadedFileUrl = cloudinary()->upload($file->getRealPath())->getSecureUrl();

                if (!$uploadedFileUrl) {
                    return response()->json(['error' => 'Cloudinary failed to return a secure URL.'], 500);
                }

                // Update global permit path and status on the user account
                DB::table('users')->where('id', $user->id)->update([
                    'permit_path' => $uploadedFileUrl,
                    'verification_status' => 'pending',
                    'updated_at' => now(),
                ]);

                // Sync status across all existing service rows
                DB::table('services')->where('user_id', $user->id)->update([
                    'permit_path' => $uploadedFileUrl,
                    'verification_status' => 'pending',
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Permit uploaded successfully and is pending admin verification.',
                    'path' => $uploadedFileUrl
                ], 200);
            }

            return response()->json(['error' => 'No file uploaded.'], 400);

        } catch (\Exception $e) {
            Log::error("Permit upload error: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to upload permit: ' . $e->getMessage()
            ], 500);
        }
    }
}