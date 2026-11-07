<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorService; 

class BudgetOptimizerController extends Controller
{
    public function optimize(Request $request)
    {
        $validated = $request->validate([
            'category'     => 'required|string',
            'guest_count'  => 'required|integer|min:15|max:100',
            'total_budget' => 'required|numeric|min:0',
            'location'     => 'nullable|string', 
        ]);

        $category = $validated['category'];
        $pax = $validated['guest_count'];
        $budget = $validated['total_budget'];
        $eventLocation = $validated['location'] ?? null;

        // Standard percentage allocations based on event industry benchmarks
        $allocations = [
            'Catering & Food' => 0.40,
            'Venue Rental'    => 0.25,
            'Decoration'      => 0.15,
            'Photography'     => 0.10,
            'Entertainment'   => 0.10,
        ];

        $breakdown = [];
        foreach ($allocations as $item => $percentage) {
            $recommendedAmount = round($budget * $percentage, 2);
            $breakdown[] = [
                'item' => $item,
                'recommended_amount' => $recommendedAmount,
                'percentage' => ($percentage * 100) . '%'
            ];
        }

        // Cost efficiency analysis baseline rates per pax
        $categoryRates = [ 'Wedding' => 800, 'Birthday' => 350, 'Corporate' => 500, 'Workshop' => 300, 'Debut' => 600 ];
        $minPerPax = $categoryRates[$category] ?? 300;
        $recommendedMinTotal = $pax * $minPerPax;

        $status = 'Optimal';
        $message = 'Your budget is well-balanced for your guest count and event type.';

        if ($budget < $recommendedMinTotal) {
            $status = 'Infeasible';
            $message = "Warning: Your target budget (₱" . number_format($budget, 2) . ") is lower than the recommended baseline (₱" . number_format($recommendedMinTotal, 2) . ") for {$pax} guests. Consider scaling down the guest count or prioritizing core essentials.";
        } else {
            $surplus = $budget - $recommendedMinTotal;
            if ($surplus > 0) {
                $message = "Optimal! You have a surplus/contingency buffer of ₱" . number_format($surplus, 2) . " which can be allocated to luxury add-ons or premium upgrades.";
            }
        }

        // Pag-query ng mga optimized items na isasama sa AI recommendation
        $query = VendorService::where('is_available', 1);

        if ($eventLocation) {
            $query->where(function($q) use ($eventLocation) {
                $q->where('location', 'LIKE', "%{$eventLocation}%")
                  ->orWhere('service_scope', 'LIKE', "%{$eventLocation}%");
            });
        }

        $availableVendors = $query->get();
        $optimizedItems = [];
        $accumulatedCost = 0;
        $fulfilledCategories = []; // Track categories upang maiwasan ang duplicate sa parehong kategorya

        foreach ($availableVendors as $vendor) {
            $price = floatval($vendor->starting_price ?? $vendor->price ?? 0);
            $minPax = $vendor->min_pax ? intval($vendor->min_pax) : 15;
            $maxPax = $vendor->max_pax ? intval($vendor->max_pax) : 100;
            $vendorCategory = $vendor->category;

            // Suriin kung pasok sa pax, budget, at hindi pa nakuha ang category na ito
            if ($pax >= $minPax && $pax <= $maxPax && ($accumulatedCost + $price) <= $budget) {
                if (!in_array($vendorCategory, $fulfilledCategories)) {
                    $optimizedItems[] = [
                        'id' => $vendor->id,
                        'name' => $vendor->business_name ?? $vendor->name,
                        'category' => $vendorCategory,
                        'price' => $price,
                        'min_pax' => $minPax,
                        'max_pax' => $maxPax,
                    ];
                    $accumulatedCost += $price;
                    $fulfilledCategories[] = $vendorCategory; // Markahan ang category na ito bilang nakuha na
                }
            }
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'recommended_minimum' => $recommendedMinTotal,
            'breakdown' => $breakdown,
            'optimized_items' => $optimizedItems,
            'total_optimized_cost' => $accumulatedCost
        ], 200);
    }
}