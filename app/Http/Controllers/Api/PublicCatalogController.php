<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;

class PublicCatalogController extends Controller
{
    public function index()
    {
        try {
            $query = Service::with('vendor');
            
            // Sinisiguro nitong hindi magka-crash kung may o walang 'status' column sa database
            if (Schema::hasColumn('services', 'status')) {
                $query->where('status', 'active');
            }

            $services = $query->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $services
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch public catalog data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}