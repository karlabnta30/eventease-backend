<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceiptMail;

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

            // Kukunin ang 50% ng total amount at i-convert sa centavos (multiply by 100 para sa PayMongo)
            $downPaymentAmount = intval(($request->amount * 0.50) * 100);

            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [
                                [
                                    'currency' => 'PHP',
                                    'amount' => $downPaymentAmount, // Nakapako na sa 50% Down Payment
                                    'name' => $request->description . ' (50% Down Payment)',
                                    'quantity' => 1
                                ]
                            ],
                            'payment_method_types' => ['card', 'gcash', 'paymaya', 'qrph'],
                            'success_url' => $frontendUrl . '/payment-success?session_id={CHECKOUT_SESSION_ID}&booking_id=' . $request->booking_id,
                            'cancel_url' => $frontendUrl . '/payment-cancelled',
                            'metadata' => [
                                'booking_id' => (string) $request->booking_id,
                                'total_package_amount' => (string) $request->amount,
                                'down_payment_amount' => (string) ($request->amount * 0.50)
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

            if ($sessionId && $sessionId !== 'test_session') {
                $secretKey = $this->getPaymongoKey();

                $response = Http::withBasicAuth($secretKey, '')
                    ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");

                if ($response->successful()) {
                    $data = $response->json();
                    $bookingId = $data['data']['attributes']['metadata']['booking_id'] ?? null;
                }
            }

            if (!$bookingId) {
                $bookingId = $fallbackBookingId;
            }

            if (!$bookingId) {
                return response()->json(['error' => 'Could not resolve booking ID from session or request.'], 400);
            }

            $cleanBookingId = intval(explode(':', $bookingId)[0]);

            $booking = DB::table('bookings')->where('id', $cleanBookingId)->first();

            // I-update ang status ng booking
            $updated = DB::table('bookings')
                ->where('id', $cleanBookingId)
                ->update([
                    'payment_status' => 'Paid (50% Down Payment)',
                    'status' => 'accepted',
                    'updated_at' => now()
                ]);

            if ($updated) {
                if ($booking) {
                    $user = DB::table('users')->where('id', $booking->user_id)->first();
                    
                    if ($user && !empty($user->email)) {
                        try {
                            // I-compute din sa email kung magkano ang binayaran (50%)
                            $totalBudget = $booking->budget ?? 0;
                            $paidAmount = $totalBudget * 0.50;

                            Mail::to($user->email)->send(new PaymentReceiptMail([
                                'customer_name' => $user->name,
                                'service_name' => ($booking->event_name ?? 'Event Booking Service') . ' (50% Down Payment)',
                                'amount' => $paidAmount,
                                'transaction_id' => $sessionId !== 'test_session' ? $sessionId : 'EVT-' . time()
                            ]));
                        } catch (\Exception $mailEx) {
                            Log::error('Payment Receipt Email Error: ' . $mailEx->getMessage());
                        }
                    }
                }

                return response()->json(['success' => true, 'message' => 'Booking updated to Down Payment Paid successfully and receipt sent.']);
            }

            return response()->json(['error' => 'Booking ID not found in database.'], 404);
        } catch (\Exception $e) {
            Log::error('Verify Payment Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}