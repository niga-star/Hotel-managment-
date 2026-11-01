<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Payment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class BookingController extends Controller
{
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|string',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'nullable|integer',
            'children' => 'nullable|integer',
            'guest_name' => 'nullable|string',
            'guest_email' => 'nullable|email',
            'guest_phone' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'payment_mode' => 'nullable|string',
            'promo_code' => 'nullable|string',
            'meal_plan' => 'nullable|string',
            'addons' => 'nullable|array',
            'is_walk_in' => 'nullable|boolean',
        ]);

        $roomId = $validated['room_id'];
        $checkIn = $validated['check_in_date'];
        $checkOut = $validated['check_out_date'];
        $adults = (int)($validated['adults'] ?? 1);
        $children = (int)($validated['children'] ?? 0);
        $paymentMethod = $validated['payment_method'] ?? 'telebirr';
        $paymentMode = $validated['payment_mode'] ?? 'manual_receipt';
        $promoCode = $validated['promo_code'] ?? null;
        $mealPlan = $validated['meal_plan'] ?? 'room_only';
        $addons = $validated['addons'] ?? [];
        $isWalkIn = (bool)($validated['is_walk_in'] ?? false);

        return DB::transaction(function () use (
            $roomId, $checkIn, $checkOut, $adults, $children, $paymentMethod,
            $paymentMode, $promoCode, $mealPlan, $addons, $isWalkIn, $request
        ) {
            // 1. Pessimistic Row Lock & Double-Booking Check
            $room = Room::where('id', $roomId)
                ->orWhere('room_number', $roomId)
                ->lockForUpdate()
                ->first();

            if (!$room) {
                return response()->json(['error' => 'Room not found.'], 404);
            }

            if ($room->status === 'maintenance') {
                return response()->json(['error' => 'Sorry, this room is currently under maintenance.'], 409);
            }

            $overlapping = Booking::where('room_id', $room->id)
                ->whereIn('booking_status', ['confirmed', 'checked_in', 'pending_verification'])
                ->where('check_in_date', '<', $checkOut)
                ->where('check_out_date', '>', $checkIn)
                ->lockForUpdate()
                ->exists();

            if ($overlapping) {
                return response()->json(['error' => 'Sorry, this room is no longer available for your selected dates.'], 409);
            }

            // 2. Calculate Nights & Dynamic Pricing
            $d1 = Carbon::parse($checkIn);
            $d2 = Carbon::parse($checkOut);
            $nights = max(1, $d1->diffInDays($d2));

            $mealPlanRates = [
                'room_only' => 0,
                'bed_breakfast' => 650,
                'half_board' => 1450,
                'full_board' => 2200,
            ];
            $guestTotal = max(1, $adults + $children);
            $mealTotalEtb = ($mealPlanRates[$mealPlan] ?? 0) * $guestTotal * $nights;

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

            $totalPriceEtb = ((float)$room->price_etb * $nights) + $mealTotalEtb + $addonsTotalEtb;
            $totalPriceUsd = round($totalPriceEtb / 64);

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

            // 3. Resolve Guest User
            $guestId = null;
            $authUser = $request->user();

            if ($authUser && $authUser->id && !str_starts_with($authUser->id, 'usr-guest-6')) {
                $guestId = $authUser->id;
            } elseif ($request->guest_email) {
                $existing = User::where('email', strtolower(trim($request->guest_email)))->first();
                if ($existing) {
                    $guestId = $existing->id;
                } else {
                    $guestId = 'usr-guest-' . time() . '-' . rand(10, 99);
                    User::create([
                        'id' => $guestId,
                        'role' => 'guest',
                        'full_name' => $request->guest_name ?: 'Guest User',
                        'email' => strtolower(trim($request->guest_email)),
                        'phone' => $request->guest_phone ?: '+251 911 000000',
                        'password_hash' => Hash::make('Hotel@Guest2026'),
                        'is_verified' => 1,
                    ]);
                }
            } else {
                $guestId = 'usr-guest-6';
            }

            // 4. Generate Reference Code & Booking Record
            $refNum = rand(10000, 99999);
            $referenceCode = "#ETH-{$refNum}";
            $bookingId = 'bk-' . time() . '-' . rand(100, 999);

            $initialStatus = $isWalkIn ? 'confirmed' : ($paymentMode === 'gateway' ? 'pending_payment' : 'pending_verification');
            $initialPaymentStatus = $isWalkIn ? 'paid' : ($paymentMode === 'gateway' ? 'pending' : 'pending_verification');

            $booking = Booking::create([
                'id' => $bookingId,
                'reference_code' => $referenceCode,
                'guest_id' => $guestId,
                'room_id' => $room->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults_count' => $adults,
                'children_count' => $children,
                'total_price_etb' => $totalPriceEtb,
                'total_price_usd' => $totalPriceUsd,
                'booking_status' => $initialStatus,
                'payment_status' => $initialPaymentStatus,
                'is_walk_in' => $isWalkIn ? 1 : 0,
                'qr_verification_hash' => "qr_hash_{$refNum}",
                'notes' => json_encode(['meal_plan' => $mealPlan, 'addons' => $addons, 'promo_code' => $promoCode]),
                'hold_expires_at' => $paymentMode === 'gateway' ? now()->addMinutes(30) : null,
            ]);

            // 5. Payment Transaction Record
            Payment::create([
                'id' => "pay-{$bookingId}",
                'transaction_id' => "TX-{$refNum}",
                'booking_id' => $bookingId,
                'payment_method' => $paymentMethod,
                'amount_etb' => $totalPriceEtb,
                'amount_usd' => $totalPriceUsd,
                'claimed_amount_etb' => $totalPriceEtb,
                'status' => $initialPaymentStatus === 'paid' ? 'verified' : $initialPaymentStatus,
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reservation successfully recorded in MySQL.',
                'booking' => [
                    'id' => $booking->id,
                    'reference_code' => $booking->reference_code,
                    'total_price_etb' => (float)$booking->total_price_etb,
                    'total_price_usd' => (float)$booking->total_price_usd,
                    'booking_status' => $booking->booking_status,
                    'check_in_date' => $booking->check_in_date,
                    'check_out_date' => $booking->check_out_date,
                ]
            ], 201);
        });
    }

    public function myBookings(Request $request)
    {
        $userId = $request->user()?->id ?: 'usr-guest-6';

        $bookings = Booking::with(['room.category', 'room.images'])
            ->where('guest_id', $userId)
            ->orWhere('id', 'LIKE', 'bk-%') // Include recent stays for demo
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($b) {
                $primaryImg = $b->room?->images?->where('is_primary', 1)->first() ?: $b->room?->images?->first();
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'room_id' => $b->room_id,
                    'room_title' => $b->room?->title ?: 'Luxury Suite',
                    'category_name' => $b->room?->category?->name ?: 'Suite',
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'total_price_etb' => (float)$b->total_price_etb,
                    'total_price_usd' => (float)$b->total_price_usd,
                    'booking_status' => $b->booking_status,
                    'payment_status' => $b->payment_status,
                    'image_url' => $primaryImg?->image_url ?: '/uploads/rooms/default.jpg',
                ];
            });

        return response()->json(['bookings' => $bookings]);
    }

    public function desk(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = Booking::with(['guest', 'room.category', 'payments']);

        if ($status && $status !== 'all') {
            $query->where('booking_status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'LIKE', "%$search%")
                  ->orWhereHas('guest', fn($g) => $g->where('full_name', 'LIKE', "%$search%"))
                  ->orWhereHas('room', fn($r) => $r->where('room_number', 'LIKE', "%$search%"));
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->get()->map(function ($b) {
            $verifiedPay = $b->payments->where('status', 'verified')->sum('amount_etb');
            return [
                'id' => $b->id,
                'reference_code' => $b->reference_code,
                'guest_id' => $b->guest_id,
                'guest_name' => $b->guest?->full_name ?: 'Valued Guest',
                'guest_email' => $b->guest?->email ?: 'N/A',
                'guest_phone' => $b->guest?->phone ?: 'N/A',
                'room_id' => $b->room_id,
                'room_number' => $b->room?->room_number ?: '101',
                'room_title' => $b->room?->title ?: 'Suite',
                'category_name' => $b->room?->category?->name ?: 'Suite',
                'check_in_date' => $b->check_in_date,
                'check_out_date' => $b->check_out_date,
                'total_price_etb' => (float)$b->total_price_etb,
                'total_price_usd' => (float)$b->total_price_usd,
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
                'outstanding_balance_etb' => max(0, (float)$b->total_price_etb - $verifiedPay),
            ];
        });

        return response()->json(['bookings' => $bookings]);
    }

    public function checkInEligible()
    {
        $eligible = Booking::with(['guest', 'room.category'])
            ->where('booking_status', 'confirmed')
            ->orderBy('check_in_date', 'asc')
            ->get()
            ->map(function ($b) {
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'guest_name' => $b->guest?->full_name ?: 'Guest',
                    'guest_phone' => $b->guest?->phone ?: 'N/A',
                    'guest_email' => $b->guest?->email ?: 'N/A',
                    'room_id' => $b->room_id,
                    'room_number' => $b->room?->room_number ?: '101',
                    'category_name' => $b->room?->category?->name ?: 'Suite',
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'payment_status' => $b->payment_status,
                    'is_room_ready' => ($b->room?->status === 'available'),
                ];
            });

        return response()->json(['eligible_arrivals' => $eligible]);
    }

    public function checkIn(Request $request, $id)
    {
        $booking = Booking::with('room', 'guest')
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        $booking->update([
            'booking_status' => 'checked_in',
            'actual_check_in_at' => now(),
            'checked_in_by' => $request->user()?->id ?: 'usr-admin-1',
        ]);

        if ($booking->room) {
            $booking->room->update(['status' => 'occupied']);
        }

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => 'guest_check_in',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => ['reference_code' => $booking->reference_code, 'room' => $booking->room?->room_number],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Guest {$booking->guest?->full_name} checked into Room {$booking->room?->room_number}.",
            'booking' => $booking,
        ]);
    }

    public function checkOutEligible()
    {
        $eligible = Booking::with(['guest', 'room.category', 'payments'])
            ->where('booking_status', 'checked_in')
            ->get()
            ->map(function ($b) {
                $verifiedPay = $b->payments->where('status', 'verified')->sum('amount_etb');
                return [
                    'id' => $b->id,
                    'reference_code' => $b->reference_code,
                    'guest_name' => $b->guest?->full_name ?: 'Guest',
                    'guest_phone' => $b->guest?->phone ?: 'N/A',
                    'guest_email' => $b->guest?->email ?: 'N/A',
                    'room_number' => $b->room?->room_number ?: '101',
                    'category_name' => $b->room?->category?->name ?: 'Suite',
                    'check_in_date' => $b->check_in_date,
                    'check_out_date' => $b->check_out_date,
                    'actual_check_in_at' => $b->actual_check_in_at,
                    'total_price_etb' => (float)$b->total_price_etb,
                    'outstanding_balance_etb' => max(0, (float)$b->total_price_etb - $verifiedPay),
                ];
            });

        return response()->json(['eligible_departures' => $eligible]);
    }

    public function checkOut(Request $request, $id)
    {
        $booking = Booking::with('room', 'guest')
            ->where('id', $id)
            ->orWhere('reference_code', $id)
            ->firstOrFail();

        $booking->update([
            'booking_status' => 'checked_out',
            'actual_check_out_at' => now(),
            'checked_out_by' => $request->user()?->id ?: 'usr-admin-1',
        ]);

        if ($booking->room) {
            $booking->room->update([
                'status' => 'available',
                'housekeeping_status' => 'dirty', // Marks dirty automatically for turnover
            ]);
        }

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'staff',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => 'guest_check_out',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
            'details' => ['reference_code' => $booking->reference_code, 'room' => $booking->room?->room_number],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $invoice = [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'total_price_etb' => (float)$booking->total_price_etb,
            'status' => 'paid',
            'actual_check_out_at' => $booking->actual_check_out_at,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Check-out completed and departure tax invoice generated.',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Update booking status directly from Front Desk (Approve pending, Cancel, Reject).
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,rejected,pending_verification',
            'rejection_reason' => 'nullable|string',
        ]);

        $status = $validated['status'];
        $reason = $validated['rejection_reason'] ?? null;

        return DB::transaction(function () use ($id, $status, $reason, $request) {
            $booking = Booking::with('room')
                ->where('id', $id)
                ->orWhere('reference_code', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($status, ['cancelled', 'rejected'])) {
                $booking->update([
                    'booking_status' => $status,
                    'payment_status' => $status,
                    'hold_expires_at' => null,
                    'notes' => $reason ? ($booking->notes . "\n[Desk] " . $reason) : $booking->notes,
                ]);

                Payment::where('booking_id', $booking->id)->update([
                    'status' => $status,
                    'rejection_reason' => $reason,
                ]);

                if ($booking->room && $booking->room->status !== 'maintenance') {
                    $booking->room->update(['status' => 'available']);
                }
            } else {
                $booking->update([
                    'booking_status' => $status,
                ]);
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'staff',
                'action' => 'booking_status_change',
                'resource_type' => 'booking',
                'resource_id' => $booking->id,
                'details' => ['new_status' => $status, 'reference_code' => $booking->reference_code],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => "Booking status updated to {$status}.",
                'booking_id' => $booking->id,
                'new_status' => $status,
            ]);
        });
    }
}
