<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomImage;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function categories()
    {
        $categories = RoomCategory::all();
        return response()->json(['categories' => $categories]);
    }

    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $search = $request->query('search');
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');

        $query = Room::with(['category', 'images', 'amenities'])
            ->where('is_active', 1);

        if ($category && $category !== 'all') {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'LIKE', "%$category%");
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                  ->orWhere('room_number', 'LIKE', "%$search%")
                  ->orWhere('description', 'LIKE', "%$search%");
            });
        }

        $rooms = $query->get()->map(function ($room) use ($checkIn, $checkOut) {
            $isAvailable = true;

            if ($room->status === 'maintenance') {
                $isAvailable = false;
            } elseif ($checkIn && $checkOut) {
                $overlapCount = Booking::where('room_id', $room->id)
                    ->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                    ->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn)
                    ->count();

                if ($overlapCount > 0) {
                    $isAvailable = false;
                }
            } else {
                $isAvailable = ($room->status === 'available');
            }

            $primaryImg = $room->images->where('is_primary', 1)->first() ?: $room->images->first();

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'title' => $room->title,
                'category_name' => $room->category ? $room->category->name : 'Standard',
                'status' => $room->status,
                'housekeeping_status' => $room->housekeeping_status ?: 'inspected',
                'floor' => $room->floor,
                'capacity' => $room->capacity,
                'price_etb' => (float)$room->price_etb,
                'price_usd' => (float)$room->price_usd,
                'description' => $room->description,
                'image_url' => $primaryImg ? $primaryImg->image_url : '/uploads/rooms/default.jpg',
                'is_available' => $isAvailable,
                'avg_rating' => '4.9',
                'amenities' => $room->amenities->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
            ];
        });

        return response()->json(['rooms' => $rooms]);
    }

    public function show($id)
    {
        $room = Room::with(['category', 'images', 'amenities'])
            ->where('id', $id)
            ->orWhere('room_number', $id)
            ->firstOrFail();

        $primaryImg = $room->images->where('is_primary', 1)->first() ?: $room->images->first();

        return response()->json([
            'room' => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'title' => $room->title,
                'category_name' => $room->category ? $room->category->name : 'Suite',
                'status' => $room->status,
                'housekeeping_status' => $room->housekeeping_status ?: 'inspected',
                'floor' => $room->floor,
                'capacity' => $room->capacity,
                'price_etb' => (float)$room->price_etb,
                'price_usd' => (float)$room->price_usd,
                'description' => $room->description,
                'image_url' => $primaryImg ? $primaryImg->image_url : '/uploads/rooms/default.jpg',
                'images' => $room->images->pluck('image_url')->toArray(),
                'amenities' => $room->amenities->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
                'avg_rating' => '4.9',
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|string|unique:rooms,room_number',
            'category_id' => 'required|string',
            'title' => 'required|string',
            'floor' => 'required|integer',
            'capacity' => 'required|integer',
            'price_etb' => 'required|numeric',
            'price_usd' => 'required|numeric',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
        ]);

        $roomId = 'rm-' . time() . '-' . rand(10, 99);

        $room = Room::create([
            'id' => $roomId,
            'room_number' => $validated['room_number'],
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'status' => 'available',
            'housekeeping_status' => 'inspected',
            'floor' => $validated['floor'],
            'capacity' => $validated['capacity'],
            'price_etb' => $validated['price_etb'],
            'price_usd' => $validated['price_usd'],
            'description' => $validated['description'] ?? '',
            'is_active' => 1,
        ]);

        if (!empty($validated['image_url'])) {
            RoomImage::create([
                'id' => 'img-' . time() . '-' . rand(10, 99),
                'room_id' => $roomId,
                'image_url' => $validated['image_url'],
                'is_primary' => 1,
                'display_order' => 1,
            ]);
        }

        return response()->json(['success' => true, 'room' => $room], 201);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);
        $room->update($request->only([
            'title', 'category_id', 'floor', 'capacity',
            'price_etb', 'price_usd', 'description', 'status'
        ]));

        return response()->json(['success' => true, 'room' => $room]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->update(['is_active' => 0]);
        return response()->json(['success' => true, 'message' => 'Room archived successfully.']);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:available,occupied,maintenance']);
        $room = Room::where('id', $id)->orWhere('room_number', $id)->firstOrFail();
        $room->update(['status' => $request->status]);

        return response()->json(['message' => 'Room status updated successfully in MySQL.', 'status' => $request->status]);
    }

    public function getFavorites(Request $request)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';
        $favs = DB::table('user_favorites')->where('user_id', $userId)->pluck('room_id');
        return response()->json(['favorites' => $favs]);
    }

    public function addFavorite(Request $request, $roomId)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';
        DB::table('user_favorites')->updateOrInsert(
            ['user_id' => $userId, 'room_id' => $roomId],
            ['created_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    public function removeFavorite(Request $request, $roomId)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';
        DB::table('user_favorites')->where('user_id', $userId)->where('room_id', $roomId)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Interactive Hotel Visual Floor Map data grouped by floors with live occupant details.
     */
    public function floorMap()
    {
        $rooms = Room::with(['category', 'images', 'bookings' => function ($q) {
            $q->whereIn('booking_status', ['confirmed', 'checked_in'])
              ->where('check_in_date', '<=', now()->toDateString())
              ->where('check_out_date', '>=', now()->toDateString())
              ->with('guest');
        }])
        ->where('is_active', 1)
        ->orderBy('floor', 'asc')
        ->orderByRaw('CAST(room_number AS UNSIGNED) ASC')
        ->get();

        $floors = $rooms->groupBy('floor')->map(function ($floorRooms, $floorNum) {
            return [
                'floor' => (int)$floorNum,
                'floor_name' => $floorNum == 1 ? '1st Floor - Executive Wing' :
                                ($floorNum == 2 ? '2nd Floor - Deluxe Suites' :
                                ($floorNum == 3 ? '3rd Floor - Heritage Balcony' : "Floor {$floorNum} - Premier Wing")),
                'total_rooms' => $floorRooms->count(),
                'available_count' => $floorRooms->where('status', 'available')->count(),
                'occupied_count' => $floorRooms->where('status', 'occupied')->count(),
                'maintenance_count' => $floorRooms->where('status', 'maintenance')->count(),
                'rooms' => $floorRooms->map(function ($r) {
                    $activeBooking = $r->bookings->first();
                    $primaryImg = $r->images->where('is_primary', 1)->first() ?: $r->images->first();
                    return [
                        'id' => $r->id,
                        'room_number' => $r->room_number,
                        'title' => $r->title,
                        'floor' => $r->floor,
                        'status' => $r->status,
                        'housekeeping_status' => $r->housekeeping_status ?: 'inspected',
                        'capacity' => $r->capacity,
                        'price_etb' => (float)$r->price_etb,
                        'category_name' => $r->category?->name ?: 'Deluxe',
                        'image_url' => $primaryImg?->image_url ?: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop',
                        'occupant' => $activeBooking ? [
                            'booking_id' => $activeBooking->id,
                            'reference_code' => $activeBooking->reference_code,
                            'guest_name' => $activeBooking->guest?->full_name ?: 'In-House Guest',
                            'check_in_date' => $activeBooking->check_in_date,
                            'check_out_date' => $activeBooking->check_out_date,
                            'booking_status' => $activeBooking->booking_status,
                        ] : null,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'floors' => $floors,
            'summary' => [
                'total_rooms' => $rooms->count(),
                'available' => $rooms->where('status', 'available')->count(),
                'occupied' => $rooms->where('status', 'occupied')->count(),
                'maintenance' => $rooms->where('status', 'maintenance')->count(),
            ],
        ]);
    }

    /**
     * Get booked date intervals and disabled date strings for a specific room.
     */
    public function bookedDates($id)
    {
        $room = Room::where('id', $id)->orWhere('room_number', $id)->firstOrFail();

        $bookings = Booking::where('room_id', $room->id)
            ->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
            ->where('check_out_date', '>=', now()->toDateString())
            ->get(['id', 'reference_code', 'check_in_date', 'check_out_date']);

        $blockedRanges = [];
        $blockedDates = [];

        foreach ($bookings as $b) {
            $blockedRanges[] = [
                'reference' => $b->reference_code,
                'start' => $b->check_in_date,
                'end' => $b->check_out_date,
            ];

            $period = \Carbon\CarbonPeriod::create($b->check_in_date, \Carbon\Carbon::parse($b->check_out_date)->subDay());
            foreach ($period as $date) {
                $blockedDates[] = $date->format('Y-m-d');
            }
        }

        return response()->json([
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'blocked_ranges' => $blockedRanges,
            'blocked_dates' => array_values(array_unique($blockedDates)),
        ]);
    }
}
