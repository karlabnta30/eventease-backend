<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bundle;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BundleController extends Controller
{
    public function index()
    {
        try {
            if (!Schema::hasTable('bundles')) {
                return response()->json([], 200);
            }

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
                'min_pax'     => 'nullable|integer',
                'max_pax'     => 'nullable|integer',
                'services'    => 'required|array',
            ]);

            $user = $request->user() ?? Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized user session.'], 401);
            }

            // Gamitin ang updateOrCreate para sigurado na laging valid at bago ang vendor record ng user na ito
            $vendor = Vendor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name'  => $user->name ?? 'Vendor Hub',
                    'category'       => 'General',
                    'location'       => 'Metro Manila',
                    'starting_price' => 0,
                    'is_available'   => true,
                ]
            );

            // I-save ang Bundle na may kasamang is_available = true para laging online by default
            $bundle = Bundle::create([
                'vendor_id'    => $vendor->id,
                'bundle_name'  => $request->bundle_name,
                'description'  => $request->description,
                'price'        => $request->price,
                'min_pax'      => $request->min_pax ?? 15,
                'max_pax'      => $request->max_pax ?? 100,
                'is_available' => true, 
            ]);

            // I-sync ang mga napiling serbisyo
            if ($request->has('services') && method_exists($bundle, 'services')) {
                $serviceIds = array_map('intval', $request->services);
                $bundle->services()->sync($serviceIds);
            }

            return response()->json([
                'message' => 'Bundle created successfully!',
                'data'    => $bundle->load('services', 'vendor')
            ], 201);
            
        } catch (ValidationException $e) {
            return response()->json([
                'error'    => 'Validation Failed',
                'messages' => $e->errors()
            ], 422);
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

            $role = strtolower($user->role ?? '');
            $isAdmin = ($role === 'admin' || $role === 'administrator');

            // Hanapin ang vendor record ng user
            $vendor = Vendor::where('user_id', $user->id)->first();
            
            // Suriin kung ang user ay admin, o kung may vendor record siya at tumutugma ang ID, 
            // O kaya ay pahintulutan kung ang vendor_id ng bundle ay pagmamay-ari ng user (fallback check)
            $isOwner = ($vendor && $bundle->vendor_id === $vendor->id) || ($bundle->vendor && $bundle->vendor->user_id === $user->id);

            if (!$isAdmin && !$isOwner) {
                return response()->json(['error' => 'Unauthorized action.'], 403);
            }

            if (method_exists($bundle, 'services')) {
                $bundle->services()->detach();
            }
            
            $bundle->delete();

            return response()->json(['message' => 'Bundle deleted successfully'], 200);

        } catch (\Exception $e) {
            Log::error("Bundle Destroy Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}