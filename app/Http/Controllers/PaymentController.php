<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private function getPaymongoKey()
    {
        $key = env('PAYMONGO_SECRET_KEY') ?? config('services.paymongo.key');
        
        if (!$key) {
            throw new \Exception('PayMongo Secret Key is missing in environment variables.');
        }

        return trim($key);
    }

    private function getFrontendUrl()
    {
        // Dynamically fetch frontend URL from config/env or fallback safely to production domain
        return rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://www.eventeases.com')), '/');
    }

    public function createCheckout(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string',
            'booking_id' => 'required',
        ]);

        try {
            $secretKey = $this->getPaymongoKey();
            $frontendUrl = $this->getFrontendUrl();

            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [
                                [
                                    'currency' => 'PHP',
                                    'amount' => intval($request->amount * 100),
                                    'name' => $request->description,
                                    'quantity' => 1
                                ]
                            ],
                            'payment_method_types' => ['card', 'gcash', 'paymaya', 'qrph'],
                            'success_url' => $frontendUrl . '/payment-success?session_id={CHECKOUT_SESSION_ID}&booking_id=' . $request->booking_id,
                            'cancel_url' => $frontendUrl . '/payment-cancelled',
                            'metadata' => [
                                'booking_id' => (string) $request->booking_id
                            ]
                        ]
                    ]
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => $response->json()['errors'][0]['detail'] ?? 'Payment gateway error'
                ], 400);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');
            $fallbackBookingId = $request->input('booking_id');
            $bookingId = null;

            // If we have a session ID from PayMongo, query PayMongo API to securely retrieve metadata
            if ($sessionId && $sessionId !== 'test_session') {
                $secretKey = $this->getPaymongoKey();

                $response = Http::withBasicAuth($secretKey, '')
                    ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");

                if ($response->successful()) {
                    $data = $response->json();
                    $bookingId = $data['data']['attributes']['metadata']['booking_id'] ?? null;
                }
            }

            // Fallback to request booking_id if metadata extraction was empty
            if (!$bookingId) {
                $bookingId = $fallbackBookingId;
            }

            if (!$bookingId) {
                return response()->json(['error' => 'Could not resolve booking ID from session or request.'], 400);
            }

            $cleanBookingId = intval(explode(':', $bookingId)[0]);

            // Direct database update to guarantee execution
            $updated = DB::table('bookings')
                ->where('id', $cleanBookingId)
                ->update([
                    'payment_status' => 'Paid',
                    'status' => 'accepted',
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Booking updated to Paid successfully.']);
            }

            return response()->json(['error' => 'Booking ID not found in database.'], 404);
        } catch (\Exception $e) {
            Log::error('Verify Payment Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}