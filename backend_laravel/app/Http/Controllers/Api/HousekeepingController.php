<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    /**
     * List all active rooms with their real-time housekeeping cleanliness status.
     */
    public function index()
    {
        $rooms = Room::with('category')
            ->where('is_active', 1)
            ->orderBy('floor', 'asc')
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'room_number' => $r->room_number,
                    'title' => $r->title,
                    'floor' => $r->floor,
                    'status' => $r->status,
                    'housekeeping_status' => $r->housekeeping_status ?: 'inspected',
                    'category_name' => $r->category?->name ?: 'Deluxe',
                    'updated_at' => $r->updated_at,
                ];
            });

        return response()->json(['rooms' => $rooms]);
    }

    /**
     * Update room housekeeping cleanliness state and log action to audit trail.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'housekeeping_status' => 'required|in:inspected,clean,dirty,maintenance',
        ]);

        $room = Room::where('id', $id)
            ->orWhere('room_number', $id)
            ->firstOrFail();

        $oldStatus = $room->housekeeping_status;
        $room->housekeeping_status = $validated['housekeeping_status'];
        $room->save();

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'usr-staff-1',
            'user_name' => $request->user()?->full_name ?: 'Housekeeping Staff',
            'user_role' => $request->user()?->role ?: 'housekeeper',
            'action' => 'housekeeping_status_change',
            'resource_type' => 'room',
            'resource_id' => $room->id,
            'details' => [
                'room_number' => $room->room_number,
                'old_status' => $oldStatus,
                'new_status' => $validated['housekeeping_status'],
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'room_id' => $room->id,
            'housekeeping_status' => $room->housekeeping_status,
            'message' => "Room {$room->room_number} marked as {$room->housekeeping_status}.",
        ]);
    }
}
