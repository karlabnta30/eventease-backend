<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Vendor; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BookingController extends Controller
{
    public function index()
    {
        $bookings = Auth::user()->bookings()
            ->with(['service', 'services', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['data' => $bookings], 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'event_name'  => 'required|string',
                'location'    => 'required|string',
                'category'    => 'nullable|string',
                'event_date'  => 'required|date',
                'start_time'  => 'nullable|string', 
                'end_time'    => 'nullable|string',
                'budget'      => 'required|numeric',
                'guest_count' => 'nullable|integer|min:1|max:500',
                'service_id'  => 'nullable|integer|exists:services,id', 
                'bundle_id'   => 'nullable|integer',
                'vendor_id'   => 'nullable|integer|exists:vendors,id',
            ]);

            if ($request->filled('guest_count') && $request->filled('category')) {
                $categoryRates = [
                    'Wedding'       => 800,
                    'Birthday'      => 350,
                    'Corporate'     => 500,
                    'Debut'         => 600,
                    'General Event' => 300,
                    'Catering'      => 600,
                ];

                $ratePerPax = $categoryRates[$request->category] ?? 300;
                $minEstimatedBudget = $request->guest_count * $ratePerPax;

                if ($request->budget < $minEstimatedBudget) {
                    return response()->json([
                        'error' => 'Budget Infeasible',
                        'message' => "The budget (₱" . number_format($request->budget, 2) . ") is below the minimum recommended for {$request->guest_count} guests in {$request->category} (Minimum: ₱" . number_format($minEstimatedBudget, 2) . " based on ₱{$ratePerPax}/head)."
                    ], 422);
                }
            }

            if ($request->service_id && $request->start_time && $request->end_time) {
                $hasConflict = Booking::where('service_id', $request->service_id)
                    ->where('status', 'accepted')
                    ->whereDate('event_date', $request->event_date)
                    ->where(function ($query) use ($request) {
                        $query->where('start_time', '<', $request->end_time)
                              ->where('end_time', '>', $request->start_time);
                    })
                    ->exists();

                if ($hasConflict) {
                    return response()->json([
                        'error' => 'Schedule Conflict',
                        'message' => 'This service provider is already booked for the selected time slot.'
                    ], 422);
                }
            }

            $eventDateOnly = date('Y-m-d', strtotime($request->event_date));
            $formattedEndTime = $eventDateOnly . ' 23:59:00';
            $formattedStartTime = $request->start_time ? ($eventDateOnly . ' ' . $request->start_time) : ($eventDateOnly . ' 08:00:00');

            $resolvedVendorId = $request->vendor_id;
            $resolvedBundleId = $request->bundle_id;

            if ($resolvedBundleId) {
                $bundleRecord = DB::table('bundles')->where('id', $resolvedBundleId)->first();
                if ($bundleRecord && isset($bundleRecord->vendor_id)) {
                    $resolvedVendorId = $bundleRecord->vendor_id;
                }
            }

            $bookingData = array_merge($validated, [
                'status'         => $request->status ?? 'pending',
                'payment_status' => 'Unpaid', 
                'venue_id'       => $request->venue_id ?? 0,
                'service_id'     => $request->service_id ?? null,
                'bundle_id'      => $resolvedBundleId,
                'vendor_id'      => $resolvedVendorId,
                'category'       => $request->category ?? 'Bundle',
                'guest_count'    => $request->guest_count ?? 1,
                'start_time'     => $formattedStartTime,
                'end_time'       => $formattedEndTime,
            ]);

            $booking = Auth::user()->bookings()->create($bookingData);

            if ($resolvedVendorId) {
                $vendorRecord = Vendor::find($resolvedVendorId);
                if ($vendorRecord && $vendorRecord->user_id) {
                    DB::table('notifications')->insert([
                        'user_id'    => $vendorRecord->user_id,
                        'title'      => 'New Booking Request!',
                        'message'    => 'You have received a new booking request for "' . $booking->event_name . '". Check your dashboard to review.',
                        'is_read'    => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Event created successfully!',
                'data'    => $booking
            ], 201);

        } catch (\Exception $e) {
            Log::error("Event Store Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));

            $validated = $request->validate([
                'event_name'  => 'sometimes|required|string',
                'location'    => 'sometimes|required|string',
                'event_date'  => 'sometimes|required|date',
                'start_time'  => 'sometimes|nullable|string', 
                'end_time'    => 'sometimes|required|string',
                'budget'      => 'sometimes|required|numeric',
                'guest_count' => 'sometimes|required|integer',
            ]);

            $booking->update($validated);

            return response()->json([
                'message' => 'Event updated successfully!',
                'data'    => $booking
            ], 200);

        } catch (\Exception $e) {
            Log::error("Event Update Error: " . $e->getMessage());
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    // Bill adjustment method for Phase 4 final pricing negotiations
    public function adjustBill(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));

            $validated = $request->validate([
                'budget'      => 'required|numeric|min:0',
                'guest_count' => 'sometimes|required|integer|min:1',
            ]);

            $booking->update($validated);

            return response()->json([
                'message' => 'Final bill adjusted successfully!',
                'data'    => $booking->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error("Bill Adjustment Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to adjust bill: ' . $e->getMessage()], 500);
        }
    }

    public function attachServices(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));
            
            $validated = $request->validate([
                'services' => 'required|array',
                'services.*' => 'required|integer|exists:services,id'
            ]);

            if (method_exists($booking, 'services')) {
                $booking->services()->sync($validated['services']);
            }

            return response()->json([
                'message' => 'Services attached successfully!',
                'data' => $booking->fresh()->load('services')
            ], 200);
        } catch (\Exception $e) {
            Log::error("Attach Services Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to attach services: ' . $e->getMessage()], 500);
        }
    }
    
    // Safely handles document and photo file attachments up to 5MB without database structure risks
    public function attachDocument(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));

            $request->validate([
                'attachment' => 'required|file|mimes:jpeg,png,jpg,pdf,docx|max:5120',
            ]);

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                
                // Move file to public/uploads
                $file->move(public_path('uploads'), $filename);

                return response()->json([
                    'message' => 'File attached successfully!',
                    'file_url' => url('uploads/' . $filename)
                ], 200);
            }

            return response()->json(['error' => 'No file uploaded.'], 422);
        } catch (\Exception $e) {
            Log::error("Attachment Error: " . $e->getMessage());
            return response()->json(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'booking_id'  => 'required',
            'amount'      => 'required|numeric'
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $cleanId = $this->cleanId($request->booking_id);
                $booking = Booking::with('service')->findOrFail($cleanId);
                
                $booking->payment_status = 'Paid';
                $booking->save(); 

                try {
                    DB::table('notifications')->insert([
                        'user_id'    => Auth::id(),
                        'title'      => 'Payment Confirmed!',
                        'message'    => 'Your payment for ' . $booking->event_name . ' was successful.',
                        'is_read'    => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Notification failed but payment saved.");
                }

                return response()->json([
                    'message' => 'Payment processed successfully', 
                    'data' => $booking->fresh()
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error("Payment Persistence Failure: " . $e->getMessage());
            return response()->json(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
        }
    }

    public function toggleAvailability($id)
    {
        try {
            $vendor = Vendor::where('user_id', Auth::id())->findOrFail($this->cleanId($id));
            $vendor->is_available = !$vendor->is_available;
            $vendor->save();

            return response()->json(['is_available' => $vendor->is_available], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Toggle failed: ' . $e->getMessage()], 500);
        }
    }

    public function vendorBookings()
    {
        try {
            $userId = Auth::id();
            $vendorIds = Vendor::where('user_id', $userId)->pluck('id');

            if ($vendorIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'bookings' => [],
                    'stats' => ['earnings' => 0, 'pending' => 0],
                    'category_distribution' => []
                ], 200);
            }

            $serviceIds = DB::table('services')->where('user_id', $userId)->pluck('id');
            $bundleIds = DB::table('bundles')->whereIn('vendor_id', $vendorIds)->pluck('id');

            $bookings = Booking::with(['user', 'services', 'service'])
                ->where(function ($query) use ($vendorIds, $serviceIds, $bundleIds) {
                    if (Schema::hasColumn('bookings', 'vendor_id')) {
                        $query->whereIn('vendor_id', $vendorIds);
                    }
                    if ($serviceIds->isNotEmpty()) {
                        $query->orWhereIn('service_id', $serviceIds);
                    }
                    if ($bundleIds->isNotEmpty() && Schema::hasColumn('bookings', 'bundle_id')) {
                        $query->orWhereIn('bundle_id', $bundleIds);
                    }
                    $query->orWhereHas('services', function($q) use ($serviceIds) {
                        $q->whereIn('services.id', $serviceIds);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $stats = [
                'earnings' => $bookings->where('payment_status', 'Paid')->sum('budget'),
                'pending' => $bookings->where('status', 'pending')->count()
            ];

            $categoryDistribution = $bookings->groupBy('category')->map->count();

            return response()->json([
                'data' => $bookings,
                'bookings' => $bookings,
                'stats' => $stats,
                'category_distribution' => $categoryDistribution
            ], 200);
        } catch (\Exception $e) {
            Log::error("Vendor Bookings Fetch Error: " . $e->getMessage());
            return response()->json(['error' => 'Vendor fetch failed: ' . $e->getMessage()], 500);
        }
    }

    public function assignVendor(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));
            $targetServiceId = $request->vendor_id;

            $service = Vendor::findOrFail($targetServiceId);

            $booking->service_id = $targetServiceId;
            $booking->status = 'pending'; 
            $booking->save();

            DB::table('notifications')->insert([
                'user_id'    => $service->user_id, 
                'title'      => 'New Hire Request!',
                'message'    => 'You have been hired for event: ' . $booking->event_name . '. Check your dashboard to accept.',
                'is_read'    => 0, 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Vendor hired!', 'data' => $booking->load('service')], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Hiring failed: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));
            $booking->status = $request->status;
            $booking->save();

            if ($request->status === 'accepted') {
                DB::table('notifications')
                    ->where('user_id', Auth::id())
                    ->where('message', 'like', '%' . $booking->event_name . '%')
                    ->update(['is_read' => 1]);
            }

            DB::table('notifications')->insert([
                'user_id'    => $booking->user_id,
                'title'      => 'Booking Update',
                'message'    => "The vendor has " . $request->status . " your request for " . $booking->event_name,
                'is_read'    => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Status updated successfully', 'data' => $booking]);
        } catch (\Exception $e) {
            Log::error("Update Status Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function allBookings()
    {
        try {
            $bookings = Booking::with(['user', 'service', 'services'])
                ->orderBy('created_at', 'desc')
                ->get();

            $categoryDistribution = $bookings->groupBy('category')->map->count();

            return response()->json([
                'data' => $bookings,
                'category_distribution' => $categoryDistribution
            ], 200);
        } catch (\Exception $e) {
            Log::error("Admin All Bookings Error: " . $e->getMessage());
            return response()->json(['error' => 'Admin fetch failed'], 500);
        }
    }

    public function show($id)
    {
        try {
            $booking = Booking::with(['service', 'services', 'user'])->findOrFail($this->cleanId($id));
            return response()->json(['data' => $booking], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Details not found'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::findOrFail($this->cleanId($id));
            $booking->delete();
            return response()->json(['message' => 'Deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Not found.'], 404);
        }
    }

    private function cleanId($id)
    {
        if (is_string($id)) {
            $clean = str_replace('#EE-', '', $id);
            return (int) explode(':', $clean)[0];
        }
        return (int) $id;
    }
}