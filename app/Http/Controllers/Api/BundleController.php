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

            // Safely fetch bundles with relationships
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
                'price' => 'required|numeric',
                'services' => 'required|array',
                'services.*' => 'exists:services,id'
            ]);

            $user = $request->user() ?? Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized user session.'], 401);
            }

            // Look up the vendor profile associated with the authenticated user
            $vendor = Vendor::where('user_id', $user->id)->first();

            // If the vendor profile doesn't exist, safely create one with unique constraints handled
            if (!$vendor) {
                $vendor = Vendor::create([
                    'user_id' => $user->id,
                    'business_name' => $user->name . ' Business ' . rand(100, 999),
                    'category' => 'General',
                    'location' => 'Manila',
                    'starting_price' => 0,
                    'is_available' => true
                ]);
            }

            if (!$vendor || !isset($vendor->id)) {
                return response()->json(['error' => 'Failed to resolve or create vendor profile.'], 500);
            }

            $bundle = Bundle::create([
                'vendor_id' => $vendor->id,
                'bundle_name' => $request->bundle_name,
                'description' => $request->description,
                'price' => $request->price,
            ]);

            $bundle->services()->sync($request->services);

            return response()->json([
                'message' => 'Bundle created successfully!', 
                'data' => $bundle->load('services', 'vendor')
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