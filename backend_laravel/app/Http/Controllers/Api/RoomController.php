<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomImage;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RoomController extends Controller
{
    /**
     * List all room categories with metadata, room counts, base prices and max capacities.
     */
    public function categories()
    {
        $categories = RoomCategory::withCount(['rooms as active_rooms_count' => function ($q) {
            $q->where('is_active', 1);
        }, 'rooms as total_rooms_count'])
        ->orderBy('base_price_etb', 'asc')
        ->get()
        ->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'badge_color' => $cat->badge_color ?: '#0B4D34',
                'description' => $cat->description,
                'base_price_etb' => (float)$cat->base_price_etb,
                'base_price_usd' => (float)$cat->base_price_usd,
                'max_capacity' => (int)($cat->max_capacity ?: 2),
                'active_rooms_count' => (int)$cat->active_rooms_count,
                'total_rooms_count' => (int)$cat->total_rooms_count,
                'created_at' => $cat->created_at,
            ];
        });

        return response()->json(['categories' => $categories]);
    }

    /**
     * Create a new room type / category.
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:room_categories,name',
            'slug' => 'nullable|string|max:60|unique:room_categories,slug',
            'base_price_etb' => 'required|numeric|gt:0',
            'base_price_usd' => 'nullable|numeric|gt:0',
            'max_capacity' => 'required|integer|min:1|max:20',
            'badge_color' => 'nullable|string|max:30',
            'description' => 'nullable|string',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        
        // Ensure slug uniqueness fallback
        if (RoomCategory::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(10, 99);
        }

        $priceUsd = !empty($validated['base_price_usd']) 
            ? (float)$validated['base_price_usd'] 
            : round((float)$validated['base_price_etb'] / 64, 2);

        $categoryId = 'cat-' . $slug;
        if (RoomCategory::where('id', $categoryId)->exists()) {
            $categoryId = 'cat-' . time() . '-' . rand(10, 99);
        }

        $category = RoomCategory::create([
            'id' => $categoryId,
            'name' => $validated['name'],
            'slug' => $slug,
            'badge_color' => $validated['badge_color'] ?? '#0B4D34',
            'description' => $validated['description'] ?? '',
            'base_price_etb' => $validated['base_price_etb'],
            'base_price_usd' => $priceUsd,
            'max_capacity' => $validated['max_capacity'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Room type '{$category->name}' created successfully.",
            'category' => $category,
        ], 201);
    }

    /**
     * Update an existing room type / category.
     */
    public function updateCategory(Request $request, $id)
    {
        $category = RoomCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:room_categories,name,' . $category->id,
            'slug' => 'nullable|string|max:60|unique:room_categories,slug,' . $category->id,
            'base_price_etb' => 'required|numeric|gt:0',
            'base_price_usd' => 'nullable|numeric|gt:0',
            'max_capacity' => 'required|integer|min:1|max:20',
            'badge_color' => 'nullable|string|max:30',
            'description' => 'nullable|string',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : $category->slug;
        $priceUsd = !empty($validated['base_price_usd']) 
            ? (float)$validated['base_price_usd'] 
            : round((float)$validated['base_price_etb'] / 64, 2);

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'badge_color' => $validated['badge_color'] ?? $category->badge_color,
            'description' => $validated['description'] ?? $category->description,
            'base_price_etb' => $validated['base_price_etb'],
            'base_price_usd' => $priceUsd,
            'max_capacity' => $validated['max_capacity'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Room type '{$category->name}' updated successfully.",
            'category' => $category,
        ]);
    }

    /**
     * Delete a room category if no rooms are assigned to it.
     */
    public function destroyCategory($id)
    {
        $category = RoomCategory::findOrFail($id);

        $roomsAssigned = $category->rooms()->count();
        if ($roomsAssigned > 0) {
            return response()->json([
                'error' => "Cannot delete room type '{$category->name}' because {$roomsAssigned} hotel rooms are assigned to it. Please reassign those rooms to another type first."
            ], 422);
        }

        $name = $category->name;
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "Room type '{$name}' deleted successfully."
        ]);
    }

    /**
     * List all system amenities.
     */
    public function amenities()
    {
        $amenities = Amenity::orderBy('name', 'asc')->get();
        return response()->json(['amenities' => $amenities]);
    }

    /**
     * List rooms with dynamic availability, operational statuses, filters, and optional pagination.
     */
    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $search = $request->query('search');
        $statusFilter = $request->query('status', 'all');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $floor = $request->query('floor');
        $capacity = $request->query('capacity');
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');
        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

        $query = Room::with(['category', 'images', 'amenities']);

        if (!$includeInactive) {
            $query->where('is_active', 1);
        }

        // Category filter
        if ($category && $category !== 'all') {
            $query->where(function ($q) use ($category) {
                $q->where('category_id', $category)
                  ->orWhereHas('category', function ($sub) use ($category) {
                      $sub->where('slug', $category)->orWhere('name', 'LIKE', "%$category%");
                  });
            });
        }

        // Search filter (number, title, description)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                  ->orWhere('room_number', 'LIKE', "%$search%")
                  ->orWhere('description', 'LIKE', "%$search%");
            });
        }

        // Price range
        if ($minPrice && is_numeric($minPrice)) {
            $query->where('price_etb', '>=', (float)$minPrice);
        }
        if ($maxPrice && is_numeric($maxPrice)) {
            $query->where('price_etb', '<=', (float)$maxPrice);
        }

        // Floor filter
        if ($floor && is_numeric($floor)) {
            $query->where('floor', (int)$floor);
        }

        // Capacity filter
        if ($capacity && is_numeric($capacity)) {
            $query->where('capacity', '>=', (int)$capacity);
        }

        $today = now()->toDateString();

        // Retrieve rooms ordered logically by floor and room number
        $allRooms = $query->orderBy('floor', 'asc')
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC')
            ->get();

        $todayBookings = Booking::with('bookingRooms')->where(function ($q) {
                $q->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                  ->orWhere(function ($sub) {
                      $sub->where('booking_status', 'pending_payment')
                          ->where(function ($h) {
                              $h->whereNull('hold_expires_at')
                                ->orWhere('hold_expires_at', '>', now());
                          });
                  });
            })
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>=', $today)
            ->get(['id', 'room_id', 'booking_status', 'check_in_date', 'check_out_date', 'reference_code']);

        $dateRangeOverlaps = collect();
        if ($checkIn && $checkOut) {
            $dateRangeOverlaps = Booking::with('bookingRooms')->where(function ($q) {
                    $q->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                      ->orWhere(function ($sub) {
                          $sub->where('booking_status', 'pending_payment')
                              ->where(function ($h) {
                                  $h->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now());
                              });
                      });
                })
                ->where('check_in_date', '<', $checkOut)
                ->where('check_out_date', '>', $checkIn)
                ->get(['id', 'room_id', 'booking_status', 'check_in_date', 'check_out_date']);
        }

        $mappedRooms = $allRooms->map(function ($room) use ($today, $todayBookings, $checkIn, $checkOut, $dateRangeOverlaps) {
            // Calculate dynamic operational status
            $dynamicStatus = 'available';

            if ($room->status === 'maintenance' || $room->housekeeping_status === 'maintenance') {
                $dynamicStatus = 'maintenance';
            } else {
                $activeToday = $todayBookings->first(function ($b) use ($room) {
                    return $b->room_id === $room->id || $b->bookingRooms->contains('room_id', $room->id);
                });
                if ($activeToday && $activeToday->booking_status === 'checked_in') {
                    $dynamicStatus = 'occupied';
                } elseif ($room->status === 'occupied') {
                    $dynamicStatus = 'occupied';
                } elseif ($room->status === 'cleaning' || $room->housekeeping_status === 'dirty') {
                    $dynamicStatus = 'cleaning';
                } elseif ($activeToday && in_array($activeToday->booking_status, ['confirmed', 'pending_verification'])) {
                    $dynamicStatus = 'reserved';
                } elseif ($room->status === 'reserved') {
                    $dynamicStatus = 'reserved';
                } else {
                    $dynamicStatus = 'available';
                }
            }

            // Calculate dynamic availability
            $isAvailable = true;
            $availabilityReason = 'Available for assignment';

            if ($dynamicStatus === 'maintenance') {
                $isAvailable = false;
                $availabilityReason = 'Room is currently under maintenance';
            } elseif ($checkIn && $checkOut) {
                $hasCollision = $dateRangeOverlaps->some(function ($b) use ($room) {
                    return $b->room_id === $room->id || $b->bookingRooms->contains('room_id', $room->id);
                });
                if ($hasCollision) {
                    $isAvailable = false;
                    $availabilityReason = 'Room is reserved for overlapping dates';
                } elseif ($checkIn <= $today && ($dynamicStatus === 'cleaning' || $room->housekeeping_status === 'dirty')) {
                    $isAvailable = false;
                    $availabilityReason = 'Room is currently being cleaned / awaiting housekeeping inspection';
                }
            } else {
                if ($dynamicStatus !== 'available') {
                    $isAvailable = false;
                    $availabilityReason = match ($dynamicStatus) {
                        'occupied' => 'Room is currently occupied by an in-house guest',
                        'reserved' => 'Room is reserved for today',
                        'cleaning' => 'Room is currently undergoing housekeeping turnover',
                        default => 'Room is currently not available',
                    };
                }
            }

            $primaryImg = $room->images->where('is_primary', 1)->first() ?: $room->images->first();

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'title' => $room->title,
                'category_id' => $room->category_id,
                'category_name' => $room->category ? $room->category->name : 'Standard',
                'category_slug' => $room->category ? $room->category->slug : 'standard',
                'badge_color' => $room->category ? $room->category->badge_color : '#0B4D34',
                'status' => $room->status,
                'dynamic_status' => $dynamicStatus,
                'housekeeping_status' => $room->housekeeping_status ?: 'inspected',
                'floor' => (int)$room->floor,
                'capacity' => (int)$room->capacity,
                'price_etb' => (float)$room->price_etb,
                'price_usd' => (float)$room->price_usd,
                'description' => $room->description ?: '',
                'is_active' => (bool)$room->is_active,
                'image_url' => $primaryImg ? $primaryImg->image_url : '/uploads/rooms/default.jpg',
                'images' => $room->images->pluck('image_url')->values()->toArray(),
                'is_available' => $isAvailable,
                'availability_reason' => $availabilityReason,
                'amenities' => $room->amenities->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'icon_name' => $a->icon_name,
                ])->values()->toArray(),
            ];
        });

        // Filter by operational status if requested
        if ($statusFilter && $statusFilter !== 'all') {
            $mappedRooms = $mappedRooms->filter(function ($r) use ($statusFilter) {
                if ($statusFilter === 'available') {
                    return $r['dynamic_status'] === 'available';
                }
                if ($statusFilter === 'reserved') {
                    return $r['dynamic_status'] === 'reserved';
                }
                if ($statusFilter === 'occupied') {
                    return $r['dynamic_status'] === 'occupied';
                }
                if ($statusFilter === 'maintenance') {
                    return $r['dynamic_status'] === 'maintenance';
                }
                if ($statusFilter === 'cleaning') {
                    return $r['dynamic_status'] === 'cleaning';
                }
                if ($statusFilter === 'inactive') {
                    return !$r['is_active'];
                }
                return true;
            })->values();
        }

        // Summary counts
        $summary = [
            'total_rooms' => $allRooms->count(),
            'active_rooms' => $allRooms->where('is_active', 1)->count(),
            'inactive_rooms' => $allRooms->where('is_active', 0)->count(),
            'available_count' => $mappedRooms->where('dynamic_status', 'available')->where('is_active', true)->count(),
            'reserved_count' => $mappedRooms->where('dynamic_status', 'reserved')->count(),
            'occupied_count' => $mappedRooms->where('dynamic_status', 'occupied')->count(),
            'cleaning_count' => $mappedRooms->where('dynamic_status', 'cleaning')->count(),
            'maintenance_count' => $mappedRooms->where('dynamic_status', 'maintenance')->count(),
        ];

        // Optional Pagination
        $perPage = $request->query('per_page');
        if ($perPage && is_numeric($perPage) && (int)$perPage > 0) {
            $perPage = (int)$perPage;
            $page = max(1, (int)$request->query('page', 1));
            $totalCount = $mappedRooms->count();
            $pagedRooms = $mappedRooms->slice(($page - 1) * $perPage, $perPage)->values();

            return response()->json([
                'rooms' => $pagedRooms,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'last_page' => max(1, (int)ceil($totalCount / $perPage)),
                ]
            ]);
        }

        return response()->json([
            'rooms' => $mappedRooms->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * Show single room details with amenities, category specs, and recent bookings history.
     */
    public function show($id)
    {
        $room = Room::with([
            'category',
            'images',
            'amenities',
            'bookings' => function ($q) {
                $q->with('guest')
                  ->orderBy('check_in_date', 'desc')
                  ->limit(5);
            },
            'bookingRooms.booking.guest',
        ])
        ->where('id', $id)
        ->orWhere('room_number', $id)
        ->firstOrFail();

        $primaryImg = $room->images->where('is_primary', 1)->first() ?: $room->images->first();

        // Calculate dynamic status
        $today = now()->toDateString();
        $activeBooking = $room->bookings->first(function ($b) use ($today) {
            return in_array($b->booking_status, ['checked_in', 'confirmed'])
                && $b->check_in_date <= $today
                && $b->check_out_date >= $today;
        });

        if (!$activeBooking) {
            $brActive = $room->bookingRooms->first(function ($br) use ($today) {
                $b = $br->booking;
                return $b && in_array($b->booking_status, ['checked_in', 'confirmed'])
                    && $b->check_in_date <= $today
                    && $b->check_out_date >= $today;
            });
            if ($brActive) {
                $activeBooking = $brActive->booking;
            }
        }

        $dynamicStatus = 'available';
        if ($room->status === 'maintenance' || $room->housekeeping_status === 'maintenance') {
            $dynamicStatus = 'maintenance';
        } elseif ($activeBooking && $activeBooking->booking_status === 'checked_in') {
            $dynamicStatus = 'occupied';
        } elseif ($room->status === 'occupied') {
            $dynamicStatus = 'occupied';
        } elseif ($room->status === 'cleaning' || $room->housekeeping_status === 'dirty') {
            $dynamicStatus = 'cleaning';
        } elseif ($activeBooking && $activeBooking->booking_status === 'confirmed') {
            $dynamicStatus = 'reserved';
        } elseif ($room->status === 'reserved') {
            $dynamicStatus = 'reserved';
        }

        return response()->json([
            'room' => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'title' => $room->title,
                'category_id' => $room->category_id,
                'category_name' => $room->category ? $room->category->name : 'Standard',
                'category_slug' => $room->category ? $room->category->slug : 'standard',
                'badge_color' => $room->category ? $room->category->badge_color : '#0B4D34',
                'status' => $room->status,
                'dynamic_status' => $dynamicStatus,
                'housekeeping_status' => $room->housekeeping_status ?: 'inspected',
                'floor' => (int)$room->floor,
                'capacity' => (int)$room->capacity,
                'price_etb' => (float)$room->price_etb,
                'price_usd' => (float)$room->price_usd,
                'description' => $room->description ?: '',
                'is_active' => (bool)$room->is_active,
                'image_url' => $primaryImg ? $primaryImg->image_url : '/uploads/rooms/default.jpg',
                'images' => $room->images->pluck('image_url')->values()->toArray(),
                'amenities' => $room->amenities->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'icon_name' => $a->icon_name,
                ])->values()->toArray(),
                'recent_bookings' => $room->bookings->map(fn($b) => [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'guest_name' => $b->guest?->full_name ?: 'Valued Guest',
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'booking_status' => $b->booking_status,
                    'total_price_etb' => (float)$b->total_price_etb,
                ])->values()->toArray(),
            ]
        ]);
    }

    /**
     * Add a new hotel room with comprehensive validations, amenities and pricing.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|string|max:20|unique:rooms,room_number',
            'category_id' => 'required|string|exists:room_categories,id',
            'title' => 'required|string|max:150',
            'floor' => 'required|integer|min:1|max:99',
            'capacity' => 'required|integer|min:1|max:20',
            'price_etb' => 'required|numeric|gt:0',
            'price_usd' => 'nullable|numeric|gt:0',
            'status' => 'nullable|in:available,reserved,occupied,maintenance,cleaning',
            'housekeeping_status' => 'nullable|in:inspected,clean,dirty,maintenance',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|exists:amenities,id',
        ]);

        $priceUsd = !empty($validated['price_usd']) 
            ? (float)$validated['price_usd'] 
            : round((float)$validated['price_etb'] / 64, 2);

        $initialStatus = $validated['status'] ?? 'available';
        $housekeeping = $validated['housekeeping_status'] ?? ($initialStatus === 'cleaning' ? 'dirty' : 'inspected');

        $roomId = 'rm-' . trim($validated['room_number']);
        if (Room::where('id', $roomId)->exists()) {
            $roomId = 'rm-' . time() . '-' . rand(10, 99);
        }

        return DB::transaction(function () use ($validated, $roomId, $priceUsd, $initialStatus, $housekeeping, $request) {
            $room = Room::create([
                'id' => $roomId,
                'room_number' => trim($validated['room_number']),
                'category_id' => $validated['category_id'],
                'title' => trim($validated['title']),
                'status' => $initialStatus,
                'housekeeping_status' => $housekeeping,
                'floor' => $validated['floor'],
                'capacity' => $validated['capacity'],
                'price_etb' => $validated['price_etb'],
                'price_usd' => $priceUsd,
                'description' => $validated['description'] ?? '',
                'is_active' => 1,
            ]);

            // Associate amenities
            if (!empty($validated['amenities'])) {
                $room->amenities()->sync($validated['amenities']);
            }

            // Associate primary image
            if (!empty($validated['image_url'])) {
                RoomImage::create([
                    'id' => 'img-' . time() . '-' . rand(10, 99),
                    'room_id' => $roomId,
                    'image_url' => $validated['image_url'],
                    'is_primary' => 1,
                    'display_order' => 1,
                ]);
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-admin-1',
                'user_name' => $request->user()?->full_name ?: 'System Administrator',
                'user_role' => $request->user()?->role ?: 'super_admin',
                'action' => 'create_room',
                'resource_type' => 'room',
                'resource_id' => $room->id,
                'details' => ['room_number' => $room->room_number, 'category' => $room->category_id, 'price_etb' => $room->price_etb],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Room {$room->room_number} registered successfully in the hotel inventory.",
                'room' => $room->load(['category', 'amenities', 'images'])
            ], 201);
        });
    }

    /**
     * Update room details with uniqueness, capacity, price, and amenities validation.
     */
    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'room_number' => 'sometimes|required|string|max:20|unique:rooms,room_number,' . $room->id,
            'category_id' => 'sometimes|required|string|exists:room_categories,id',
            'title' => 'sometimes|required|string|max:150',
            'floor' => 'sometimes|required|integer|min:1|max:99',
            'capacity' => 'sometimes|required|integer|min:1|max:20',
            'price_etb' => 'sometimes|required|numeric|gt:0',
            'price_usd' => 'nullable|numeric|gt:0',
            'status' => 'sometimes|required|in:available,reserved,occupied,maintenance,cleaning',
            'housekeeping_status' => 'nullable|in:inspected,clean,dirty,maintenance',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|exists:amenities,id',
        ]);

        return DB::transaction(function () use ($room, $validated, $request) {
            $updateData = [];

            if (isset($validated['room_number'])) $updateData['room_number'] = trim($validated['room_number']);
            if (isset($validated['category_id'])) $updateData['category_id'] = $validated['category_id'];
            if (isset($validated['title'])) $updateData['title'] = trim($validated['title']);
            if (isset($validated['floor'])) $updateData['floor'] = $validated['floor'];
            if (isset($validated['capacity'])) $updateData['capacity'] = $validated['capacity'];
            if (isset($validated['price_etb'])) {
                $updateData['price_etb'] = $validated['price_etb'];
                $updateData['price_usd'] = !empty($validated['price_usd']) 
                    ? (float)$validated['price_usd'] 
                    : round((float)$validated['price_etb'] / 64, 2);
            }
            if (isset($validated['description'])) $updateData['description'] = $validated['description'];
            if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

            // Status transitions & housekeeping linkage
            if (isset($validated['status'])) {
                $newStatus = $validated['status'];
                $updateData['status'] = $newStatus;

                if ($newStatus === 'maintenance') {
                    $updateData['housekeeping_status'] = 'maintenance';
                } elseif ($newStatus === 'cleaning') {
                    $updateData['housekeeping_status'] = 'dirty';
                } elseif ($newStatus === 'available') {
                    if ($room->status === 'occupied') {
                        // Prevent marking occupied room available without checkout
                        return response()->json(['error' => 'Cannot mark an occupied room as available directly. Please process guest check-out at the Front Desk.'], 422);
                    }
                    $updateData['housekeeping_status'] = 'inspected';
                }
            }

            if (!empty($validated['housekeeping_status'])) {
                $updateData['housekeeping_status'] = $validated['housekeeping_status'];
            }

            $room->update($updateData);

            // Sync amenities if provided
            if (array_key_exists('amenities', $validated)) {
                $room->amenities()->sync($validated['amenities'] ?: []);
            }

            // Update primary image if provided
            if (!empty($validated['image_url'])) {
                $primary = $room->images()->where('is_primary', 1)->first();
                if ($primary) {
                    $primary->update(['image_url' => $validated['image_url']]);
                } else {
                    RoomImage::create([
                        'id' => 'img-' . time() . '-' . rand(10, 99),
                        'room_id' => $room->id,
                        'image_url' => $validated['image_url'],
                        'is_primary' => 1,
                        'display_order' => 1,
                    ]);
                }
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-admin-1',
                'user_name' => $request->user()?->full_name ?: 'System Administrator',
                'user_role' => $request->user()?->role ?: 'super_admin',
                'action' => 'update_room',
                'resource_type' => 'room',
                'resource_id' => $room->id,
                'details' => ['room_number' => $room->room_number, 'updates' => array_keys($updateData)],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Room {$room->room_number} details updated successfully.",
                'room' => $room->load(['category', 'amenities', 'images'])
            ]);
        });
    }

    /**
     * Delete or deactivate room following strict hotel business rules:
     * - An occupied room cannot be deleted or deactivated.
     * - A room with historical bookings is deactivated to preserve financial & audit history.
     * - A room with NO historical bookings is permanently deleted.
     */
    public function destroy(Request $request, $id)
    {
        $room = Room::with('bookings')->findOrFail($id);

        // 1. Prevent deleting or deactivating occupied rooms
        $today = now()->toDateString();
        $isOccupied = ($room->status === 'occupied') || $room->bookings()
            ->where('booking_status', 'checked_in')
            ->exists();

        if ($isOccupied) {
            return response()->json([
                'error' => "Cannot delete or deactivate Room {$room->room_number} because it is currently occupied by an in-house guest. Process guest departure first."
            ], 422);
        }

        $bookingCount = $room->bookings()->count();

        // 2. If historical bookings exist, deactivate instead of permanently deleting
        if ($bookingCount > 0) {
            $room->update(['is_active' => 0]);

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-admin-1',
                'user_name' => $request->user()?->full_name ?: 'System Administrator',
                'user_role' => $request->user()?->role ?: 'super_admin',
                'action' => 'deactivate_room_with_history',
                'resource_type' => 'room',
                'resource_id' => $room->id,
                'details' => ['room_number' => $room->room_number, 'booking_count' => $bookingCount],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'action' => 'deactivated',
                'message' => "Room {$room->room_number} has {$bookingCount} associated reservation record(s). It has been deactivated/archived to maintain financial ledger and guest transaction integrity."
            ]);
        }

        // 3. No historical records -> permanently delete cleanly
        return DB::transaction(function () use ($room, $request) {
            $roomNumber = $room->room_number;
            DB::table('user_favorites')->where('room_id', $room->id)->delete();
            $room->amenities()->detach();
            $room->images()->delete();
            $room->delete();

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-admin-1',
                'user_name' => $request->user()?->full_name ?: 'System Administrator',
                'user_role' => $request->user()?->role ?: 'super_admin',
                'action' => 'permanently_delete_room',
                'resource_type' => 'room',
                'resource_id' => $roomNumber,
                'details' => ['room_number' => $roomNumber, 'reason' => 'Zero historical records'],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'action' => 'deleted',
                'message' => "Room {$roomNumber} had no historical records and was permanently removed from the system."
            ]);
        });
    }

    /**
     * Explicitly toggle room active/deactivated state.
     */
    public function toggleActive(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        if ($room->is_active) {
            // Check if occupied before deactivating
            if ($room->status === 'occupied') {
                return response()->json(['error' => "Cannot deactivate Room {$room->room_number} while it is occupied."], 422);
            }
            $room->update(['is_active' => 0]);
            $msg = "Room {$room->room_number} is now deactivated (archived).";
        } else {
            $room->update(['is_active' => 1]);
            $msg = "Room {$room->room_number} is now reactivated and available for booking.";
        }

        return response()->json([
            'success' => true,
            'is_active' => (bool)$room->is_active,
            'message' => $msg,
        ]);
    }

    /**
     * Update room operational status directly with hotel business logic rules.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,reserved,occupied,maintenance,cleaning',
        ]);

        $room = Room::where('id', $id)->orWhere('room_number', $id)->firstOrFail();
        $targetStatus = $request->status;

        if ($targetStatus === 'available' && $room->status === 'occupied') {
            return response()->json(['error' => 'Cannot set an occupied room to available directly. Please process guest check-out.'], 422);
        }

        $room->status = $targetStatus;

        if ($targetStatus === 'maintenance') {
            $room->housekeeping_status = 'maintenance';
        } elseif ($targetStatus === 'cleaning') {
            $room->housekeeping_status = 'dirty';
        } elseif ($targetStatus === 'available') {
            $room->housekeeping_status = 'inspected';
        }

        $room->save();

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'usr-admin-1',
            'user_name' => $request->user()?->full_name ?: 'System Administrator',
            'user_role' => $request->user()?->role ?: 'super_admin',
            'action' => 'update_room_status',
            'resource_type' => 'room',
            'resource_id' => $room->id,
            'details' => ['room_number' => $room->room_number, 'new_status' => $targetStatus],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Room {$room->room_number} status updated to '{$targetStatus}'.",
            'status' => $targetStatus,
            'housekeeping_status' => $room->housekeeping_status,
        ]);
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
                'cleaning_count' => $floorRooms->where('status', 'cleaning')->count(),
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
                'cleaning' => $rooms->where('status', 'cleaning')->count(),
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
            ->where(function ($q) {
                $q->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                  ->orWhere(function ($sub) {
                      $sub->where('booking_status', 'pending_payment')
                          ->where(function ($h) {
                              $h->whereNull('hold_expires_at')
                                ->orWhere('hold_expires_at', '>', now());
                          });
                  });
            })
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
