<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
     * Fetch bookings specifically for this vendor and calculate revenue safely
     */
    public function myBookings()
    {
        try {
            $vendorUserId = Auth::id();
            
            // Kunin ang mga ID ng services/vendors na pag-aari ng user na ito
            $vendorServiceIds = Vendor::where('user_id', $vendorUserId)->pluck('id');

            // Kunin ang mga booking ID mula sa booking_service table o service_id column
            $bookingIds = DB::table('booking_service')
                ->whereIn('service_id', $vendorServiceIds)
                ->pluck('booking_id')
                ->merge(
                    Booking::whereIn('service_id', $vendorServiceIds)->pluck('id')
                )
                ->unique();

            // Kunin ang mismong mga booking kasama ang user at services nang walang pivot price conflict
            $bookings = Booking::whereIn('id', $bookingIds)
                ->with(['user', 'services'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Kalkulahin ang total earnings gamit ang budget ng booking o service price
            $totalEarnings = Booking::whereIn('id', $bookingIds)
                ->whereIn('payment_status', ['downpayment_paid', 'fully_paid', 'completed', 'paid'])
                ->get()
                ->sum(function($booking) {
                    $budget = (float) $booking->budget;
                    $status = strtolower(trim($booking->payment_status));
                    
                    // Kung fully paid, buong budget; kung downpayment pa lang, 50%
                    if (in_array($status, ['fully_paid', 'completed', 'paid'])) {
                        return $budget;
                    } else {
                        return $budget * 0.50;
                    }
                });

            $pendingCount = Booking::whereIn('id', $bookingIds)
                ->where('status', 'pending')
                ->count();

            $stats = [
                'earnings' => $totalEarnings ?? 0,
                'pending' => $pendingCount,
            ];

            return response()->json([
                'bookings' => $bookings,
                'stats' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error("Vendor fetch failed: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json(['error' => 'Vendor fetch failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Booking Status (Accept/Reject)
     */
    public function updateBookingStatus(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            
            $vendorServiceIds = Vendor::where('user_id', Auth::id())->pluck('id')->toArray();
            
            $isAssociated = in_array($booking->service_id, $vendorServiceIds) || 
                            DB::table('booking_service')->where('booking_id', $id)->whereIn('service_id', $vendorServiceIds)->exists();

            if (!$isAssociated) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($request->has('payment_status')) {
                $booking->payment_status = $request->payment_status;
            }
            if ($request->has('status')) {
                $booking->status = $request->status; 
            }
            
            $booking->save();

            return response()->json(['message' => "Booking status updated successfully!"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new service (Gated by Account Verification Status & Permit Upload)
     */
    public function storeService(Request $request)
    {
        try {
            $user = Auth::user();

            $userRecord = DB::table('users')->where('id', $user->id)->first();
            
            if (empty($userRecord->permit_path) || $userRecord->verification_status !== 'verified') {
                return response()->json([
                    'error' => 'Access Denied: You must upload a valid business permit and wait for admin verification before posting a service.'
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
                'permit_path'          => $userRecord->permit_path,
            ]);

            return response()->json(['message' => 'Service published!', 'data' => $vendor], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete/Destroy a specific service
     */
    public function destroyService($id)
    {
        try {
            $service = Vendor::findOrFail($id);

            if ($service->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $service->delete();

            return response()->json(['message' => 'Service deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service not found or failed to delete: ' . $e->getMessage()], 500);
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
        
        if ($service->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $service->is_available = !$service->is_available;
        $service->save();

        return response()->json(['is_available' => $service->is_available]);
    }

    /**
     * Upload Business Permit directly via Cloudinary REST API
     */
    public function uploadPermit(Request $request)
    {
        try {
            $request->validate([
                'permit' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', 
            ]);

            $user = Auth::user();

            if (!$request->hasFile('permit')) {
                return response()->json(['error' => 'No file uploaded.'], 400);
            }

            $file = $request->file('permit');

            $cloudinaryUrl = env('CLOUDINARY_URL');
            preg_match('/cloudinary:\/\/([^\:]+)\:([^\@]+)\@([^\/\?]+)/', $cloudinaryUrl, $matches);
            
            $apiKey    = $matches[1] ?? env('CLOUDINARY_API_KEY');
            $apiSecret = $matches[2] ?? env('CLOUDINARY_API_SECRET');
            $cloudName = $matches[3] ?? env('CLOUDINARY_CLOUD_NAME');

            if (!$apiKey || !$apiSecret || !$cloudName) {
                return response()->json(['error' => 'Cloudinary environment credentials are missing or malformed.'], 500);
            }

            $timestamp = time();
            $signatureString = "timestamp=" . $timestamp . $apiSecret;
            $signature = sha1($signatureString);

            $response = Http::attach(
                'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
            )->post("https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error_message' => 'Cloudinary API rejection: ' . $response->body()
                ], 500);
            }

            $responseData = $response->json();
            $uploadedFileUrl = $responseData['secure_url'] ?? null;

            if (!$uploadedFileUrl) {
                return response()->json(['error' => 'Cloudinary responded successfully, but returned no secure URL.'], 500);
            }

            DB::table('users')->where('id', $user->id)->update([
                'permit_path' => $uploadedFileUrl,
                'verification_status' => 'pending',
                'updated_at' => now(),
            ]);

            DB::table('services')->where('user_id', $user->id)->update([
                'permit_path' => $uploadedFileUrl,
                'verification_status' => 'pending',
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Permit uploaded successfully and is pending admin verification.',
                'path' => $uploadedFileUrl
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Direct permit upload error: " . $e->getMessage() . " on line " . $e->getLine());
            
            return response()->json([
                'error_message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], 500);
        }
    }
}