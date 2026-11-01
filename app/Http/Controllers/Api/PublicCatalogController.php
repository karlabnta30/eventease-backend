<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service; // Siguraduhing na-import ang Service model mo

class ServiceController extends Controller
{
    /**
     * Display a listing of the services for the public catalog.
     */
    public function publicIndex()
    {
        try {
            // Kunin ang mga serbisyo na active o verified mula sa Aiven DB
            $services = Service::with('vendor')
                ->where('status', 'active')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $services
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch public services.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}