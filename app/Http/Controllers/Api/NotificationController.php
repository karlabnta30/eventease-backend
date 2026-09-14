<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['message' => 'Not authenticated'], 401);
            if (!Schema::hasTable('notifications')) return response()->json([], 200);

            $query = DB::table('notifications');

            if (Schema::hasColumn('notifications', 'user_id')) {
                $query->where('user_id', $user->id);
                if (($user->role ?? 'client') === 'client') {
                    $query->where('message', 'NOT LIKE', '%You have been hired%');
                }
            }

            $data = $query->orderBy('created_at', 'desc')->get();
            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error("Notification Error: " . $e->getMessage());
            return response()->json([], 200);
        }
    }

    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

            DB::table('notifications')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->update(['is_read' => true]);

            return response()->json(['message' => 'Success'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed'], 200);
        }
    }

    /**
     * NEW: Mark all as read for the authenticated user
     */
    public function markAllRead()
    {
        try {
            DB::table('notifications')
                ->where('user_id', Auth::id())
                ->update(['is_read' => true]);
            return response()->json(['message' => 'All marked as read'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Update failed'], 500);
        }
    }

    /**
     * NEW: Delete all notifications for the authenticated user
     */
    public function deleteAll()
    {
        try {
            DB::table('notifications')
                ->where('user_id', Auth::id())
                ->delete();
            return response()->json(['message' => 'All deleted'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Delete failed'], 500);
        }
    }
}