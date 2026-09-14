<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function getConversation($userId)
    {
        try {
            $currentUserId = Auth::id();
            
            $messages = Message::where(function($query) use ($currentUserId, $userId) {
                    $query->where('sender_id', $currentUserId)->where('receiver_id', $userId);
                })
                ->orWhere(function($query) use ($currentUserId, $userId) {
                    $query->where('sender_id', $userId)->where('receiver_id', $currentUserId);
                })
                ->orderBy('created_at', 'asc')
                ->get();

            $currentUserVendorIds = Vendor::where('user_id', $currentUserId)->pluck('id')->toArray();
            $targetVendorIds = Vendor::where('user_id', $userId)->pluck('id')->toArray();

            $booking = Booking::where(function($q) use ($currentUserId, $targetVendorIds) {
                $q->where('bookings.user_id', $currentUserId);
                if (!empty($targetVendorIds)) {
                    $q->where(function($sub) use ($targetVendorIds) {
                        $sub->whereIn('bookings.service_id', $targetVendorIds)
                            ->orWhereHas('services', function($s) use ($targetVendorIds) {
                                $s->whereIn('services.id', $targetVendorIds);
                            });
                    });
                } else {
                    $q->whereRaw('0 = 1');
                }
            })->orWhere(function($q) use ($userId, $currentUserVendorIds) {
                $q->where('bookings.user_id', $userId);
                if (!empty($currentUserVendorIds)) {
                    $q->where(function($sub) use ($currentUserVendorIds) {
                        $sub->whereIn('bookings.service_id', $currentUserVendorIds)
                            ->orWhereHas('services', function($s) use ($currentUserVendorIds) {
                                $s->whereIn('services.id', $currentUserVendorIds);
                            });
                    });
                } else {
                    $q->whereRaw('0 = 1');
                }
            })->latest()->first();

            return response()->json([
                'messages' => $messages,
                'booking' => $booking
            ]);
        } catch (\Exception $e) {
            Log::error("Get Conversation Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch conversation: ' . $e->getMessage()], 500);
        }
    }

    // Send a message with active booking validation
    public function sendMessage(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['error' => 'Admins have read-only access and cannot send messages.'], 403);
        }

        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        $receiverId = $request->receiver_id;
        $userVendorIds = Vendor::where('user_id', $user->id)->pluck('id')->toArray();
        $receiverVendorIds = Vendor::where('user_id', $receiverId)->pluck('id')->toArray();

        $bookingQuery = Booking::query();

        $bookingQuery->where(function($q) use ($user, $receiverVendorIds) {
            $q->where('bookings.user_id', $user->id);
            if (!empty($receiverVendorIds)) {
                $q->where(function($sub) use ($receiverVendorIds) {
                    $sub->whereIn('bookings.service_id', $receiverVendorIds)
                        ->orWhereHas('services', fn($s) => $s->whereIn('services.id', $receiverVendorIds));
                });
            } else {
                $q->whereRaw('0 = 1');
            }
        });

        $bookingQuery->orWhere(function($q) use ($receiverId, $userVendorIds) {
            $q->where('bookings.user_id', $receiverId);
            if (!empty($userVendorIds)) {
                $q->where(function($sub) use ($userVendorIds) {
                    $sub->whereIn('bookings.service_id', $userVendorIds)
                        ->orWhereHas('services', fn($s) => $s->whereIn('services.id', $userVendorIds));
                });
            } else {
                $q->whereRaw('0 = 1');
            }
        });

        $hasBooking = $bookingQuery->exists();

        if (!$hasBooking) {
            return response()->json(['error' => 'You can only message users with whom you have a confirmed booking.'], 403);
        }

        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $request->message,
        ]);

        return response()->json(['success' => true, 'message' => $message]);
    }

    // Return contacts sharing a booking relationship
    public function getContacts(Request $request)
    {
        try {
            $user = Auth::user();

            if ($user->role === 'admin') {
                $contacts = User::where('id', '!=', $user->id)->get();
                return response()->json($contacts);
            }

            if ($user->role === 'client') {
                $directVendorIds = Booking::where('bookings.user_id', $user->id)
                    ->whereNotNull('bookings.service_id')
                    ->pluck('service_id');

                $directUserIds = !$directVendorIds->isEmpty() ? Vendor::whereIn('id', $directVendorIds)->pluck('user_id') : collect();

                $pivotUserIds = Booking::where('bookings.user_id', $user->id)
                    ->whereHas('services')
                    ->with('services')
                    ->get()
                    ->flatMap(fn($b) => $b->services->pluck('user_id'))
                    ->filter();

                $allContactUserIds = $directUserIds->merge($pivotUserIds)->unique()->toArray();
                $contacts = !empty($allContactUserIds) ? User::whereIn('id', $allContactUserIds)->get() : collect();

                return response()->json($contacts);
            } 
            
            if ($user->role === 'vendor') {
                $vendorIds = Vendor::where('user_id', $user->id)->pluck('id')->toArray();

                if (empty($vendorIds)) {
                    return response()->json([]);
                }

                $clientIdsFromDirect = Booking::whereIn('bookings.service_id', $vendorIds)->pluck('user_id');
                
                $clientIdsFromPivot = Booking::whereHas('services', function($q) use ($vendorIds) {
                    $q->whereIn('services.id', $vendorIds);
                })->pluck('user_id');

                $allClientIds = $clientIdsFromDirect->merge($clientIdsFromPivot)->unique()->toArray();
                $contacts = !empty($allClientIds) ? User::whereIn('id', $allClientIds)->get() : collect();

                return response()->json($contacts);
            }

            return response()->json([]);
        } catch (\Exception $e) {
            Log::error("Get Contacts Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch contacts: ' . $e->getMessage()], 500);
        }
    }
}