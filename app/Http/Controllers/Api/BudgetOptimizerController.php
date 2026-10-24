<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BudgetOptimizerController extends Controller
{
    public function optimize(Request $request)
    {
        $validated = $request->validate([
            'category'    => 'required|string',
            'guest_count' => 'required|integer|min:1',
            'total_budget'=> 'required|numeric|min:0',
        ]);

        $category = $validated['category'];
        $pax = $validated['guest_count'];
        $budget = $validated['total_budget'];

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
        $categoryRates = [
            'Wedding' => 800, 
            'Birthday' => 350, 
            'Corporate' => 500, 
            'Workshop' => 300, 
            'Debut' => 600
        ];
        
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

        return response()->json([
            'status' => $status,
            'message' => $message,
            'recommended_minimum' => $recommendedMinTotal,
            'breakdown' => $breakdown
        ], 200);
    }
}