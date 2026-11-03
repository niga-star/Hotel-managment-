<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GuestController extends Controller
{
    /**
     * Guest Management Dashboard Statistics.
     */
    public function dashboardStats(Request $request)
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateTimeString();

        $totalGuests = User::where('role', 'guest')->count();
        $newGuestsThisMonth = User::where('role', 'guest')->where('created_at', '>=', $startOfMonth)->count();

        // Returning guests (guests with >= 2 bookings or >= 1 completed stay)
        $returningGuestsCount = User::where('role', 'guest')
            ->whereHas('bookings', function ($q) {
                $q->whereIn('booking_status', ['checked_out', 'checked_in', 'confirmed']);
            }, '>=', 2)
            ->count();

        // Guests currently in-house (checked_in)
        $inHouseGuestsCount = User::where('role', 'guest')
            ->whereHas('bookings', function ($q) {
                $q->where('booking_status', 'checked_in');
            })
            ->count();

        // Upcoming arrivals within the next 7 days
        $upcomingArrivalsCount = User::where('role', 'guest')
            ->whereHas('bookings', function ($q) use ($today) {
                $q->whereIn('booking_status', ['confirmed', 'pending_verification'])
                  ->where('check_in_date', '>=', $today)
                  ->where('check_in_date', '<=', now()->addDays(7)->toDateString());
            })
            ->count();

        $vipGuestsCount = User::where('role', 'guest')
            ->whereIn('vip_status', ['vip', 'vvip'])
            ->count();

        $recentGuests = User::where('role', 'guest')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'full_name' => $u->full_name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'vip_status' => $u->vip_status,
                    'created_at' => $u->created_at->toFormattedDateString(),
                ];
            });

        return response()->json([
            'stats' => [
                'total_guests' => $totalGuests,
                'new_guests_this_month' => $newGuestsThisMonth,
                'returning_guests' => $returningGuestsCount,
                'in_house_guests' => $inHouseGuestsCount,
                'upcoming_arrivals' => $upcomingArrivalsCount,
                'vip_guests' => $vipGuestsCount,
            ],
            'recent_guests' => $recentGuests,
        ]);
    }

    /**
     * Check if a guest already exists (Real-time returning guest detection helper).
     */
    public function checkDuplicate(Request $request)
    {
        $email = $request->query('email');
        $phone = $request->query('phone');
        $idNumber = $request->query('id_number');
        $name = $request->query('name');
        $excludeId = $request->query('exclude_id');

        $query = User::where('role', 'guest');
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $matches = collect();

        if ($email) {
            $m = (clone $query)->where('email', strtolower(trim($email)))->first();
            if ($m) $matches->push(['field' => 'email', 'guest' => $m]);
        }
        if ($phone) {
            $m = (clone $query)->where('phone', trim($phone))->first();
            if ($m && !$matches->contains('guest.id', $m->id)) {
                $matches->push(['field' => 'phone', 'guest' => $m]);
            }
        }
        if ($idNumber) {
            $m = (clone $query)->where('id_number', trim($idNumber))->first();
            if ($m && !$matches->contains('guest.id', $m->id)) {
                $matches->push(['field' => 'id_number', 'guest' => $m]);
            }
        }
        if ($name && $matches->isEmpty()) {
            $m = (clone $query)->where('full_name', 'LIKE', "%" . trim($name) . "%")->take(3)->get();
            foreach ($m as $g) {
                if (!$matches->contains('guest.id', $g->id)) {
                    $matches->push(['field' => 'name', 'guest' => $g]);
                }
            }
        }

        return response()->json([
            'is_duplicate' => $matches->isNotEmpty(),
            'matches' => $matches->map(function ($item) {
                $g = $item['guest'];
                return [
                    'matched_on' => $item['field'],
                    'id' => $g->id,
                    'full_name' => $g->full_name,
                    'email' => $g->email,
                    'phone' => $g->phone,
                    'id_type' => $g->id_type,
                    'id_number' => $g->masked_id_number,
                    'vip_status' => $g->vip_status,
                    'bookings_count' => $g->bookings()->count(),
                ];
            })->values(),
        ]);
    }

    /**
     * List all registered guests with search, filters, sorting, and pagination.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all'); // all, active, inactive
        $vipStatus = $request->query('vip_status', 'all'); // standard, vip, vvip, blacklist
        $idType = $request->query('id_type', 'all');
        $returning = $request->query('returning', 'all'); // all, returning, first_time
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, max(5, (int)$request->query('per_page', 15)));

        $query = User::where('role', 'guest')->withCount([
            'bookings as total_bookings_count',
            'bookings as completed_stays_count' => function ($q) {
                $q->where('booking_status', 'checked_out');
            },
            'bookings as active_stays_count' => function ($q) {
                $q->where('booking_status', 'checked_in');
            }
        ]);

        // Search (Name, email, phone, ID number, Guest ID)
        if (!empty($search)) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('id_number', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        // Active / Inactive filter
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // VIP status filter
        if ($vipStatus !== 'all' && !empty($vipStatus)) {
            $query->where('vip_status', $vipStatus);
        }

        // ID type filter
        if ($idType !== 'all' && !empty($idType)) {
            $query->where('id_type', $idType);
        }

        // Returning vs First-Time
        if ($returning === 'returning') {
            $query->has('bookings', '>=', 2);
        } elseif ($returning === 'first_time') {
            $query->has('bookings', '<', 2);
        }

        // Registration Date Range
        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Sorting
        if ($sortBy === 'name') {
            $query->orderBy('full_name', $sortDir);
        } elseif ($sortBy === 'bookings_count') {
            $query->orderBy('total_bookings_count', $sortDir);
        } elseif ($sortBy === 'stays_count') {
            $query->orderBy('completed_stays_count', $sortDir);
        } else {
            $query->orderBy('created_at', $sortDir);
        }

        $paginated = $query->paginate($perPage);

        // Check if user is authorized to view unmasked sensitive ID data
        $canViewFullId = $this->hasIdViewPermission($request);

        $items = collect($paginated->items())->map(function ($u) use ($canViewFullId) {
            return [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'gender' => $u->gender,
                'email' => $u->email,
                'phone' => $u->phone,
                'nationality' => $u->nationality,
                'city' => $u->city,
                'country' => $u->country,
                'id_type' => $u->id_type,
                'id_number' => $canViewFullId ? $u->id_number : $u->masked_id_number,
                'id_issue_country' => $u->id_issue_country,
                'id_expiry_date' => $u->id_expiry_date ? $u->id_expiry_date->format('Y-m-d') : null,
                'vip_status' => $u->vip_status,
                'preferences' => $u->preferences,
                'notes' => $u->notes,
                'is_active' => (bool)$u->is_active,
                'total_bookings_count' => (int)$u->total_bookings_count,
                'completed_stays_count' => (int)$u->completed_stays_count,
                'active_stays_count' => (int)$u->active_stays_count,
                'is_returning' => $u->total_bookings_count >= 2 || $u->completed_stays_count >= 1,
                'created_at' => $u->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $u->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'guests' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    /**
     * Register a new guest with duplicate prevention and validation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:60',
            'last_name' => 'nullable|string|max:60',
            'full_name' => 'nullable|string|max:120',
            'email' => 'required|email|max:160',
            'phone' => 'required|string|max:30',
            'gender' => 'nullable|in:male,female,other,unspecified',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'id_type' => 'nullable|in:national_id,passport,driving_license,kebele_id,resident_id,other',
            'id_number' => 'nullable|string|max:100',
            'id_issue_country' => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
            'vip_status' => 'nullable|in:standard,vip,vvip,blacklist',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:50',
        ]);

        $email = strtolower(trim($validated['email']));
        $phone = trim($validated['phone']);
        $idNumber = !empty($validated['id_number']) ? trim($validated['id_number']) : null;

        // 1. Accidental Duplicate Prevention Checks
        $existingEmail = User::where('email', $email)->first();
        if ($existingEmail) {
            return response()->json([
                'error' => "A guest is already registered with email '{$email}' (Existing Guest: {$existingEmail->full_name}, ID: {$existingEmail->id}).",
                'duplicate_field' => 'email',
                'existing_guest_id' => $existingEmail->id,
            ], 409);
        }

        $existingPhone = User::where('phone', $phone)->first();
        if ($existingPhone) {
            return response()->json([
                'error' => "A guest is already registered with phone number '{$phone}' (Existing Guest: {$existingPhone->full_name}, ID: {$existingPhone->id}).",
                'duplicate_field' => 'phone',
                'existing_guest_id' => $existingPhone->id,
            ], 409);
        }

        if ($idNumber) {
            $existingId = User::where('id_number', $idNumber)->first();
            if ($existingId) {
                return response()->json([
                    'error' => "A guest is already registered with this ID number '{$idNumber}' (Existing Guest: {$existingId->full_name}, ID: {$existingId->id}).",
                    'duplicate_field' => 'id_number',
                    'existing_guest_id' => $existingId->id,
                ], 409);
            }
        }

        // Format names
        $firstName = trim($validated['first_name'] ?? '');
        $lastName = trim($validated['last_name'] ?? '');
        $fullName = trim($validated['full_name'] ?? '');

        if (empty($fullName)) {
            $fullName = trim("{$firstName} {$lastName}");
        }
        if (empty($fullName)) {
            $fullName = 'Guest User';
        }
        if (empty($firstName) && !empty($fullName)) {
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0] ?? $fullName;
            $lastName = $parts[1] ?? '';
        }

        return DB::transaction(function () use ($validated, $email, $phone, $idNumber, $fullName, $firstName, $lastName, $request) {
            $guestId = 'usr-guest-' . time() . '-' . rand(100, 999);

            $guest = User::create([
                'id' => $guestId,
                'role' => 'guest',
                'is_verified' => true,
                'full_name' => $fullName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $validated['gender'] ?? 'unspecified',
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'nationality' => $validated['nationality'] ?? 'Ethiopian',
                'email' => $email,
                'phone' => $phone,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? 'Addis Ababa',
                'country' => $validated['country'] ?? 'Ethiopia',
                'id_type' => $validated['id_type'] ?? null,
                'id_number' => $idNumber,
                'id_issue_country' => $validated['id_issue_country'] ?? 'Ethiopia',
                'id_expiry_date' => $validated['id_expiry_date'] ?? null,
                'vip_status' => $validated['vip_status'] ?? 'standard',
                'preferences' => $validated['preferences'] ?? [
                    'smoking' => false,
                    'floor_preference' => 'any',
                    'bed_type' => 'king',
                    'dietary' => 'none',
                    'accessibility_needs' => false,
                ],
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'password_hash' => Hash::make(Str::random(16)),
            ]);

            AuditLog::create([
                'id' => 'aud-' . (string)Str::uuid(),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'register_guest',
                'resource_type' => 'guest',
                'resource_id' => $guest->id,
                'details' => [
                    'full_name' => $guest->full_name,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'id_type' => $guest->id_type,
                    'vip_status' => $guest->vip_status,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Guest '{$guest->full_name}' successfully registered.",
                'guest' => $guest,
            ], 201);
        });
    }

    /**
     * Show comprehensive individual guest profile, stay dossier, preferences, and booking history.
     */
    public function show(Request $request, $id)
    {
        $guest = User::where('id', $id)->firstOrFail();
        $canViewFullId = $this->hasIdViewPermission($request);

        // Retrieve full reservations history
        $bookings = Booking::with(['room.category', 'bookingRooms.room', 'payments'])
            ->where('guest_id', $guest->id)
            ->orderBy('check_in_date', 'desc')
            ->get();

        $today = now()->toDateString();

        $currentStays = $bookings->where('booking_status', 'checked_in')->values();
        $upcomingStays = $bookings->filter(fn($b) => in_array($b->booking_status, ['confirmed', 'pending_verification']) && $b->check_in_date >= $today)->values();
        $completedStays = $bookings->where('booking_status', 'checked_out')->values();
        $cancelledStays = $bookings->where('booking_status', 'cancelled')->values();
        $noShowStays = $bookings->where('booking_status', 'no_show')->values();

        $totalSpentEtb = $bookings->whereIn('booking_status', ['checked_out', 'checked_in'])->sum('total_price_etb');
        $totalSpentUsd = $bookings->whereIn('booking_status', ['checked_out', 'checked_in'])->sum('total_price_usd');

        $formatBooking = function ($b) {
            return [
                'id' => $b->id,
                'reference_code' => $b->reference_code,
                'primary_room' => $b->room ? [
                    'id' => $b->room->id,
                    'room_number' => $b->room->room_number,
                    'category' => $b->room->category?->name ?: 'Deluxe',
                ] : null,
                'rooms_count' => $b->rooms_count ?: 1,
                'check_in_date' => $b->check_in_date,
                'check_out_date' => $b->check_out_date,
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
                'total_price_etb' => (float)$b->total_price_etb,
                'total_price_usd' => (float)$b->total_price_usd,
                'special_requests' => $b->special_requests,
                'cancellation_reason' => $b->cancellation_reason,
                'is_walk_in' => (bool)$b->is_walk_in,
                'created_at' => $b->created_at->format('Y-m-d H:i'),
            ];
        };

        return response()->json([
            'guest' => [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'gender' => $guest->gender,
                'date_of_birth' => $guest->date_of_birth ? $guest->date_of_birth->format('Y-m-d') : null,
                'nationality' => $guest->nationality,
                'email' => $guest->email,
                'phone' => $guest->phone,
                'address' => $guest->address,
                'city' => $guest->city,
                'country' => $guest->country,
                'id_type' => $guest->id_type,
                'id_number' => $canViewFullId ? $guest->id_number : $guest->masked_id_number,
                'id_issue_country' => $guest->id_issue_country,
                'id_expiry_date' => $guest->id_expiry_date ? $guest->id_expiry_date->format('Y-m-d') : null,
                'vip_status' => $guest->vip_status,
                'preferences' => $guest->preferences ?: [
                    'smoking' => false,
                    'floor_preference' => 'any',
                    'bed_type' => 'king',
                    'dietary' => 'none',
                    'accessibility_needs' => false,
                ],
                'notes' => $guest->notes,
                'is_active' => (bool)$guest->is_active,
                'emergency_contact_name' => $guest->emergency_contact_name,
                'emergency_contact_phone' => $guest->emergency_contact_phone,
                'created_at' => $guest->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $guest->created_at->diffForHumans(),
            ],
            'summary' => [
                'total_reservations' => $bookings->count(),
                'completed_stays' => $completedStays->count(),
                'current_stays' => $currentStays->count(),
                'upcoming_reservations' => $upcomingStays->count(),
                'cancelled_stays' => $cancelledStays->count(),
                'no_show_records' => $noShowStays->count(),
                'is_returning' => $bookings->count() >= 2 || $completedStays->count() >= 1,
                'total_spent_etb' => (float)$totalSpentEtb,
                'total_spent_usd' => (float)$totalSpentUsd,
            ],
            'booking_history' => [
                'all' => $bookings->map($formatBooking)->values(),
                'current' => $currentStays->map($formatBooking)->values(),
                'upcoming' => $upcomingStays->map($formatBooking)->values(),
                'completed' => $completedStays->map($formatBooking)->values(),
                'cancelled' => $cancelledStays->map($formatBooking)->values(),
                'no_show' => $noShowStays->map($formatBooking)->values(),
            ],
        ]);
    }

    /**
     * Update guest information, permanent preferences, notes, and VIP status.
     */
    public function update(Request $request, $id)
    {
        $guest = User::where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:60',
            'last_name' => 'nullable|string|max:60',
            'full_name' => 'nullable|string|max:120',
            'email' => 'required|email|max:160',
            'phone' => 'required|string|max:30',
            'gender' => 'nullable|in:male,female,other,unspecified',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'id_type' => 'nullable|in:national_id,passport,driving_license,kebele_id,resident_id,other',
            'id_number' => 'nullable|string|max:100',
            'id_issue_country' => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
            'vip_status' => 'nullable|in:standard,vip,vvip,blacklist',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $email = strtolower(trim($validated['email']));
        $phone = trim($validated['phone']);
        $idNumber = array_key_exists('id_number', $validated) ? (!empty($validated['id_number']) ? trim($validated['id_number']) : null) : $guest->id_number;

        // Duplicate checks against other guests
        $dupEmail = User::where('email', $email)->where('id', '!=', $id)->first();
        if ($dupEmail) {
            return response()->json(['error' => "Email '{$email}' is already used by another guest ({$dupEmail->full_name})."], 409);
        }

        $dupPhone = User::where('phone', $phone)->where('id', '!=', $id)->first();
        if ($dupPhone) {
            return response()->json(['error' => "Phone number '{$phone}' is already used by another guest ({$dupPhone->full_name})."], 409);
        }

        if ($idNumber) {
            $dupId = User::where('id_number', $idNumber)->where('id', '!=', $id)->first();
            if ($dupId) {
                return response()->json(['error' => "ID number '{$idNumber}' is already registered to another guest ({$dupId->full_name})."], 409);
            }
        }

        // Format names
        $firstName = trim($validated['first_name'] ?? ($guest->first_name ?: ''));
        $lastName = trim($validated['last_name'] ?? ($guest->last_name ?: ''));
        $fullName = trim($validated['full_name'] ?? '');

        if (empty($fullName)) {
            $fullName = trim("{$firstName} {$lastName}");
        }
        if (empty($fullName)) {
            $fullName = $guest->full_name;
        }

        return DB::transaction(function () use ($guest, $validated, $email, $phone, $idNumber, $fullName, $firstName, $lastName, $request) {
            $guest->update([
                'full_name' => $fullName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $validated['gender'] ?? $guest->gender,
                'date_of_birth' => array_key_exists('date_of_birth', $validated) ? $validated['date_of_birth'] : $guest->date_of_birth,
                'nationality' => $validated['nationality'] ?? $guest->nationality,
                'email' => $email,
                'phone' => $phone,
                'address' => array_key_exists('address', $validated) ? $validated['address'] : $guest->address,
                'city' => array_key_exists('city', $validated) ? $validated['city'] : $guest->city,
                'country' => array_key_exists('country', $validated) ? $validated['country'] : $guest->country,
                'id_type' => array_key_exists('id_type', $validated) ? $validated['id_type'] : $guest->id_type,
                'id_number' => $idNumber,
                'id_issue_country' => array_key_exists('id_issue_country', $validated) ? $validated['id_issue_country'] : $guest->id_issue_country,
                'id_expiry_date' => array_key_exists('id_expiry_date', $validated) ? $validated['id_expiry_date'] : $guest->id_expiry_date,
                'vip_status' => $validated['vip_status'] ?? $guest->vip_status,
                'preferences' => $validated['preferences'] ?? $guest->preferences,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $guest->notes,
                'is_active' => array_key_exists('is_active', $validated) ? (bool)$validated['is_active'] : $guest->is_active,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? $guest->emergency_contact_name,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? $guest->emergency_contact_phone,
            ]);

            AuditLog::create([
                'id' => 'aud-' . (string)Str::uuid(),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'update_guest',
                'resource_type' => 'guest',
                'resource_id' => $guest->id,
                'details' => [
                    'full_name' => $guest->full_name,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'vip_status' => $guest->vip_status,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Guest profile for '{$guest->full_name}' updated successfully.",
                'guest' => $guest->fresh(),
            ]);
        });
    }

    /**
     * Delete or Soft-Deactivate a Guest Profile.
     * Prevents permanent deletion of guests with historical bookings/financial records.
     */
    public function destroy(Request $request, $id)
    {
        $guest = User::where('id', $id)->firstOrFail();
        $hasBookings = Booking::where('guest_id', $guest->id)->exists();

        if ($hasBookings) {
            // Soft-deactivate to preserve historical ledger & stay records
            $guest->update(['is_active' => false]);

            AuditLog::create([
                'id' => 'aud-' . (string)Str::uuid(),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'deactivate_guest',
                'resource_type' => 'guest',
                'resource_id' => $guest->id,
                'details' => ['reason' => 'Guest has booking history; deactivated to maintain historical records.'],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'action' => 'deactivated',
                'message' => "Guest '{$guest->full_name}' has associated reservation history and cannot be deleted permanently. The profile has been safely deactivated instead.",
                'guest' => $guest->fresh(),
            ]);
        }

        // Hard-delete only allowed if guest has zero booking history
        return DB::transaction(function () use ($guest, $request) {
            $name = $guest->full_name;
            $gId = $guest->id;
            $guest->delete();

            AuditLog::create([
                'id' => 'aud-' . (string)Str::uuid(),
                'user_id' => $request->user()?->id ?: 'usr-staff-1',
                'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
                'user_role' => $request->user()?->role ?: 'receptionist',
                'action' => 'delete_guest',
                'resource_type' => 'guest',
                'resource_id' => $gId,
                'details' => ['name' => $name, 'reason' => 'Guest had no booking history; permanently deleted.'],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'action' => 'deleted',
                'message' => "Guest '{$name}' had no associated bookings and was permanently deleted.",
            ]);
        });
    }

    /**
     * Toggle Guest Active / Inactive Status.
     */
    public function toggleActive(Request $request, $id)
    {
        $guest = User::where('id', $id)->firstOrFail();
        $guest->is_active = !$guest->is_active;
        $guest->save();

        AuditLog::create([
            'id' => 'aud-' . (string)Str::uuid(),
            'user_id' => $request->user()?->id ?: 'usr-staff-1',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'receptionist',
            'action' => $guest->is_active ? 'activate_guest' : 'deactivate_guest',
            'resource_type' => 'guest',
            'resource_id' => $guest->id,
            'details' => ['new_status' => $guest->is_active ? 'active' : 'inactive'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Guest '{$guest->full_name}' is now " . ($guest->is_active ? 'active' : 'inactive') . ".",
            'guest' => $guest,
        ]);
    }

    /**
     * Check if request has authorized permissions to view unmasked sensitive identification data.
     */
    protected function hasIdViewPermission(Request $request): bool
    {
        $role = $request->user()?->role ?? $request->header('X-User-Role') ?? 'receptionist';
        // Authorized roles: super_admin, admin, receptionist, manager
        return in_array($role, ['super_admin', 'admin', 'receptionist', 'manager']);
    }
}
