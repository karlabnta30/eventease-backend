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
            $breakdown[] = [
                'item' => $item,
                'recommended_amount' => round($budget * $percentage, 2),
                'percentage' => ($percentage * 100) . '%'
            ];
        }

        // Cost efficiency analysis
        $categoryRates = ['Wedding' => 800, 'Birthday' => 350, 'Corporate' => 500, 'Workshop' => 300, 'Debut' => 600];
        $minPerPax = $categoryRates[$category] ?? 300;
        $recommendedMinTotal = $pax * $minPerPax;

        $status = 'Optimal';
        $message = 'Your budget is well-balanced for your guest count and event type.';

        if ($budget < $recommendedMinTotal) {
            $status = 'Infeasible';
            $message = "Warning: Your budget is lower than the recommended baseline (₱" . number_format($recommendedMinTotal, 2) . ") for {$pax} guests.";
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'recommended_minimum' => $recommendedMinTotal,
            'breakdown' => $breakdown
        ], 200);
    }
}