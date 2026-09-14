<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service; // <--- MUST ADD THIS
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query();

        if ($request->has('category') && $request->category != '') {
            $query->where('category', $request->category);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('location', 'like', '%' . $searchTerm . '%');
            });
        }

        return response()->json($query->where('is_available', true)->get());
    }

    public function store(Request $request)
    {
        // This fulfills Objective 7: Dashboard Management for Vendors
        $validated = $request->validate([
            'name' => 'required|string',
            'category' => 'required|string',
            'price' => 'required|numeric',
            'location' => 'required|string',
            'description' => 'nullable|string',
        ]);

        // Automatically assign the logged-in vendor's ID
        $service = $request->user()->services()->create($validated);

        return response()->json($service, 201);
    }
}