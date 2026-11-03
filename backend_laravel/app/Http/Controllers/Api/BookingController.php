<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Payment;
use App\Models\BookingServiceCharge;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Validate promotional codes.
     */
    public function validatePromo(Request $request)
    {
        $promoCode = $request->input('promo_code');
        if (!$promoCode) {
            return response()->json(['error' => 'Promo code is required.'], 400);
        }

        $code = strtoupper(trim($promoCode));
        $validPromos = [
            'LUXURYETHIOPIA' => ['discount_percent' => 15, 'title' => '15% Royal VIP Ethiopian Discount'],
            'HOLIDAY2026' => ['discount_percent' => 10, 'title' => '10% Seasonal Holiday Getaway'],
            'TELEBIRR5' => ['discount_percent' => 5, 'title' => '5% Telebirr Partner Privilege'],
        ];

        if (isset($validPromos[$code])) {
            return response()->json([
                'valid' => true,
                'code' => $code,
                'discount_percent' => $validPromos[$code]['discount_percent'],
                'title' => $validPromos[$code]['title'],
            ]);
        }

        return response()->json(['valid' => false, 'error' => 'Invalid or expired promotional code.'], 404);
    }

    /**
     * Real-time Room Availability Search.
     * Supports search by dates, room type, guest count, price range, and number of rooms required.
     * Displays available rooms and unavailable rooms with explicit operational conflict reasons.
     */
    public function searchAvailability(Request $request)
    {
        $validated = $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'category_id' => 'nullable|string',
            'guests' => 'nullable|integer|min:1',
            'rooms_required' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'floor' => 'nullable|integer|min:1',
        ]);

        $checkIn = $validated['check_in'];
        $checkOut = $validated['check_out'];
        $categoryId = $validated['category_id'] ?? null;
        $minGuests = (int)($validated['guests'] ?? 1);
        $roomsRequired = (int)($validated['rooms_required'] ?? 1);
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $floor = $validated['floor'] ?? null;

        $d1 = Carbon::parse($checkIn);
        $d2 = Carbon::parse($checkOut);
        $nights = max(1, $d1->diffInDays($d2));
        $today = now()->toDateString();

        // Query all active rooms in the hotel
        $roomQuery = Room::with(['category', 'images', 'amenities'])
            ->where('is_active', 1);

        if ($categoryId && $categoryId !== 'all') {
            $roomQuery->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orWhereHas('category', function ($sub) use ($categoryId) {
                      $sub->where('slug', $categoryId)->orWhere('name', 'LIKE', "%$categoryId%");
                  });
            });
        }

        if ($floor) {
            $roomQuery->where('floor', (int)$floor);
        }

        if ($minPrice !== null && is_numeric($minPrice)) {
            $roomQuery->where('price_etb', '>=', (float)$minPrice);
        }
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $roomQuery->where('price_etb', '<=', (float)$maxPrice);
        }

        $allCandidateRooms = $roomQuery->orderBy('floor', 'asc')
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC')
            ->get();

        // Find all reservations overlapping with the requested stay window
        // Overlap rule: Existing Check-in < New Check-out AND Existing Check-out > New Check-in
        $overlappingBookings = Booking::with('guest')
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
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->get();

        // Also check booking_rooms table for any multi-room reservations
        $overlappingBookingRoomIds = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where(function ($q) {
                $q->whereIn('bookings.booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                  ->orWhere(function ($sub) {
                      $sub->where('bookings.booking_status', 'pending_payment')
                          ->where(function ($h) {
                              $h->whereNull('bookings.hold_expires_at')
                                ->orWhere('bookings.hold_expires_at', '>', now());
                          });
                  });
            })
            ->whereNotIn('booking_rooms.status', ['cancelled'])
            ->where('bookings.check_in_date', '<', $checkOut)
            ->where('bookings.check_out_date', '>', $checkIn)
            ->pluck('booking_rooms.room_id')
            ->toArray();

        $availableRooms = [];
        $unavailableRooms = [];

        foreach ($allCandidateRooms as $room) {
            $isAvailable = true;
            $unavailabilityReason = null;

            // 1. Maintenance Check
            if ($room->status === 'maintenance' || $room->housekeeping_status === 'maintenance') {
                $isAvailable = false;
                $unavailabilityReason = 'Room is currently under maintenance or repair';
            }

            // 2. Turnover / Cleaning check for same-day check-in
            if ($isAvailable && $checkIn <= $today && ($room->status === 'cleaning' || $room->housekeeping_status === 'dirty')) {
                $isAvailable = false;
                $unavailabilityReason = 'Room undergoing housekeeping turnover / awaiting inspection';
            }

            // 3. Overlap check with direct room_id on bookings
            if ($isAvailable) {
                $directOverlap = $overlappingBookings->firstWhere('room_id', $room->id);
                if ($directOverlap) {
                    $isAvailable = false;
                    $guestName = $directOverlap->guest?->full_name ?: 'Registered Guest';
                    $unavailabilityReason = "Reserved: Booking {$directOverlap->reference_code} ({$directOverlap->check_in_date} to {$directOverlap->check_out_date})";
                }
            }

            // 4. Overlap check with multi-room booking_rooms table
            if ($isAvailable && in_array($room->id, $overlappingBookingRoomIds)) {
                $isAvailable = false;
                $unavailabilityReason = 'Reserved as part of a multi-room booking for overlapping dates';
            }

            $primaryImg = $room->images->where('is_primary', 1)->first() ?: $room->images->first();
            $roomTotalEtb = (float)$room->price_etb * $nights;
            $roomTotalUsd = (float)$room->price_usd * $nights;

            $roomPayload = [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'title' => $room->title,
                'category_id' => $room->category_id,
                'category_name' => $room->category?->name ?: 'Standard',
                'category_slug' => $room->category?->slug ?: 'standard',
                'badge_color' => $room->category?->badge_color ?: '#0B4D34',
                'floor' => (int)$room->floor,
                'capacity' => (int)$room->capacity,
                'price_per_night_etb' => (float)$room->price_etb,
                'price_per_night_usd' => (float)$room->price_usd,
                'total_stay_price_etb' => $roomTotalEtb,
                'total_stay_price_usd' => $roomTotalUsd,
                'status' => $room->status,
                'housekeeping_status' => $room->housekeeping_status ?: 'inspected',
                'description' => $room->description ?: '',
                'image_url' => $primaryImg ? $primaryImg->image_url : '/uploads/rooms/default.jpg',
                'amenities' => $room->amenities->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'icon_name' => $a->icon_name,
                ])->values()->toArray(),
            ];

            if ($isAvailable) {
                // If filtering by minimum capacity per room
                if ($minGuests > 1 && $room->capacity < $minGuests) {
                    $roomPayload['unavailability_reason'] = "Room capacity ({$room->capacity} pax) less than requested ({$minGuests} pax)";
                    $unavailableRooms[] = $roomPayload;
                } else {
                    $availableRooms[] = $roomPayload;
                }
            } else {
                $roomPayload['unavailability_reason'] = $unavailabilityReason;
                $unavailableRooms[] = $roomPayload;
            }
        }

        return response()->json([
            'stay_details' => [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'min_guests' => $minGuests,
                'rooms_required' => $roomsRequired,
                'has_sufficient_rooms' => count($availableRooms) >= $roomsRequired,
            ],
            'available_rooms' => $availableRooms,
            'unavailable_rooms' => $unavailableRooms,
            'summary' => [
                'total_searched' => count($allCandidateRooms),
                'available_count' => count($availableRooms),
                'unavailable_count' => count($unavailableRooms),
            ]
        ]);
    }

    /**
     * Create a new room reservation (Single or Multiple Rooms, Advance or Walk-In).
     * Enforces ACID transaction, pessimistic row locking, guest creation, and collision prevention.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'room_id' => 'nullable|string',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'string',
            'rooms' => 'nullable|array',
            'rooms.*.room_id' => 'required|string',
            'rooms.*.adults' => 'nullable|integer|min:1',
            'rooms.*.children' => 'nullable|integer|min:0',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'guest_id' => 'nullable|string',
            'guest_name' => 'nullable|string|max:150',
            'guest_email' => 'nullable|email|max:160',
            'guest_phone' => 'nullable|string|max:40',
            'payment_method' => 'nullable|string',
            'payment_mode' => 'nullable|string',
            'promo_code' => 'nullable|string',
            'meal_plan' => 'nullable|string',
            'addons' => 'nullable|array',
            'is_walk_in' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'special_requests' => 'nullable|string',
        ]);

        $checkIn = $validated['check_in_date'];
        $checkOut = $validated['check_out_date'];
        $isWalkIn = (bool)($validated['is_walk_in'] ?? false);
        $paymentMethod = $validated['payment_method'] ?? ($isWalkIn ? 'cash' : 'telebirr');
        $paymentMode = $validated['payment_mode'] ?? ($isWalkIn ? 'instant_paid' : 'manual_receipt');
        $promoCode = $validated['promo_code'] ?? null;
        $mealPlan = $validated['meal_plan'] ?? 'room_only';
        $addons = $validated['addons'] ?? [];
        $specialRequests = $validated['special_requests'] ?? ($validated['notes'] ?? '');

        // Normalize room requests: supports single room_id, array of room_ids, or structured rooms array
        $requestedRooms = [];
        if (!empty($validated['rooms'])) {
            $requestedRooms = $validated['rooms'];
        } elseif (!empty($validated['room_ids'])) {
            foreach ($validated['room_ids'] as $rId) {
                $requestedRooms[] = ['room_id' => $rId, 'adults' => 2, 'children' => 0];
            }
        } elseif (!empty($validated['room_id'])) {
            $requestedRooms[] = [
                'room_id' => $validated['room_id'],
                'adults' => (int)($validated['adults'] ?? 1),
                'children' => (int)($validated['children'] ?? 0),
            ];
        } else {
            return response()->json(['error' => 'Please select at least one room for the reservation.'], 422);
        }

        $d1 = Carbon::parse($checkIn);
        $d2 = Carbon::parse($checkOut);
        $nights = max(1, $d1->diffInDays($d2));
        $today = now()->toDateString();

        return DB::transaction(function () use (
            $requestedRooms, $checkIn, $checkOut, $nights, $today, $isWalkIn,
            $paymentMethod, $paymentMode, $promoCode, $mealPlan, $addons,
            $specialRequests, $request
        ) {
            $lockedRooms = [];
            $totalRoomsChargesEtb = 0;
            $totalRoomsChargesUsd = 0;
            $totalAdults = 0;
            $totalChildren = 0;

            // 1. Concurrency Lock & Collision Validation for EVERY room in the reservation
            foreach ($requestedRooms as $reqRoom) {
                $rId = $reqRoom['room_id'];
                $adults = (int)($reqRoom['adults'] ?? 1);
                $children = (int)($reqRoom['children'] ?? 0);
                $totalAdults += $adults;
                $totalChildren += $children;

                // Pessimistic Row Lock
                $room = Room::where('id', $rId)
                    ->orWhere('room_number', $rId)
                    ->lockForUpdate()
                    ->first();

                if (!$room) {
                    return response()->json(['error' => "Room '{$rId}' not found in inventory."], 404);
                }

                if (!$room->is_active) {
                    return response()->json(['error' => "Room {$room->room_number} is currently archived/inactive."], 422);
                }

                if ($room->status === 'maintenance' || $room->housekeeping_status === 'maintenance') {
                    return response()->json(['error' => "Room {$room->room_number} is currently under maintenance and cannot be reserved."], 409);
                }

                if ($isWalkIn && ($room->status === 'cleaning' || $room->housekeeping_status === 'dirty')) {
                    return response()->json(['error' => "Room {$room->room_number} is currently undergoing housekeeping turnover and is not ready for immediate arrival."], 409);
                }

                // Check capacity constraint per room
                if (($adults + $children) > $room->capacity && ($adults > $room->capacity)) {
                    return response()->json([
                        'error' => "Guest count ({$adults} adults) exceeds the maximum capacity of Room {$room->room_number} ({$room->capacity} guests)."
                    ], 422);
                }

                // Overlap collision check on bookings table
                $overlapBooking = Booking::where('room_id', $room->id)
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
                    ->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn)
                    ->lockForUpdate()
                    ->first();

                if ($overlapBooking) {
                    return response()->json([
                        'error' => "Room {$room->room_number} is no longer available between {$checkIn} and {$checkOut} (Collides with reservation {$overlapBooking->reference_code})."
                    ], 409);
                }

                // Overlap collision check on booking_rooms table
                $overlapMulti = DB::table('booking_rooms')
                    ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
                    ->where('booking_rooms.room_id', $room->id)
                    ->whereNotIn('booking_rooms.status', ['cancelled'])
                    ->where(function ($q) {
                        $q->whereIn('bookings.booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                          ->orWhere(function ($sub) {
                              $sub->where('bookings.booking_status', 'pending_payment')
                                  ->where(function ($h) {
                                      $h->whereNull('bookings.hold_expires_at')
                                        ->orWhere('bookings.hold_expires_at', '>', now());
                                  });
                          });
                    })
                    ->where('bookings.check_in_date', '<', $checkOut)
                    ->where('bookings.check_out_date', '>', $checkIn)
                    ->exists();

                if ($overlapMulti) {
                    return response()->json([
                        'error' => "Room {$room->room_number} is already reserved for overlapping dates in another multi-room booking."
                    ], 409);
                }

                $roomStayEtb = (float)$room->price_etb * $nights;
                $roomStayUsd = (float)$room->price_usd * $nights;
                $totalRoomsChargesEtb += $roomStayEtb;
                $totalRoomsChargesUsd += $roomStayUsd;

                $lockedRooms[] = [
                    'model' => $room,
                    'adults' => $adults,
                    'children' => $children,
                    'nightly_rate_etb' => (float)$room->price_etb,
                    'nightly_rate_usd' => (float)$room->price_usd,
                    'total_price_etb' => $roomStayEtb,
                    'total_price_usd' => $roomStayUsd,
                ];
            }

            // 2. Meal Plans & Addon calculations
            $mealPlanRates = [
                'room_only' => 0,
                'bed_breakfast' => 650,
                'half_board' => 1450,
                'full_board' => 2200,
            ];
            $totalGuestsCount = max(1, $totalAdults + $totalChildren);
            $mealTotalEtb = ($mealPlanRates[$mealPlan] ?? 0) * $totalGuestsCount * $nights;

            $addonRates = [
                'airport_shuttle' => 1200,
                'coffee_ceremony' => 850,
                'spa_massage' => 2800,
                'honeymoon_setup' => 1600,
            ];
            $addonsTotalEtb = 0;
            foreach ($addons as $add) {
                if (isset($addonRates[$add])) {
                    $addonsTotalEtb += $addonRates[$add];
                }
            }

            $totalPriceEtb = $totalRoomsChargesEtb + $mealTotalEtb + $addonsTotalEtb;
            $totalPriceUsd = round($totalPriceEtb / 64);

            // Promotional discount
            if ($promoCode) {
                $code = strtoupper(trim($promoCode));
                $discountPct = match ($code) {
                    'LUXURYETHIOPIA' => 15,
                    'HOLIDAY2026' => 10,
                    'TELEBIRR5' => 5,
                    default => 0,
                };
                if ($discountPct > 0) {
                    $totalPriceEtb = round($totalPriceEtb * (1 - $discountPct / 100));
                    $totalPriceUsd = round($totalPriceUsd * (1 - $discountPct / 100));
                }
            }

            // 3. Guest Association or Registration
            $guestId = null;
            $guestName = $request->input('guest_name');
            $guestEmail = $request->input('guest_email');
            $guestPhone = $request->input('guest_phone');

            if (!empty($request->guest_id)) {
                $existing = User::find($request->guest_id);
                if ($existing) {
                    $guestId = $existing->id;
                }
            }

            if (!$guestId) {
                if ($guestEmail) {
                    $existing = User::where('email', strtolower(trim($guestEmail)))->first();
                    if ($existing) {
                        $guestId = $existing->id;
                    }
                } elseif ($guestPhone) {
                    $existing = User::where('phone', trim($guestPhone))->first();
                    if ($existing) {
                        $guestId = $existing->id;
                    }
                }
            }

            if (!$guestId) {
                $guestId = 'usr-guest-' . time() . '-' . rand(10, 99);
                User::create([
                    'id' => $guestId,
                    'role' => 'guest',
                    'full_name' => $guestName ?: 'Guest User',
                    'email' => $guestEmail ? strtolower(trim($guestEmail)) : "guest-{$guestId}@hotel.et",
                    'phone' => $guestPhone ?: '+251 911 000000',
                    'password_hash' => Hash::make('Hotel@Guest2026'),
                    'is_verified' => 1,
                ]);
            }

            // 4. Generate Unique Booking Reference Number
            $refNum = rand(10000, 99999);
            while (Booking::where('reference_code', "#ETH-{$refNum}")->exists()) {
                $refNum = rand(10000, 99999);
            }
            $referenceCode = "#ETH-{$refNum}";
            $bookingId = 'bk-' . time() . '-' . rand(100, 999);

            $primaryRoom = $lockedRooms[0];
            $initialBookingStatus = $isWalkIn ? 'confirmed' : 'pending_payment';
            $initialPaymentStatus = ($isWalkIn || $paymentMode === 'instant_paid') ? 'paid' : 'pending';

            $booking = Booking::create([
                'id' => $bookingId,
                'reference_code' => $referenceCode,
                'guest_id' => $guestId,
                'room_id' => $primaryRoom['model']->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults_count' => $totalAdults ?: 1,
                'children_count' => $totalChildren ?: 0,
                'rooms_count' => count($lockedRooms),
                'nightly_rate_etb' => $primaryRoom['nightly_rate_etb'],
                'nightly_rate_usd' => $primaryRoom['nightly_rate_usd'],
                'total_price_etb' => $totalPriceEtb,
                'total_price_usd' => $totalPriceUsd,
                'booking_status' => $initialBookingStatus,
                'payment_status' => $initialPaymentStatus,
                'is_walk_in' => $isWalkIn ? 1 : 0,
                'qr_verification_hash' => "qr_hash_{$refNum}",
                'notes' => json_encode([
                    'meal_plan' => $mealPlan,
                    'addons' => $addons,
                    'promo_code' => $promoCode,
                    'special_requests' => $specialRequests,
                ]),
                'hold_expires_at' => $isWalkIn ? null : now()->addMinutes(30),
            ]);

            // 5. Store individual room records in booking_rooms
            foreach ($lockedRooms as $idx => $lr) {
                BookingRoom::create([
                    'id' => "br-{$bookingId}-" . ($idx + 1),
                    'booking_id' => $bookingId,
                    'room_id' => $lr['model']->id,
                    'room_number' => $lr['model']->room_number,
                    'adults_count' => $lr['adults'],
                    'children_count' => $lr['children'],
                    'nightly_rate_etb' => $lr['nightly_rate_etb'],
                    'nightly_rate_usd' => $lr['nightly_rate_usd'],
                    'total_price_etb' => $lr['total_price_etb'],
                    'total_price_usd' => $lr['total_price_usd'],
                    'status' => 'reserved',
                    'notes' => $idx === 0 ? 'Primary room' : 'Additional room',
                ]);

                // Operational room status transition if check-in is today
                if ($checkIn === $today) {
                    if ($isWalkIn) {
                        $lr['model']->update(['status' => 'occupied']);
                    } elseif ($lr['model']->status === 'available') {
                        $lr['model']->update(['status' => 'reserved']);
                    }
                }
            }

            // 6. Create Payment Record
            Payment::create([
                'id' => "pay-{$bookingId}",
                'transaction_id' => "TX-{$refNum}",
                'booking_id' => $bookingId,
                'payment_method' => $paymentMethod,
                'amount_etb' => $totalPriceEtb,
                'amount_usd' => $totalPriceUsd,
                'claimed_amount_etb' => $totalPriceEtb,
                'status' => $initialPaymentStatus === 'paid' ? 'verified' : 'pending',
                'submitted_at' => now(),
            ]);

            // 7. Audit Log
            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Reservation Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'create_reservation',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => [
                    'reference_code' => $booking->reference_code,
                    'rooms_count' => count($lockedRooms),
                    'total_price_etb' => $booking->total_price_etb,
                    'is_walk_in' => $isWalkIn,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Reservation {$booking->reference_code} successfully created in MySQL.",
                'booking' => [
                    'id' => $booking->id,
                    'reference_code' => $booking->reference_code,
                    'guest_name' => $guestName ?: 'Guest User',
                    'rooms_count' => count($lockedRooms),
                    'total_price_etb' => (float)$booking->total_price_etb,
                    'total_price_usd' => (float)$booking->total_price_usd,
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,
                    'is_walk_in' => (bool)$booking->is_walk_in,
                    'check_in_date' => $booking->check_in_date,
                    'check_out_date' => $booking->check_out_date,
                    'nights' => $nights,
                    'rooms' => collect($lockedRooms)->map(fn($lr) => [
                        'room_id' => $lr['model']->id,
                        'room_number' => $lr['model']->room_number,
                        'title' => $lr['model']->title,
                        'category' => $lr['model']->category?->name,
                        'price_etb' => $lr['total_price_etb'],
                    ])->values()->toArray(),
                ]
            ], 201);
        });
    }

    /**
     * Show detailed reservation information with multi-room breakdown, payment ledger,
     * and complete audit modification history.
     */
    public function show($id)
    {
        $booking = Booking::with([
            'guest',
            'room.category',
            'room.images',
            'bookingRooms.room.category',
            'payments',
            'auditLogs'
        ])
        ->where('id', $id)
        ->orWhere('reference_code', $id)
        ->firstOrFail();

        $d1 = Carbon::parse($booking->check_in_date);
        $d2 = Carbon::parse($booking->check_out_date);
        $nights = max(1, $d1->diffInDays($d2));

        $verifiedPay = $booking->payments->where('status', 'verified')->sum('amount_etb');
        $totalPrice = (float)$booking->total_price_etb;
        $outstanding = max(0, $totalPrice - $verifiedPay);

        // Multi-room breakdown
        $rooms = $booking->bookingRooms->map(function ($br) use ($nights) {
            return [
                'id' => $br->id,
                'room_id' => $br->room_id,
                'room_number' => $br->room_number ?: $br->room?->room_number,
                'title' => $br->room?->title ?: "Room {$br->room_number}",
                'category_name' => $br->room?->category?->name ?: 'Standard',
                'badge_color' => $br->room?->category?->badge_color ?: '#0B4D34',
                'adults_count' => $br->adults_count,
                'children_count' => $br->children_count,
                'nightly_rate_etb' => (float)$br->nightly_rate_etb,
                'nightly_rate_usd' => (float)$br->nightly_rate_usd,
                'total_price_etb' => (float)$br->total_price_etb,
                'status' => $br->status,
            ];
        });

        // Fallback for older single-room records if bookingRooms is empty
        if ($rooms->isEmpty() && $booking->room) {
            $rooms = collect([[
                'id' => 'br-primary',
                'room_id' => $booking->room->id,
                'room_number' => $booking->room->room_number,
                'title' => $booking->room->title,
                'category_name' => $booking->room->category?->name ?: 'Standard',
                'badge_color' => $booking->room->category?->badge_color ?: '#0B4D34',
                'adults_count' => $booking->adults_count,
                'children_count' => $booking->children_count,
                'nightly_rate_etb' => (float)$booking->nightly_rate_etb,
                'nightly_rate_usd' => (float)$booking->nightly_rate_usd,
                'total_price_etb' => (float)$booking->total_price_etb,
                'status' => match ($booking->booking_status) {
                    'checked_in' => 'checked_in',
                    'checked_out' => 'checked_out',
                    'cancelled' => 'cancelled',
                    default => 'reserved',
                },
            ]]);
        }

        // Parse notes/special requests JSON safely
        $parsedNotes = [];
        if ($booking->notes) {
            $parsedNotes = json_decode($booking->notes, true) ?: ['raw_notes' => $booking->notes];
        }

        return response()->json([
            'booking' => [
                'id' => $booking->id,
                'reference_code' => $booking->reference_code,
                'guest' => [
                    'id' => $booking->guest?->id,
                    'full_name' => $booking->guest?->full_name ?: 'Valued Guest',
                    'email' => $booking->guest?->email ?: 'N/A',
                    'phone' => $booking->guest?->phone ?: 'N/A',
                ],
                'guest_name' => $booking->guest?->full_name ?: 'Valued Guest',
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
                'nights' => $nights,
                'adults_count' => $booking->adults_count,
                'children_count' => $booking->children_count,
                'rooms_count' => $booking->rooms_count ?: $rooms->count(),
                'rooms' => $rooms->values(),
                'total_price_etb' => $totalPrice,
                'total_price_usd' => (float)$booking->total_price_usd,
                'verified_paid_etb' => (float)$verifiedPay,
                'outstanding_balance_etb' => $outstanding,
                'booking_status' => $booking->booking_status,
                'payment_status' => $booking->payment_status,
                'is_walk_in' => (bool)$booking->is_walk_in,
                'notes_data' => $parsedNotes,
                'special_requests' => $parsedNotes['special_requests'] ?? ($parsedNotes['raw_notes'] ?? 'None'),
                'actual_check_in_at' => $booking->actual_check_in_at,
                'actual_check_out_at' => $booking->actual_check_out_at,
                'cancellation_reason' => $booking->cancellation_reason,
                'cancelled_at' => $booking->cancelled_at,
                'cancelled_by' => $booking->cancelled_by,
                'created_at' => $booking->created_at,
                'payments' => $booking->payments->map(fn($p) => [
                    'id' => $p->id,
                    'transaction_id' => $p->transaction_id,
                    'payment_method' => $p->payment_method,
                    'amount_etb' => (float)$p->amount_etb,
                    'status' => $p->status,
                    'receipt_url' => $p->receipt_url,
                    'submitted_at' => $p->submitted_at,
                ]),
                'history' => $booking->auditLogs->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user_name,
                    'user_role' => $log->user_role,
                    'details' => $log->details,
                    'created_at' => $log->created_at,
                ]),
            ]
        ]);
    }

    /**
     * Modify an existing reservation (Change dates, add/remove/reassign rooms, guest info, notes).
     * Enforces date overlap and capacity rules, recalculates charges, and logs modification history.
     */
    public function update(Request $request, $id)
    {
        $booking = Booking::with(['bookingRooms', 'room'])->findOrFail($id);

        if ($booking->booking_status === 'checked_out') {
            return response()->json(['error' => 'Completed stays (Checked-Out) cannot be modified.'], 422);
        }
        if (in_array($booking->booking_status, ['cancelled', 'no_show'])) {
            return response()->json(['error' => 'Cancelled or No-Show reservations cannot be modified.'], 422);
        }

        $validated = $request->validate([
            'check_in_date' => 'sometimes|required|date',
            'check_out_date' => 'sometimes|required|date|after:check_in_date',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'string',
            'adults_count' => 'nullable|integer|min:1',
            'children_count' => 'nullable|integer|min:0',
            'guest_name' => 'nullable|string|max:150',
            'guest_phone' => 'nullable|string|max:40',
            'guest_email' => 'nullable|email|max:160',
            'special_requests' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($booking, $validated, $request) {
            $newCheckIn = $validated['check_in_date'] ?? $booking->check_in_date;
            $newCheckOut = $validated['check_out_date'] ?? $booking->check_out_date;

            // Restrict changing check-in date to the future if already checked in
            if ($booking->booking_status === 'checked_in' && $newCheckIn !== $booking->check_in_date) {
                return response()->json(['error' => 'Cannot modify check-in date for an active in-house stay.'], 422);
            }

            // Determine target room IDs
            $currentRoomIds = $booking->bookingRooms->pluck('room_id')->toArray();
            if (empty($currentRoomIds)) {
                $currentRoomIds = [$booking->room_id];
            }
            $targetRoomIds = !empty($validated['room_ids']) ? $validated['room_ids'] : $currentRoomIds;

            $d1 = Carbon::parse($newCheckIn);
            $d2 = Carbon::parse($newCheckOut);
            $nights = max(1, $d1->diffInDays($d2));

            // Validate and lock all target rooms
            $totalRoomsChargesEtb = 0;
            $totalRoomsChargesUsd = 0;
            $lockedRooms = [];

            foreach ($targetRoomIds as $rId) {
                $room = Room::where('id', $rId)->lockForUpdate()->firstOrFail();

                if (!$room->is_active) {
                    return response()->json(['error' => "Room {$room->room_number} is inactive."], 422);
                }
                if ($room->status === 'maintenance') {
                    return response()->json(['error' => "Room {$room->room_number} is currently under maintenance."], 409);
                }

                // Check collisions against OTHER reservations (excluding this booking!)
                $hasCollision = Booking::where('room_id', $room->id)
                    ->where('id', '!=', $booking->id)
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
                    ->where('check_in_date', '<', $newCheckOut)
                    ->where('check_out_date', '>', $newCheckIn)
                    ->exists();

                if ($hasCollision) {
                    return response()->json([
                        'error' => "Room {$room->room_number} is not available between {$newCheckIn} and {$newCheckOut}."
                    ], 409);
                }

                // Collision check against booking_rooms of other bookings
                $hasMultiCollision = DB::table('booking_rooms')
                    ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
                    ->where('booking_rooms.room_id', $room->id)
                    ->where('booking_rooms.booking_id', '!=', $booking->id)
                    ->whereNotIn('booking_rooms.status', ['cancelled'])
                    ->whereIn('bookings.booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                    ->where('bookings.check_in_date', '<', $newCheckOut)
                    ->where('bookings.check_out_date', '>', $newCheckIn)
                    ->exists();

                if ($hasMultiCollision) {
                    return response()->json([
                        'error' => "Room {$room->room_number} conflicts with another reservation."
                    ], 409);
                }

                $roomStayEtb = (float)$room->price_etb * $nights;
                $roomStayUsd = (float)$room->price_usd * $nights;
                $totalRoomsChargesEtb += $roomStayEtb;
                $totalRoomsChargesUsd += $roomStayUsd;

                $lockedRooms[] = [
                    'model' => $room,
                    'nightly_rate_etb' => (float)$room->price_etb,
                    'nightly_rate_usd' => (float)$room->price_usd,
                    'total_price_etb' => $roomStayEtb,
                    'total_price_usd' => $roomStayUsd,
                ];
            }

            // Recalculate price difference
            $oldTotalPrice = (float)$booking->total_price_etb;
            $newTotalPrice = $totalRoomsChargesEtb;
            $priceDiff = $newTotalPrice - $oldTotalPrice;

            // Sync booking_rooms
            BookingRoom::where('booking_id', $booking->id)->delete();
            foreach ($lockedRooms as $idx => $lr) {
                BookingRoom::create([
                    'id' => "br-{$booking->id}-" . ($idx + 1),
                    'booking_id' => $booking->id,
                    'room_id' => $lr['model']->id,
                    'room_number' => $lr['model']->room_number,
                    'adults_count' => $validated['adults_count'] ?? $booking->adults_count,
                    'children_count' => $validated['children_count'] ?? $booking->children_count,
                    'nightly_rate_etb' => $lr['nightly_rate_etb'],
                    'nightly_rate_usd' => $lr['nightly_rate_usd'],
                    'total_price_etb' => $lr['total_price_etb'],
                    'total_price_usd' => $lr['total_price_usd'],
                    'status' => $booking->booking_status === 'checked_in' ? 'checked_in' : 'reserved',
                    'notes' => $idx === 0 ? 'Primary room' : 'Additional room',
                ]);
            }

            // Update Guest Profile details if provided
            if ($booking->guest) {
                $guestUpdates = [];
                if (!empty($validated['guest_name'])) $guestUpdates['full_name'] = $validated['guest_name'];
                if (!empty($validated['guest_phone'])) $guestUpdates['phone'] = $validated['guest_phone'];
                if (!empty($validated['guest_email'])) $guestUpdates['email'] = strtolower(trim($validated['guest_email']));
                if (!empty($guestUpdates)) {
                    $booking->guest->update($guestUpdates);
                }
            }

            // Update main booking
            $notesObj = json_decode($booking->notes, true) ?: [];
            if (!empty($validated['special_requests'])) {
                $notesObj['special_requests'] = $validated['special_requests'];
            }

            $primaryLocked = $lockedRooms[0];
            $booking->update([
                'room_id' => $primaryLocked['model']->id,
                'check_in_date' => $newCheckIn,
                'check_out_date' => $newCheckOut,
                'rooms_count' => count($lockedRooms),
                'adults_count' => $validated['adults_count'] ?? $booking->adults_count,
                'children_count' => $validated['children_count'] ?? $booking->children_count,
                'nightly_rate_etb' => $primaryLocked['nightly_rate_etb'],
                'nightly_rate_usd' => $primaryLocked['nightly_rate_usd'],
                'total_price_etb' => $newTotalPrice,
                'total_price_usd' => $totalRoomsChargesUsd,
                'notes' => json_encode($notesObj),
            ]);

            // Audit Log
            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Reservation Desk Staff',
                'user_role' => $request->user()?->role ?: 'staff',
                'action' => 'modify_reservation',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => [
                    'reference_code' => $booking->reference_code,
                    'old_total' => $oldTotalPrice,
                    'new_total' => $newTotalPrice,
                    'price_difference' => $priceDiff,
                    'new_dates' => "{$newCheckIn} to {$newCheckOut}",
                    'rooms_count' => count($lockedRooms),
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Reservation {$booking->reference_code} modified successfully.",
                'price_difference_etb' => $priceDiff,
                'booking' => $booking->fresh(['bookingRooms.room.category', 'guest']),
            ]);
        });
    }

    /**
     * Cancel a reservation with cancellation date, reason, and room release.
     */
    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $reason = $validated['cancellation_reason'];

        return DB::transaction(function () use ($id, $reason, $request) {
            $booking = Booking::with(['bookingRooms', 'room', 'payments'])
                ->where('id', $id)
                ->orWhere('reference_code', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->booking_status === 'checked_out') {
                return response()->json(['error' => 'Completed stays (Checked-Out) cannot be cancelled.'], 422);
            }
            if ($booking->booking_status === 'cancelled') {
                return response()->json(['error' => 'Reservation is already cancelled.'], 422);
            }

            // Mark booking as cancelled
            $booking->update([
                'booking_status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()?->id ?: 'usr-staff-1',
                'hold_expires_at' => null,
            ]);

            // Mark rooms in booking_rooms as cancelled
            BookingRoom::where('booking_id', $booking->id)->update(['status' => 'cancelled']);

            // Release physical rooms if not under maintenance
            $roomIds = $booking->bookingRooms->pluck('room_id')->toArray();
            if (empty($roomIds) && $booking->room_id) {
                $roomIds = [$booking->room_id];
            }

            foreach ($roomIds as $rId) {
                $room = Room::find($rId);
                if ($room && $room->status === 'reserved') {
                    $room->update(['status' => 'available']);
                }
            }

            // Update payment transaction status to refunded / cancelled
            $totalPaid = $booking->payments->where('status', 'verified')->sum('amount_etb');
            if ($totalPaid > 0) {
                $booking->update(['payment_status' => 'refunded']);
                Payment::where('booking_id', $booking->id)->update([
                    'status' => 'refunded',
                    'rejection_reason' => "Cancelled by staff: {$reason}",
                ]);
            } else {
                $booking->update(['payment_status' => 'rejected']);
                Payment::where('booking_id', $booking->id)->update([
                    'status' => 'rejected',
                    'rejection_reason' => "Cancelled: {$reason}",
                ]);
            }

            // Audit Log
            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'staff',
                'action' => 'cancel_reservation',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => [
                    'reference_code' => $booking->reference_code,
                    'reason' => $reason,
                    'refunded' => $totalPaid > 0,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Reservation {$booking->reference_code} has been successfully cancelled and rooms released.",
                'booking' => $booking->fresh(),
            ]);
        });
    }

    /**
     * Mark a reservation as No-Show.
     */
    public function markNoShow(Request $request, $id)
    {
        $booking = Booking::with(['bookingRooms', 'room'])->findOrFail($id);

        if (in_array($booking->booking_status, ['checked_in', 'checked_out', 'cancelled'])) {
            return response()->json(['error' => "Cannot mark as No-Show: Reservation is currently '{$booking->booking_status}'."], 422);
        }

        return DB::transaction(function () use ($booking, $request) {
            $booking->update([
                'booking_status' => 'no_show',
                'cancellation_reason' => 'Guest did not arrive for scheduled check-in (No-Show).',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()?->id ?: 'usr-staff-1',
                'hold_expires_at' => null,
            ]);

            // Release rooms
            $roomIds = $booking->bookingRooms->pluck('room_id')->toArray();
            if (empty($roomIds) && $booking->room_id) {
                $roomIds = [$booking->room_id];
            }

            foreach ($roomIds as $rId) {
                $room = Room::find($rId);
                if ($room && $room->status === 'reserved') {
                    $room->update(['status' => 'available']);
                }
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'mark_no_show',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => ['reference_code' => $booking->reference_code],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Reservation {$booking->reference_code} marked as No-Show. Rooms released to inventory.",
                'booking' => $booking->fresh(),
            ]);
        });
    }

    /**
     * Reservation Management Desk (Listing, Tab filters, Search, Date ranges, KPIs).
     */
    public function desk(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');
        $checkInDate = $request->query('check_in_date');
        $checkOutDate = $request->query('check_out_date');
        $createdFrom = $request->query('created_from');
        $guestId = $request->query('guest_id');
        $today = now()->toDateString();

        $query = Booking::with(['guest', 'room.category', 'bookingRooms.room.category', 'payments']);

        // Quick status tab filters
        if ($status && $status !== 'all') {
            if ($status === 'arrivals_today') {
                $query->whereIn('booking_status', ['confirmed', 'pending_verification'])
                      ->where('check_in_date', $today);
            } elseif ($status === 'departures_today') {
                $query->where('booking_status', 'checked_in')
                      ->where('check_out_date', $today);
            } elseif ($status === 'in_house') {
                $query->where('booking_status', 'checked_in');
            } elseif ($status === 'upcoming') {
                $query->whereIn('booking_status', ['confirmed', 'pending_verification'])
                      ->where('check_in_date', '>', $today);
            } else {
                $query->where('booking_status', $status);
            }
        }

        // Search filter (Ref code, guest name, phone, email, room number)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'LIKE', "%$search%")
                  ->orWhereHas('guest', fn($g) => $g->where('full_name', 'LIKE', "%$search%")->orWhere('phone', 'LIKE', "%$search%")->orWhere('email', 'LIKE', "%$search%"))
                  ->orWhereHas('room', fn($r) => $r->where('room_number', 'LIKE', "%$search%"))
                  ->orWhereHas('bookingRooms.room', fn($r) => $r->where('room_number', 'LIKE', "%$search%"));
            });
        }

        if ($checkInDate) {
            $query->where('check_in_date', '>=', $checkInDate);
        }
        if ($checkOutDate) {
            $query->where('check_out_date', '<=', $checkOutDate);
        }
        if ($createdFrom) {
            $query->where('created_at', '>=', $createdFrom);
        }
        if ($guestId) {
            $query->where('guest_id', $guestId);
        }

        $allBookings = $query->orderBy('created_at', 'desc')->get();

        // Calculate KPI summary stats
        $totalAll = Booking::count();
        $arrivalsToday = Booking::whereIn('booking_status', ['confirmed', 'pending_verification'])->where('check_in_date', $today)->count();
        $departuresToday = Booking::where('booking_status', 'checked_in')->where('check_out_date', $today)->count();
        $inHouseCount = Booking::where('booking_status', 'checked_in')->count();
        $upcomingCount = Booking::whereIn('booking_status', ['confirmed', 'pending_verification'])->where('check_in_date', '>', $today)->count();
        $pendingCount = Booking::whereIn('booking_status', ['pending', 'pending_payment', 'pending_verification'])->count();
        $confirmedCount = Booking::where('booking_status', 'confirmed')->count();
        $cancelledCount = Booking::where('booking_status', 'cancelled')->count();
        $noShowCount = Booking::where('booking_status', 'no_show')->count();

        $reservations = $allBookings->map(function ($b) {
            $verifiedPay = $b->payments->where('status', 'verified')->sum('amount_etb');
            $roomNumbers = $b->bookingRooms->pluck('room_number')->filter()->toArray();
            if (empty($roomNumbers) && $b->room) {
                $roomNumbers = [$b->room->room_number];
            }

            return [
                'id' => $b->id,
                'reference_code' => $b->reference_code,
                'guest_id' => $b->guest_id,
                'guest_name' => $b->guest?->full_name ?: 'Valued Guest',
                'guest_email' => $b->guest?->email ?: 'N/A',
                'guest_phone' => $b->guest?->phone ?: 'N/A',
                'room_id' => $b->room_id,
                'room_number' => implode(', ', $roomNumbers),
                'room_title' => $b->room?->title ?: 'Suite',
                'category_name' => $b->room?->category?->name ?: 'Suite',
                'rooms_count' => $b->rooms_count ?: count($roomNumbers),
                'check_in_date' => $b->check_in_date,
                'check_out_date' => $b->check_out_date,
                'adults_count' => $b->adults_count,
                'children_count' => $b->children_count,
                'total_price_etb' => (float)$b->total_price_etb,
                'total_price_usd' => (float)$b->total_price_usd,
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
                'outstanding_balance_etb' => max(0, (float)$b->total_price_etb - $verifiedPay),
                'is_walk_in' => (bool)$b->is_walk_in,
                'created_at' => $b->created_at,
            ];
        });

        return response()->json([
            'reservations' => $reservations,
            'stats' => [
                'total' => $totalAll,
                'arrivals_today' => $arrivalsToday,
                'departures_today' => $departuresToday,
                'in_house' => $inHouseCount,
                'upcoming' => $upcomingCount,
                'pending' => $pendingCount,
                'confirmed' => $confirmedCount,
                'cancelled' => $cancelledCount,
                'no_show' => $noShowCount,
            ]
        ]);
    }

    /**
     * Printable and Downloadable Booking Confirmation Slip Voucher.
     */
    public function confirmation($id)
    {
        $booking = Booking::with([
            'guest',
            'room.category',
            'bookingRooms.room.category',
            'payments'
        ])
        ->where('id', $id)
        ->orWhere('reference_code', $id)
        ->firstOrFail();

        $d1 = Carbon::parse($booking->check_in_date);
        $d2 = Carbon::parse($booking->check_out_date);
        $nights = max(1, $d1->diffInDays($d2));

        $totalPaid = (float)$booking->payments->where('status', 'verified')->sum('amount_etb');
        $totalPrice = (float)$booking->total_price_etb;
        $balanceDue = max(0, $totalPrice - $totalPaid);

        $roomItems = $booking->bookingRooms->map(function ($br) use ($nights) {
            return [
                'room_number' => $br->room_number ?: $br->room?->room_number,
                'category_name' => $br->room?->category?->name ?: 'Deluxe',
                'capacity' => $br->adults_count + $br->children_count,
                'nightly_rate_etb' => (float)$br->nightly_rate_etb,
                'subtotal_etb' => (float)$br->total_price_etb,
            ];
        });

        return response()->json([
            'confirmation' => [
                'confirmation_number' => $booking->reference_code,
                'issue_date' => now()->toFormattedDateString(),
                'hotel' => [
                    'name' => 'Ethiopian Luxury Resort & Heritage Suites',
                    'address' => 'Bole Medhanealem Avenue, Addis Ababa, Ethiopia',
                    'phone' => '+251 11 662 0000',
                    'tin' => '0048921849',
                ],
                'guest' => [
                    'name' => $booking->guest?->full_name ?: 'Valued Guest',
                    'phone' => $booking->guest?->phone ?: 'N/A',
                    'email' => $booking->guest?->email ?: 'N/A',
                ],
                'stay' => [
                    'check_in_date' => $booking->check_in_date,
                    'check_in_time' => '02:00 PM',
                    'check_out_date' => $booking->check_out_date,
                    'check_out_time' => '11:00 AM',
                    'nights' => $nights,
                    'rooms_count' => $booking->rooms_count ?: $roomItems->count(),
                    'total_guests' => $booking->adults_count + $booking->children_count,
                ],
                'rooms' => $roomItems->values(),
                'financial' => [
                    'total_amount_etb' => $totalPrice,
                    'total_amount_usd' => (float)$booking->total_price_usd,
                    'amount_paid_etb' => $totalPaid,
                    'balance_due_etb' => $balanceDue,
                    'payment_status' => $booking->payment_status,
                ],
                'status' => $booking->booking_status,
                'cancellation_policy' => 'Free cancellation up to 48 hours before check-in. Late cancellations or no-shows incur 1 night charge.',
            ]
        ]);
    }

    /**
     * Guest directory search for front desk staff.
     */
    public function guests(Request $request)
    {
        $search = $request->query('search');

        $query = User::withCount('bookings')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%")
                  ->orWhere('phone', 'LIKE', "%$search%");
            });
        }

        $guests = $query->limit(20)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'email' => $u->email,
                'phone' => $u->phone,
                'bookings_count' => $u->bookings_count,
                'created_at' => $u->created_at,
            ];
        });

        return response()->json(['guests' => $guests]);
    }

    /**
     * Individual Guest's Historical and Active Bookings.
     */
    public function guestBookings($guestId)
    {
        $guest = User::findOrFail($guestId);

        $bookings = Booking::with(['room.category', 'bookingRooms', 'payments'])
            ->where('guest_id', $guestId)
            ->orderBy('check_in_date', 'desc')
            ->get()
            ->map(function ($b) {
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'room_number' => $b->room?->room_number,
                    'category_name' => $b->room?->category?->name,
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'total_price_etb' => (float)$b->total_price_etb,
                    'booking_status' => $b->booking_status,
                    'payment_status' => $b->payment_status,
                    'created_at' => $b->created_at,
                ];
            });

        return response()->json([
            'guest' => [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'email' => $guest->email,
                'phone' => $guest->phone,
            ],
            'bookings' => $bookings
        ]);
    }

    /**
     * Check-in eligible arrivals list.
     */
    public function checkInEligible()
    {
        $today = now()->toDateString();
        $eligible = Booking::with(['guest', 'room.category', 'bookingRooms.room.category', 'payments'])
            ->whereIn('booking_status', ['confirmed', 'pending_verification'])
            ->orderBy('check_in_date', 'asc')
            ->get()
            ->map(function ($b) use ($today) {
                $isToday = $b->check_in_date <= $today;
                $isRoomReady = ($b->room && ($b->room->status === 'available' || $b->room->status === 'reserved') && in_array($b->room->housekeeping_status, ['clean', 'inspected']));
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'guest_name' => $b->guest?->full_name ?: 'Valued Guest',
                    'guest_phone' => $b->guest?->phone ?: 'N/A',
                    'guest_email' => $b->guest?->email ?: 'N/A',
                    'guest_id_type' => $b->guest?->id_type,
                    'guest_id_number' => $b->guest?->id_number,
                    'vip_status' => $b->guest?->vip_status ?: 'standard',
                    'preferences' => $b->guest?->preferences,
                    'room_number' => $b->room?->room_number ?: ($b->bookingRooms->first()?->room_number ?: '101'),
                    'category_name' => $b->room?->category?->name ?: ($b->bookingRooms->first()?->room?->category?->name ?: 'Deluxe'),
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'is_today' => $isToday,
                    'is_room_ready' => $isRoomReady,
                    'payment_status' => $b->payment_status,
                ];
            });

        return response()->json(['eligible_arrivals' => $eligible]);
    }

    /**
     * Process Check-In.
     */
    public function checkIn(Request $request, $id)
    {
        $booking = Booking::with(['room', 'bookingRooms.room', 'guest'])
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        if (in_array($booking->booking_status, ['checked_in', 'checked_out', 'cancelled', 'no_show'])) {
            return response()->json(['error' => "Cannot check in: Reservation is currently '{$booking->booking_status}'."], 422);
        }

        // Validate readiness of all rooms
        $rooms = $booking->bookingRooms->isNotEmpty() ? $booking->bookingRooms->pluck('room') : collect([$booking->room]);

        foreach ($rooms as $r) {
            if (!$r) continue;
            if ($r->status === 'maintenance' || $r->housekeeping_status === 'maintenance') {
                return response()->json(['error' => "Cannot check in: Room {$r->room_number} is currently under maintenance."], 422);
            }
            if ($r->status === 'cleaning' || $r->housekeeping_status === 'dirty') {
                return response()->json(['error' => "Cannot check in: Room {$r->room_number} is currently undergoing cleaning/turnover. Please ensure housekeeping inspection is complete."], 422);
            }
        }

        // Validate capacity
        $numGuests = (int)($request->input('num_guests') ?: ($booking->num_guests ?: ($booking->adults_count ?: 1)));
        $totalCapacity = $rooms->sum('capacity') ?: ($booking->room?->capacity ?: 2);
        if ($numGuests > $totalCapacity) {
            return response()->json([
                'error' => "Cannot check in: Arriving guest count ({$numGuests}) exceeds total room capacity ({$totalCapacity})."
            ], 422);
        }

        $bookingUpdates = [
            'booking_status' => 'checked_in',
            'actual_check_in_at' => now(),
            'checked_in_by' => $request->user()?->id ?: 'usr-admin-1',
            'key_card_number' => $request->input('key_card_number', $booking->key_card_number ?: ('KC-' . ($booking->room?->room_number ?: '101'))),
            'check_in_notes' => $request->input('check_in_notes', $booking->check_in_notes),
            'num_guests' => $numGuests,
        ];

        if ($request->boolean('early_check_in_approved')) {
            $bookingUpdates['early_check_in_approved'] = true;
            if ($request->filled('early_check_in_fee_etb')) {
                $fee = (float)$request->input('early_check_in_fee_etb');
                $bookingUpdates['early_check_in_fee_etb'] = $fee;
                $bookingUpdates['total_price_etb'] = (float)$booking->total_price_etb + $fee;
            }
        }

        $booking->update($bookingUpdates);

        // Update guest identification if provided
        if ($booking->guest) {
            $guestUpdates = [];
            if ($request->filled('id_type')) $guestUpdates['id_type'] = $request->input('id_type');
            if ($request->filled('id_number')) $guestUpdates['id_number'] = $request->input('id_number');
            if ($request->filled('nationality')) $guestUpdates['nationality'] = $request->input('nationality');
            if (!empty($guestUpdates)) {
                $booking->guest->update($guestUpdates);
            }
        }

        foreach ($rooms as $r) {
            if ($r) $r->update(['status' => 'occupied']);
        }
        BookingRoom::where('booking_id', $booking->id)->update(['status' => 'checked_in']);

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => 'guest_check_in',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => [
                'reference_code' => $booking->reference_code,
                'room' => $booking->room?->room_number,
                'key_card' => $booking->key_card_number,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Guest {$booking->guest?->full_name} checked into Room {$booking->room?->room_number}.",
            'booking' => $booking->fresh(['room.category', 'guest']),
        ]);
    }

    /**
     * Live Front-Desk Check-In Dashboard metrics.
     */
    public function checkInDashboard(Request $request)
    {
        $today = now()->toDateString();

        $arrivalsTodayQuery = Booking::whereIn('booking_status', ['confirmed', 'pending_verification'])
            ->where('check_in_date', $today);
        $expectedArrivalsToday = $arrivalsTodayQuery->count();

        $inHouseOccupants = Booking::where('booking_status', 'checked_in')->count();

        $walkInsToday = Booking::where('is_walk_in', true)
            ->whereDate('created_at', $today)
            ->count();

        $departuresToday = Booking::where('booking_status', 'checked_in')
            ->where('check_out_date', $today)
            ->count();

        $activeRooms = Room::where('is_active', true)->get();
        $roomsReady = $activeRooms->filter(function ($r) {
            return ($r->status === 'available' || $r->status === 'reserved')
                && in_array($r->housekeeping_status, ['inspected', 'clean']);
        })->count();

        $roomsTurnover = $activeRooms->filter(function ($r) {
            return $r->status === 'cleaning' || $r->housekeeping_status === 'dirty';
        })->count();

        $roomsMaintenance = $activeRooms->filter(function ($r) {
            return $r->status === 'maintenance' || $r->housekeeping_status === 'maintenance';
        })->count();

        $metrics = [
            'expected_arrivals_today' => $expectedArrivalsToday,
            'in_house_occupants' => $inHouseOccupants,
            'in_house_guests' => $inHouseOccupants,
            'walk_ins_today' => $walkInsToday,
            'departures_today' => $departuresToday,
            'pending_check_ins' => $expectedArrivalsToday,
            'rooms_ready' => $roomsReady,
            'rooms_turnover' => $roomsTurnover,
            'rooms_turnover_prep' => $roomsTurnover,
            'rooms_maintenance' => $roomsMaintenance,
            'total_active_rooms' => $activeRooms->count(),
        ];

        return response()->json([
            'dashboard' => $metrics,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Front-Desk Room Reassignment during or prior to check-in.
     */
    public function reassignRoom(Request $request, $id)
    {
        $validated = $request->validate([
            'new_room_id' => 'required|string',
            'reason' => 'nullable|string',
            'target_room_id' => 'nullable|string',
        ]);

        $booking = Booking::with(['bookingRooms', 'room'])->where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        if (in_array($booking->booking_status, ['checked_out', 'cancelled'])) {
            return response()->json(['error' => "Cannot reassign room: Stay is already {$booking->booking_status}."], 422);
        }

        return DB::transaction(function () use ($booking, $validated, $request) {
            $newRoom = Room::where('id', $validated['new_room_id'])
                ->orWhere('room_number', $validated['new_room_id'])
                ->lockForUpdate()
                ->first();

            if (!$newRoom) {
                return response()->json(['error' => "New room not found in inventory."], 404);
            }
            if (!$newRoom->is_active) {
                return response()->json(['error' => "Room {$newRoom->room_number} is inactive/archived."], 422);
            }
            if ($newRoom->status === 'maintenance' || $newRoom->housekeeping_status === 'maintenance') {
                return response()->json(['error' => "Room {$newRoom->room_number} is currently under maintenance and cannot be assigned."], 422);
            }
            if ($newRoom->status === 'occupied') {
                return response()->json(['error' => "Room {$newRoom->room_number} is currently occupied."], 422);
            }
            $guestsCount = (int)($booking->num_guests ?: ($booking->adults_count ?: 1));
            if ($newRoom->capacity < $guestsCount) {
                return response()->json(['error' => "Room {$newRoom->room_number} capacity ({$newRoom->capacity}) is less than guests arriving ({$guestsCount})."], 422);
            }

            // Check collision on new room for booking stay dates (exclusive checkout)
            $hasConflict = Booking::where('room_id', $newRoom->id)
                ->where('id', '!=', $booking->id)
                ->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                ->where('check_in_date', '<', $booking->check_out_date)
                ->where('check_out_date', '>', $booking->check_in_date)
                ->exists();

            if ($hasConflict) {
                return response()->json(['error' => "Room {$newRoom->room_number} is not available for the stay dates ({$booking->check_in_date} to {$booking->check_out_date})."], 422);
            }

            $oldRoomNumber = $booking->room?->room_number ?: 'N/A';
            $oldRoom = $booking->room;

            // Update booking room assignment
            $booking->update([
                'room_id' => $newRoom->id,
                'room_number' => $newRoom->room_number,
                'nightly_rate_etb' => (float)$newRoom->price_etb,
                'nightly_rate_usd' => (float)$newRoom->price_usd,
            ]);

            // If multi-room, update target room or primary
            if ($booking->bookingRooms()->exists()) {
                $targetBr = $validated['target_room_id']
                    ? $booking->bookingRooms()->where('room_id', $validated['target_room_id'])->first()
                    : $booking->bookingRooms()->first();
                if ($targetBr) {
                    $targetBr->update([
                        'room_id' => $newRoom->id,
                        'room_number' => $newRoom->room_number,
                        'nightly_rate_etb' => (float)$newRoom->price_etb,
                        'total_price_etb' => (float)$newRoom->price_etb * max(1, Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date))),
                    ]);
                }
            }

            // If already checked in, shift occupancy
            if ($booking->booking_status === 'checked_in') {
                if ($oldRoom) {
                    $oldRoom->update(['status' => 'cleaning', 'housekeeping_status' => 'dirty']);
                }
                $newRoom->update(['status' => 'occupied']);
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'staff',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'room_reassignment',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => [
                    'reference_code' => $booking->reference_code,
                    'old_room' => $oldRoomNumber,
                    'new_room' => $newRoom->room_number,
                    'reason' => $validated['reason'] ?? 'Guest request / Operational adjustment',
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Room successfully reassigned from {$oldRoomNumber} to Room {$newRoom->room_number}.",
                'booking' => $booking->fresh(['room.category']),
            ]);
        });
    }

    /**
     * Direct Walk-In Immediate Check-In from Front Desk.
     */
    public function walkInCheckIn(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'nationality' => 'nullable|string|max:100',
            'id_type' => 'nullable|string|in:national_id,passport,kebele_id,driving_license,resident_id,other',
            'id_number' => 'nullable|string|max:100',
            'room_id' => 'required|string',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'required|date|after_or_equal:today',
            'num_guests' => 'nullable|integer|min:1',
            'key_card_number' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|in:cash,telebirr,cbe_birr,credit_card,bank_transfer',
            'initial_payment_etb' => 'nullable|numeric|min:0',
            'check_in_notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $checkInDate = $validated['check_in_date'] ?: now()->toDateString();
            $checkOutDate = $validated['check_out_date'];
            $nights = max(1, Carbon::parse($checkInDate)->diffInDays(Carbon::parse($checkOutDate)));

            $room = Room::where('id', $validated['room_id'])
                ->orWhere('room_number', $validated['room_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($room->status === 'maintenance' || $room->housekeeping_status === 'maintenance') {
                return response()->json(['error' => "Cannot check in: Room {$room->room_number} is under maintenance."], 422);
            }
            if ($room->status === 'occupied') {
                return response()->json(['error' => "Cannot check in: Room {$room->room_number} is currently occupied."], 422);
            }

            // Find or create guest
            $guest = User::where('phone', $validated['phone'])
                ->orWhere(function ($q) use ($validated) {
                    if (!empty($validated['email'])) $q->where('email', $validated['email']);
                })
                ->first();

            if (!$guest) {
                $guest = User::create([
                    'id' => 'usr-' . time() . '-' . rand(100, 999),
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'full_name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?: ('guest_' . time() . '@hotel.et'),
                    'nationality' => $validated['nationality'] ?? 'Ethiopian',
                    'id_type' => $validated['id_type'] ?? 'national_id',
                    'id_number' => $validated['id_number'] ?? null,
                    'role' => 'guest',
                    'password_hash' => bcrypt('Guest@Pass123'),
                    'is_verified' => true,
                ]);
            } else {
                if (!empty($validated['id_number'])) {
                    $guest->update([
                        'id_type' => $validated['id_type'] ?: $guest->id_type,
                        'id_number' => $validated['id_number'],
                    ]);
                }
            }

            $nightlyRate = (float)$room->price_etb;
            $totalEtb = $nightlyRate * $nights;
            $totalUsd = (float)$room->price_usd * $nights;

            $refCode = 'ETH-' . rand(10000, 99999);
            $booking = Booking::create([
                'id' => 'bk-walkin-' . time() . '-' . rand(100, 999),
                'reference_code' => $refCode,
                'guest_id' => $guest->id,
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'num_guests' => $validated['num_guests'] ?? 1,
                'adults_count' => $validated['num_guests'] ?? 1,
                'nightly_rate_etb' => $nightlyRate,
                'nightly_rate_usd' => (float)$room->price_usd,
                'total_price_etb' => $totalEtb,
                'total_price_usd' => $totalUsd,
                'booking_status' => 'checked_in',
                'payment_status' => (!empty($validated['initial_payment_etb']) && $validated['initial_payment_etb'] >= $totalEtb) ? 'paid' : 'pending',
                'is_walk_in' => true,
                'actual_check_in_at' => now(),
                'checked_in_by' => $request->user()?->id ?: 'usr-admin-1',
                'key_card_number' => $validated['key_card_number'] ?? ('KC-' . $room->room_number),
                'check_in_notes' => $validated['check_in_notes'] ?? 'Front Desk Walk-In Check-In',
            ]);

            BookingRoom::create([
                'id' => 'br-walkin-' . time() . '-' . rand(100, 999),
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'nightly_rate_etb' => $nightlyRate,
                'nightly_rate_usd' => (float)$room->price_usd,
                'total_price_etb' => $totalEtb,
                'status' => 'checked_in',
            ]);

            // Set room status to occupied
            $room->update(['status' => 'occupied']);

            // Record initial payment if captured
            if (!empty($validated['initial_payment_etb']) && $validated['initial_payment_etb'] > 0) {
                Payment::create([
                    'id' => 'pay-' . time() . '-' . rand(100, 999),
                    'transaction_id' => 'TX-WALKIN-' . rand(10000, 99999),
                    'booking_id' => $booking->id,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'amount_etb' => (float)$validated['initial_payment_etb'],
                    'amount_usd' => round(((float)$validated['initial_payment_etb']) / 64, 2),
                    'claimed_amount_etb' => (float)$validated['initial_payment_etb'],
                    'status' => 'verified',
                    'submitted_at' => now(),
                ]);
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'staff',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'walk_in_check_in',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => [
                    'reference_code' => $refCode,
                    'guest_name' => $guest->full_name,
                    'room' => $room->room_number,
                    'initial_payment' => $validated['initial_payment_etb'] ?? 0,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Walk-in guest {$guest->full_name} successfully checked into Room {$room->room_number}.",
                'booking' => $booking->fresh(['room.category', 'guest', 'payments']),
            ], 201);
        });
    }

    /**
     * Post Incidental Service Charge to Guest Folio.
     */
    public function addServiceCharge(Request $request, $id)
    {
        $validated = $request->validate([
            'service_type' => 'required|string|in:restaurant,minibar,room_service,laundry,spa,early_checkin_fee,late_checkout_fee,damage_fee,other',
            'description' => 'required|string|max:255',
            'amount_etb' => 'required|numeric|min:0.01',
            'quantity' => 'nullable|integer|min:1',
            'staff_notes' => 'nullable|string',
        ]);

        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $charge = BookingServiceCharge::create([
            'id' => 'bsc-' . time() . '-' . rand(100, 999),
            'booking_id' => $booking->id,
            'service_type' => $validated['service_type'],
            'description' => $validated['description'],
            'amount_etb' => (float)$validated['amount_etb'],
            'amount_usd' => round(((float)$validated['amount_etb']) / 64, 2),
            'quantity' => $validated['quantity'] ?? 1,
            'posted_by' => $request->user()?->id ?: 'staff',
            'staff_notes' => $validated['staff_notes'] ?? null,
            'charged_at' => now(),
        ]);

        // Log incidental charge in audit trail
        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => 'service_charge_added',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => [
                'service_type' => $charge->service_type,
                'amount_etb' => $charge->amount_etb,
                'description' => $charge->description,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Incidental charge of ETB {$charge->amount_etb} added to guest folio.",
            'charge' => $charge,
            'booking' => $booking->fresh(['serviceCharges', 'payments']),
        ], 201);
    }

    /**
     * Get itemized service charges for booking folio.
     */
    public function getServiceCharges($id)
    {
        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();
        $charges = BookingServiceCharge::where('booking_id', $booking->id)->orderBy('charged_at', 'desc')->get();

        return response()->json([
            'reference_code' => $booking->reference_code,
            'charges' => $charges,
            'total_service_charges_etb' => (float)$charges->sum('amount_etb'),
        ]);
    }

    /**
     * Settle Folio Balance at Check-Out or Mid-Stay.
     */
    public function settlePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount_etb' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:telebirr,cbe_birr,cash,credit_card,bank_transfer',
            'reference_note' => 'nullable|string',
        ]);

        $booking = Booking::with('payments')->where('id', $id)->orWhere('reference_code', $id)->firstOrFail();

        $payment = Payment::create([
            'id' => 'pay-settle-' . time() . '-' . rand(100, 999),
            'transaction_id' => 'TX-SETTLE-' . rand(10000, 99999),
            'booking_id' => $booking->id,
            'payment_method' => $validated['payment_method'],
            'amount_etb' => (float)$validated['amount_etb'],
            'amount_usd' => round(((float)$validated['amount_etb']) / 64, 2),
            'claimed_amount_etb' => (float)$validated['amount_etb'],
            'status' => 'verified',
            'submitted_at' => now(),
        ]);

        $booking->refresh();
        $totalPaid = (float)$booking->payments->where('status', 'verified')->sum('amount_etb');
        $totalGross = (float)$booking->total_price_etb;

        if ($totalPaid >= $totalGross) {
            $booking->update(['payment_status' => 'paid']);
        }

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'cashier',
            'action' => 'folio_settlement_payment',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => [
                'reference_code' => $booking->reference_code,
                'settled_amount_etb' => $validated['amount_etb'],
                'payment_method' => $validated['payment_method'],
                'new_payment_status' => $booking->payment_status,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Payment of ETB {$validated['amount_etb']} verified and recorded.",
            'payment' => $payment,
            'booking' => $booking->fresh(['payments']),
        ]);
    }

    /**
     * Approve Early Check-In with optional surcharge fee.
     */
    public function approveEarlyCheckIn(Request $request, $id)
    {
        $validated = $request->validate([
            'early_fee_etb' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();
        $fee = (float)($validated['early_fee_etb'] ?? 0);

        $booking->update([
            'early_check_in_approved' => true,
            'early_check_in_fee_etb' => $fee,
        ]);

        if ($fee > 0) {
            BookingServiceCharge::create([
                'id' => 'bsc-early-' . time() . '-' . rand(100, 999),
                'booking_id' => $booking->id,
                'service_type' => 'early_checkin_fee',
                'description' => 'Approved Early Check-In Surcharge Fee',
                'amount_etb' => $fee,
                'amount_usd' => round($fee / 64, 2),
                'quantity' => 1,
                'posted_by' => $request->user()?->id ?: 'staff',
                'staff_notes' => $validated['notes'] ?? null,
                'charged_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Early check-in approved.',
            'booking' => $booking->fresh(),
        ]);
    }

    /**
     * Approve Late Check-Out with optional surcharge fee.
     */
    public function approveLateCheckOut(Request $request, $id)
    {
        $validated = $request->validate([
            'late_fee_etb' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $booking = Booking::where('id', $id)->orWhere('reference_code', $id)->firstOrFail();
        $fee = (float)($validated['late_fee_etb'] ?? 0);

        $booking->update([
            'late_check_out_approved' => true,
            'late_check_out_fee_etb' => $fee,
        ]);

        if ($fee > 0) {
            BookingServiceCharge::create([
                'id' => 'bsc-late-' . time() . '-' . rand(100, 999),
                'booking_id' => $booking->id,
                'service_type' => 'late_checkout_fee',
                'description' => 'Approved Late Check-Out Surcharge Fee',
                'amount_etb' => $fee,
                'amount_usd' => round($fee / 64, 2),
                'quantity' => 1,
                'posted_by' => $request->user()?->id ?: 'staff',
                'staff_notes' => $validated['notes'] ?? null,
                'charged_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Late check-out approved.',
            'booking' => $booking->fresh(),
        ]);
    }

    /**
     * Front-Desk Operations Audit Ledger.
     */
    public function operationsHistory(Request $request)
    {
        $limit = min(50, (int)$request->input('limit', 20));
        $logs = AuditLog::with('user')
            ->whereIn('action', [
                'guest_check_in',
                'guest_check_out',
                'walk_in_check_in',
                'room_reassignment',
                'service_charge_added',
                'folio_settlement_payment'
            ])
            ->orWhere('resource_type', 'booking')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'logs' => $logs,
            'count' => $logs->count(),
        ]);
    }

    /**
     * Check-out eligible departures list.
     */
    public function checkOutEligible()
    {
        $eligible = Booking::with(['guest', 'room.category', 'bookingRooms.room.category', 'payments', 'serviceCharges'])
            ->where('booking_status', 'checked_in')
            ->get()
            ->map(function ($b) {
                $verifiedPay = (float)$b->payments->where('status', 'verified')->sum('amount_etb');
                $grossTotal = (float)$b->total_price_etb;
                $outstanding = max(0, $grossTotal - $verifiedPay);

                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'guest_id' => $b->guest?->id,
                    'guest_name' => $b->guest?->full_name ?: 'Guest',
                    'guest_phone' => $b->guest?->phone ?: 'N/A',
                    'guest_email' => $b->guest?->email ?: 'N/A',
                    'room_number' => $b->room?->room_number ?: ($b->bookingRooms->first()?->room_number ?: '101'),
                    'category_name' => $b->room?->category?->name ?: 'Suite',
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'actual_check_in_at' => $b->actual_check_in_at,
                    'key_card_number' => $b->key_card_number,
                    'total_price_etb' => (float)$b->total_price_etb,
                    'gross_total_etb' => $grossTotal,
                    'verified_paid_etb' => $verifiedPay,
                    'outstanding_balance_etb' => $outstanding,
                ];
            });

        return response()->json(['eligible_departures' => $eligible]);
    }

    /**
     * Process Check-Out.
     */
    public function checkOut(Request $request, $id)
    {
        $booking = Booking::with(['room', 'bookingRooms.room', 'guest', 'payments', 'serviceCharges'])
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        if ($booking->booking_status !== 'checked_in') {
            return response()->json(['error' => "Cannot check out: Reservation is currently '{$booking->booking_status}', only checked-in guests can be checked out."], 422);
        }

        // Folio balance verification
        $verifiedPay = (float)$booking->payments->where('status', 'verified')->sum('amount_etb');
        $grossTotal = (float)($booking->gross_total_etb ?: $booking->total_price_etb);
        $outstanding = max(0, $grossTotal - $verifiedPay);

        // If payment details provided in checkout payload, capture settlement payment
        if ($outstanding > 0 && $request->filled('settle_amount_etb') && $request->filled('payment_method')) {
            $settleAmount = (float)$request->input('settle_amount_etb');
            Payment::create([
                'id' => 'pay-settle-' . time() . '-' . rand(100, 999),
                'transaction_id' => 'TX-SETTLE-' . rand(10000, 99999),
                'booking_id' => $booking->id,
                'payment_method' => $request->input('payment_method'),
                'amount_etb' => $settleAmount,
                'amount_usd' => round($settleAmount / 64, 2),
                'claimed_amount_etb' => $settleAmount,
                'status' => 'verified',
                'submitted_at' => now(),
            ]);
            $verifiedPay += $settleAmount;
            $outstanding = max(0, $grossTotal - $verifiedPay);
            if ($outstanding <= 0) {
                $booking->update(['payment_status' => 'paid']);
            }
        } elseif ($outstanding > 0 && $request->boolean('require_zero_balance', false)) {
            return response()->json([
                'error' => "Outstanding balance of ETB {$outstanding} must be settled before checkout or approved by management.",
                'outstanding_balance_etb' => $outstanding,
            ], 422);
        }

        $booking->update([
            'booking_status' => 'checked_out',
            'actual_check_out_at' => now(),
            'checked_out_by' => $request->user()?->id ?: 'usr-admin-1',
            'check_out_notes' => $request->input('check_out_notes'),
        ]);

        $rooms = $booking->bookingRooms->isNotEmpty() ? $booking->bookingRooms->pluck('room') : collect([$booking->room]);
        foreach ($rooms as $r) {
            if ($r) {
                $r->update([
                    'status' => 'cleaning',
                    'housekeeping_status' => 'dirty',
                ]);
            }
        }
        BookingRoom::where('booking_id', $booking->id)->update(['status' => 'checked_out']);

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => 'guest_check_out',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => [
                'reference_code' => $booking->reference_code,
                'room' => $booking->room?->room_number,
                'total_settled_etb' => $grossTotal,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Check-out completed, room released to housekeeping, and departure tax invoice generated.',
            'booking' => $booking->fresh(),
        ]);
    }

    /**
     * Compatibility helper: Update booking status directly from Front Desk.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,rejected,pending_verification,no_show',
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validated['status'] === 'cancelled') {
            $request->merge(['cancellation_reason' => $validated['rejection_reason'] ?? 'Cancelled from front desk']);
            return $this->cancel($request, $id);
        }
        if ($validated['status'] === 'no_show') {
            return $this->markNoShow($request, $id);
        }

        $booking = Booking::findOrFail($id);
        $booking->update(['booking_status' => $validated['status']]);

        return response()->json(['success' => true, 'booking' => $booking]);
    }

    /**
     * Generate Tax Invoice.
     */
    public function generateInvoice($id)
    {
        $booking = Booking::with(['room.category', 'bookingRooms.room.category', 'guest', 'payments', 'serviceCharges'])
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        $d1 = Carbon::parse($booking->check_in_date);
        $d2 = Carbon::parse($booking->check_out_date);
        $nights = max(1, $d1->diffInDays($d2));

        $totalPrice = (float)($booking->gross_total_etb ?: $booking->total_price_etb);
        $totalPaid = (float)$booking->payments->where('status', 'verified')->sum('amount_etb');
        $outstanding = max(0, $totalPrice - $totalPaid);

        $roomItems = $booking->bookingRooms->map(function ($br) use ($nights) {
            return [
                'description' => "Room {$br->room_number} (" . ($br->room?->category?->name ?: 'Deluxe') . ") - {$nights} nights",
                'room_number' => $br->room_number,
                'category' => $br->room?->category?->name ?: 'Deluxe',
                'nights' => $nights,
                'rate_per_night' => (float)$br->nightly_rate_etb,
                'subtotal_etb' => (float)$br->total_price_etb,
            ];
        });

        if ($roomItems->isEmpty() && $booking->room) {
            $rRate = (float)($booking->nightly_rate_etb ?: $booking->room->price_etb);
            $roomItems = collect([[
                'description' => "Room {$booking->room->room_number} (" . ($booking->room->category?->name ?: 'Deluxe') . ") - {$nights} nights",
                'room_number' => $booking->room->room_number,
                'category' => $booking->room->category?->name ?: 'Deluxe',
                'nights' => $nights,
                'rate_per_night' => $rRate,
                'subtotal_etb' => $rRate * $nights,
            ]]);
        }

        $serviceItems = $booking->serviceCharges->map(function ($sc) {
            return [
                'type' => $sc->service_type,
                'description' => $sc->description,
                'quantity' => $sc->quantity,
                'amount_etb' => (float)$sc->amount_etb,
            ];
        });

        // Fiscal Tax Calculation (Standard Ethiopian Hospitality Rate)
        $taxVat = round($totalPrice * 0.15, 2);
        $taxService = round($totalPrice * 0.10, 2);
        $taxTourism = round($totalPrice * 0.02, 2);

        $invoice = [
            'invoice_number' => 'INV-ETH-' . date('Y') . '-' . preg_replace('/[^A-Za-z0-9]/', '', $booking->reference_code),
            'fiscal_date' => now()->toFormattedDateString(),
            'hotel_name' => 'Ethiopian Luxury Resort & Heritage Suites',
            'hotel_tin' => '0048921849',
            'hotel_vat_reg' => '991823901',
            'hotel_address' => 'Bole Medhanealem Avenue, Addis Ababa, Ethiopia',
            'hotel_phone' => '+251 11 662 0000',
            'reference_code' => $booking->reference_code,
            'guest' => [
                'id' => $booking->guest?->id,
                'name' => $booking->guest?->full_name ?: 'Valued Guest',
                'phone' => $booking->guest?->phone ?: 'N/A',
                'email' => $booking->guest?->email ?: 'N/A',
                'nationality' => $booking->guest?->nationality ?: 'Ethiopian',
                'id_number' => $booking->guest?->masked_id_number,
            ],
            'stay_period' => [
                'check_in' => $booking->check_in_date,
                'check_out' => $booking->check_out_date,
                'actual_check_in_at' => $booking->actual_check_in_at ? $booking->actual_check_in_at->format('Y-m-d H:i') : null,
                'actual_check_out_at' => $booking->actual_check_out_at ? $booking->actual_check_out_at->format('Y-m-d H:i') : null,
                'nights' => $nights,
            ],
            'room_items' => $roomItems->values(),
            'service_items' => $serviceItems->values(),
            'taxes' => [
                'vat_15' => $taxVat,
                'service_charge_10' => $taxService,
                'tourism_levy_2' => $taxTourism,
            ],
            'financial' => [
                'total_amount_etb' => $totalPrice,
                'total_amount_usd' => (float)$booking->total_price_usd,
                'amount_paid_etb' => $totalPaid,
                'outstanding_balance_etb' => $outstanding,
                'payment_status' => $booking->payment_status,
            ],
            'payments' => $booking->payments->map(fn($p) => [
                'transaction_id' => $p->transaction_id,
                'payment_method' => $p->payment_method,
                'amount_etb' => (float)$p->amount_etb,
                'status' => $p->status,
                'date' => Carbon::parse($p->submitted_at ?: $p->created_at)->toFormattedDateString(),
            ])->values(),
            'qr_verification_hash' => $booking->qr_verification_hash,
        ];

        return response()->json(['invoice' => $invoice]);
    }

    /**
     * Guest Portal Summary.
     */
    public function guestPortalSummary(Request $request)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';
        $today = now()->toDateString();

        $activeBooking = Booking::with(['room.category', 'room.images'])
            ->where('guest_id', $userId)
            ->whereIn('booking_status', ['checked_in', 'confirmed'])
            ->where('check_out_date', '>=', $today)
            ->orderBy('check_in_date', 'asc')
            ->first();

        $primaryImg = $activeBooking?->room?->images->where('is_primary', 1)->first() ?: $activeBooking?->room?->images->first();

        return response()->json([
            'has_active_stay' => $activeBooking !== null,
            'booking' => $activeBooking ? [
                'id' => $activeBooking->id,
                'reference_code' => $activeBooking->reference_code,
                'room_number' => $activeBooking->room?->room_number,
                'room_title' => $activeBooking->room?->title,
                'category_name' => $activeBooking->room?->category?->name,
                'check_in_date' => $activeBooking->check_in_date,
                'check_out_date' => $activeBooking->check_out_date,
                'booking_status' => $activeBooking->booking_status,
                'image_url' => $primaryImg?->image_url,
            ] : null,
        ]);
    }

    /**
     * Guest Self-Service: My Bookings.
     */
    public function myBookings(Request $request)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';

        $bookings = Booking::with(['room.category', 'room.images', 'payments', 'bookingRooms.room'])
            ->where('guest_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($b) {
                $primaryImg = $b->room?->images->where('is_primary', 1)->first() ?: $b->room?->images->first();
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'room_number' => $b->room?->room_number,
                    'room_title' => $b->room?->title,
                    'category_name' => $b->room?->category?->name,
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'total_price_etb' => (float)$b->total_price_etb,
                    'booking_status' => $b->booking_status,
                    'payment_status' => $b->payment_status,
                    'image_url' => $primaryImg?->image_url ?: '/uploads/rooms/default.jpg',
                ];
            });

        return response()->json(['bookings' => $bookings]);
    }
}
