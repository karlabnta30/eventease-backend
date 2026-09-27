<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bundle;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class BundleController extends Controller
{
    public function index()
    {
        try {
            if (!Schema::hasTable('bundles')) {
                return response()->json([], 200);
            }

            // Fetch bundles with vendor and services relationships
            $bundles = Bundle::with(['vendor', 'services'])->get();
            
            return response()->json($bundles, 200);
        } catch (\Exception $e) {
            Log::error("Bundle Index Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'bundle_name' => 'required|string',
                'description' => 'nullable|string',
                'price'       => 'required|numeric',
                'services'    => 'required|array',
                'services.*'  => 'exists:services,id',
                'vendor_id'   => 'nullable|integer'
            ]);

            $user = $request->user() ?? Auth::user();
            $finalVendorId = null;

            // 1. Check if vendor_id was passed directly from the frontend request
            if ($request->filled('vendor_id') && Vendor::where('id', $request->vendor_id)->exists()) {
                $finalVendorId = $request->vendor_id;
            }

            // 2. If no vendor_id in request, check vendor table using logged-in user ID
            if (!$finalVendorId && $user) {
                $vendor = Vendor::where('user_id', $user->id)->first();
                if ($vendor) {
                    $finalVendorId = $vendor->id;
                }
            }

            // 3. Fallback: Use the first available vendor in the database if unresolved
            if (!$finalVendorId) {
                $firstVendor = Vendor::first();
                if ($firstVendor) {
                    $finalVendorId = $firstVendor->id;
                } else if ($user) {
                    // Only create a vendor profile if the vendors table is completely empty
                    $newVendor = Vendor::create([
                        'user_id'        => $user->id,
                        'business_name'  => $user->name . ' Business',
                        'category'       => 'General',
                        'location'       => 'Manila',
                        'starting_price' => 0,
                        'is_available'   => true
                    ]);
                    $finalVendorId = $newVendor->id;
                }
            }

            if (!$finalVendorId) {
                return response()->json(['error' => 'No valid vendor record found to associate with this bundle.'], 422);
            }

            // Create the bundle with a valid vendor_id
            $bundle = Bundle::create([
                'vendor_id'   => $finalVendorId,
                'bundle_name' => $request->bundle_name,
                'description' => $request->description,
                'price'       => $request->price,
            ]);

            // Sync pivot table services
            $bundle->services()->sync($request->services);

            return response()->json([
                'message' => 'Bundle created successfully!',
                'data'    => $bundle->load('services', 'vendor')
            ], 201);
            
        } catch (\Exception $e) {
            Log::error("Bundle Store Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user() ?? Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized user session.'], 401);
            }

            $bundle = Bundle::with('vendor')->find($id);

            if (!$bundle) {
                return response()->json(['message' => 'Bundle not found'], 404);
            }

            // Verify vendor ownership or admin privilege
            $vendor = Vendor::where('user_id', $user->id)->first();
            if ($vendor && $bundle->vendor_id !== $vendor->id && $user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized action.'], 403);
            }

            // Detach pivot relationships before deleting
            $bundle->services()->detach();
            $bundle->delete();

            return response()->json(['message' => 'Bundle deleted successfully'], 200);

        } catch (\Exception $e) {
            Log::error("Bundle Destroy Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}