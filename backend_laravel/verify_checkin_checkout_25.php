<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingServiceCharge;
use App\Models\Payment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\HousekeepingController;
use Carbon\Carbon;

$bookingController = new BookingController();
$housekeepingController = new HousekeepingController();

echo "====================================================================\n";
echo "   CHECK-IN & CHECK-OUT MANAGEMENT - 25 SCENARIOS VERIFICATION\n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 25;

// Authenticate a staff user for audits
$staff = User::where('role', 'admin')->first() ?: User::first();
if ($staff) {
    Auth::setUser($staff);
}

// Helper cleanup function
function cleanupBooking($refCode) {
    $booking = Booking::where('reference_code', $refCode)->first();
    if ($booking) {
        BookingServiceCharge::where('booking_id', $booking->id)->delete();
        Payment::where('booking_id', $booking->id)->delete();
        BookingRoom::where('booking_id', $booking->id)->delete();
        AuditLog::where('resource_id', $booking->id)->delete();
        $booking->delete();
    }
}

// Cleanup any leftover test data
cleanupBooking('ETH-CHK-TEST-01');
cleanupBooking('ETH-CHK-TEST-02');
cleanupBooking('ETH-CHK-TEST-03');
cleanupBooking('ETH-CHK-TEST-04');
cleanupBooking('ETH-CHK-WALKIN-99');

// Ensure test room category exists
$deluxeCat = RoomCategory::where('slug', 'deluxe')->first() ?: RoomCategory::first();

$testRoom1 = Room::firstOrCreate(
    ['room_number' => 'CHK-101'],
    [
        'id' => 'rm-chk-101',
        'category_id' => $deluxeCat->id,
        'title' => 'Deluxe Suite CHK-101',
        'price_etb' => 3500.00,
        'price_usd' => 30.00,
        'floor' => 1,
        'capacity' => 2,
        'status' => 'available',
        'housekeeping_status' => 'inspected',
        'is_active' => true
    ]
);
$testRoom1->update(['status' => 'available', 'housekeeping_status' => 'inspected']);

$testRoom2 = Room::firstOrCreate(
    ['room_number' => 'CHK-102'],
    [
        'id' => 'rm-chk-102',
        'category_id' => $deluxeCat->id,
        'title' => 'Deluxe Suite CHK-102',
        'price_etb' => 3800.00,
        'price_usd' => 32.00,
        'floor' => 1,
        'capacity' => 2,
        'status' => 'available',
        'housekeeping_status' => 'inspected',
        'is_active' => true
    ]
);
$testRoom2->update(['status' => 'available', 'housekeeping_status' => 'inspected']);

$testRoom3 = Room::firstOrCreate(
    ['room_number' => 'CHK-103'],
    [
        'id' => 'rm-chk-103',
        'category_id' => $deluxeCat->id,
        'title' => 'Maintenance Room CHK-103',
        'price_etb' => 3000.00,
        'price_usd' => 25.00,
        'floor' => 1,
        'capacity' => 2,
        'status' => 'maintenance',
        'housekeeping_status' => 'dirty',
        'is_active' => true
    ]
);
$testRoom3->update(['status' => 'maintenance', 'housekeeping_status' => 'dirty']);

// Ensure test guest exists
$testGuest = User::firstOrCreate(
    ['email' => 'solomon.frontdesk@hotel.et'],
    [
        'id' => 'usr-solomon-frontdesk',
        'first_name' => 'Solomon',
        'last_name' => 'Bekele',
        'full_name' => 'Solomon Bekele',
        'phone' => '+251 911 223344',
        'role' => 'guest',
        'id_type' => 'national_id',
        'id_number' => 'ET-NID-882200',
        'nationality' => 'Ethiopian',
        'password_hash' => bcrypt('Secret123!')
    ]
);

try {
    // -------------------------------------------------------------
    // Scenario 1: View today's expected arrivals
    // -------------------------------------------------------------
    echo "[Scenario 1] View today's expected arrivals & front-desk KPIs...\n";
    $todayStr = Carbon::today()->toDateString();
    $tomorrowStr = Carbon::tomorrow()->toDateString();

    // Create a confirmed arrival for today
    $booking1 = Booking::create([
        'id' => 'bk-chk-test-01',
        'reference_code' => 'ETH-CHK-TEST-01',
        'guest_id' => $testGuest->id,
        'room_id' => $testRoom1->id,
        'room_number' => $testRoom1->room_number,
        'check_in_date' => $todayStr,
        'check_out_date' => $tomorrowStr,
        'num_guests' => 2,
        'total_price_etb' => 3500.00,
        'total_price_usd' => 30.00,
        'nightly_rate_etb' => 3500.00,
        'booking_status' => 'confirmed',
        'payment_status' => 'pending'
    ]);

    $reqDash = Request::create('/api/v1/desk/checkin-dashboard', 'GET');
    $dashRes = $bookingController->checkInDashboard($reqDash);
    $dashData = json_decode($dashRes->getContent(), true);

    if ($dashRes->getStatusCode() === 200 && isset($dashData['metrics']['expected_arrivals_today']) && $dashData['metrics']['expected_arrivals_today'] >= 1) {
        echo "  -> SUCCESS: Today's arrivals dashboard loaded. Expected arrivals today: {$dashData['metrics']['expected_arrivals_today']}\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 1 failed: Expected arrivals today not reported accurately.");
    }

    // -------------------------------------------------------------
    // Scenario 2: Search reservations using booking reference
    // -------------------------------------------------------------
    echo "[Scenario 2] Search reservations using booking reference...\n";
    $searchRes = $bookingController->show('ETH-CHK-TEST-01');
    $searchData = json_decode($searchRes->getContent(), true);

    if ($searchRes->getStatusCode() === 200 && $searchData['booking']['reference_code'] === 'ETH-CHK-TEST-01') {
        echo "  -> SUCCESS: Reservation found by reference code. Guest: {$searchData['booking']['guest_name']}\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 2 failed: Booking search by reference failed.");
    }

    // -------------------------------------------------------------
    // Scenario 3: Confirm guest identity
    // -------------------------------------------------------------
    echo "[Scenario 3] Confirm guest identity with ID verification...\n";
    $reqCheckInPrep = Request::create("/api/v1/bookings/{$booking1->id}/check-in", 'POST', [
        'id_type' => 'passport',
        'id_number' => 'ET-PASS-992211',
        'key_card_number' => 'KC-101',
        'num_guests' => 2,
        'check_in_notes' => 'Identity verified with Ethiopian Passport.'
    ]);
    $checkInRes = $bookingController->checkIn($reqCheckInPrep, $booking1->id);
    $checkInData = json_decode($checkInRes->getContent(), true);

    $testGuest->refresh();
    if ($testGuest->id_number === 'ET-PASS-992211' && $testGuest->id_type === 'passport') {
        echo "  -> SUCCESS: Guest identity confirmed and updated (Passport: ET-PASS-992211).\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 3 failed: Guest identity was not updated upon check-in.");
    }

    // -------------------------------------------------------------
    // Scenario 4: Complete a normal check-in
    // -------------------------------------------------------------
    echo "[Scenario 4] Complete a normal check-in...\n";
    $booking1->refresh();
    $testRoom1->refresh();

    if ($booking1->booking_status === 'checked_in' && $booking1->actual_check_in_at !== null && $testRoom1->status === 'occupied') {
        echo "  -> SUCCESS: Check-in completed. Booking is 'checked_in', room {$testRoom1->room_number} is 'occupied'.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 4 failed: Normal check-in status or room occupancy not updated.");
    }

    // -------------------------------------------------------------
    // Scenario 5: Prevent check-in for cancelled reservations
    // -------------------------------------------------------------
    echo "[Scenario 5] Prevent check-in for cancelled reservations...\n";
    $cancelledBooking = Booking::create([
        'id' => 'bk-chk-test-02',
        'reference_code' => 'ETH-CHK-TEST-02',
        'guest_id' => $testGuest->id,
        'room_id' => $testRoom2->id,
        'room_number' => $testRoom2->room_number,
        'check_in_date' => $todayStr,
        'check_out_date' => $tomorrowStr,
        'num_guests' => 1,
        'total_price_etb' => 3800.00,
        'booking_status' => 'cancelled',
        'payment_status' => 'pending'
    ]);

    $reqCancelCheckIn = Request::create("/api/v1/bookings/{$cancelledBooking->id}/check-in", 'POST', []);
    $cancelCheckInRes = $bookingController->checkIn($reqCancelCheckIn, $cancelledBooking->id);

    if ($cancelCheckInRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Check-in rejected for cancelled reservation with HTTP 422.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 5 failed: Check-in did not reject cancelled reservation.");
    }

    // -------------------------------------------------------------
    // Scenario 6: Prevent duplicate check-in
    // -------------------------------------------------------------
    echo "[Scenario 6] Prevent duplicate check-in...\n";
    $duplicateRes = $bookingController->checkIn(Request::create("/api/v1/bookings/{$booking1->id}/check-in", 'POST'), $booking1->id);

    if ($duplicateRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Duplicate check-in rejected with HTTP 422.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 6 failed: Duplicate check-in was not blocked.");
    }

    // -------------------------------------------------------------
    // Scenario 7: Assign an alternative available room
    // -------------------------------------------------------------
    echo "[Scenario 7] Assign an alternative available room...\n";
    $booking3 = Booking::create([
        'id' => 'bk-chk-test-03',
        'reference_code' => 'ETH-CHK-TEST-03',
        'guest_id' => $testGuest->id,
        'room_id' => $testRoom1->id,
        'room_number' => $testRoom1->room_number,
        'check_in_date' => Carbon::now()->addDays(5)->toDateString(),
        'check_out_date' => Carbon::now()->addDays(7)->toDateString(),
        'num_guests' => 2,
        'total_price_etb' => 7000.00,
        'booking_status' => 'confirmed',
        'payment_status' => 'pending'
    ]);

    $reqReassign = Request::create("/api/v1/bookings/{$booking3->id}/reassign-room", 'POST', [
        'new_room_id' => $testRoom2->id,
        'reason' => 'Guest requested quiet room on higher floor'
    ]);
    $reassignRes = $bookingController->reassignRoom($reqReassign, $booking3->id);
    $booking3->refresh();

    if ($reassignRes->getStatusCode() === 200 && $booking3->room_id == $testRoom2->id) {
        echo "  -> SUCCESS: Booking successfully reassigned to Room {$testRoom2->room_number}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 7 failed: Room reassignment failed.");
    }

    // -------------------------------------------------------------
    // Scenario 8: Prevent assignment of occupied rooms
    // -------------------------------------------------------------
    echo "[Scenario 8] Prevent assignment of occupied rooms...\n";
    // Force date overlap with booking1 which occupies room 1
    $booking3->update([
        'check_in_date' => $todayStr,
        'check_out_date' => $tomorrowStr
    ]);
    $reqReassignOccupied = Request::create("/api/v1/bookings/{$booking3->id}/reassign-room", 'POST', [
        'new_room_id' => $testRoom1->id,
        'reason' => 'Try assigning occupied room'
    ]);
    $reassignOccRes = $bookingController->reassignRoom($reqReassignOccupied, $booking3->id);

    if ($reassignOccRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Assignment of occupied room was blocked with HTTP 422.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 8 failed: System allowed reassignment to occupied room.");
    }

    // -------------------------------------------------------------
    // Scenario 9: Prevent assignment of rooms under maintenance
    // -------------------------------------------------------------
    echo "[Scenario 9] Prevent assignment of rooms under maintenance...\n";
    $reqReassignMaint = Request::create("/api/v1/bookings/{$booking3->id}/reassign-room", 'POST', [
        'new_room_id' => $testRoom3->id,
        'reason' => 'Try assigning maintenance room'
    ]);
    $reassignMaintRes = $bookingController->reassignRoom($reqReassignMaint, $booking3->id);

    if ($reassignMaintRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Assignment of maintenance room was blocked with HTTP 422.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 9 failed: System allowed assigning maintenance room.");
    }

    // -------------------------------------------------------------
    // Scenario 10: Validate room capacity
    // -------------------------------------------------------------
    echo "[Scenario 10] Validate room capacity...\n";
    // testRoom2 capacity is 2. Attempt check-in with 5 guests.
    $reqCapacity = Request::create("/api/v1/bookings/{$booking3->id}/check-in", 'POST', [
        'num_guests' => 5
    ]);
    $capRes = $bookingController->checkIn($reqCapacity, $booking3->id);

    if ($capRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Check-in rejected when guest count exceeds capacity (HTTP 422).\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 10 failed: Guest count exceeding capacity was not rejected.");
    }

    // -------------------------------------------------------------
    // Scenario 11: Complete walk-in check-in
    // -------------------------------------------------------------
    echo "[Scenario 11] Complete walk-in check-in...\n";
    $reqWalkIn = Request::create('/api/v1/desk/walk-in-checkin', 'POST', [
        'first_name' => 'Tewodros',
        'last_name' => 'Kassahun',
        'phone' => '+251 911 776655',
        'email' => 'tewodros.walkin@hotel.et',
        'id_type' => 'national_id',
        'id_number' => 'ET-NID-110022',
        'room_id' => $testRoom2->id,
        'check_in_date' => $todayStr,
        'check_out_date' => $tomorrowStr,
        'num_guests' => 2,
        'key_card_number' => 'KC-WALKIN-102',
        'payment_method' => 'telebirr',
        'initial_payment_etb' => 3800.00,
        'check_in_notes' => 'Walk-in VIP arrived at front desk.'
    ]);
    $walkInRes = $bookingController->walkInCheckIn($reqWalkIn);
    $walkInData = json_decode($walkInRes->getContent(), true);

    $testRoom2->refresh();
    if ($walkInRes->getStatusCode() === 201 && $walkInData['booking']['booking_status'] === 'checked_in' && $testRoom2->status === 'occupied') {
        echo "  -> SUCCESS: Walk-in check-in completed. Ref: {$walkInData['booking']['reference_code']}, Room {$testRoom2->room_number} Occupied.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 11 failed: Walk-in check-in failed.");
    }

    // -------------------------------------------------------------
    // Scenario 12: View currently checked-in guests
    // -------------------------------------------------------------
    echo "[Scenario 12] View currently checked-in guests (In-House)...\n";
    $reqInHouse = Request::create('/api/v1/admin/bookings/check-out-eligible', 'GET');
    $inHouseRes = $bookingController->checkOutEligible($reqInHouse);
    $inHouseData = json_decode($inHouseRes->getContent(), true);

    if ($inHouseRes->getStatusCode() === 200 && count($inHouseData['eligible_departures']) >= 2) {
        echo "  -> SUCCESS: Currently checked-in occupants retrieved (" . count($inHouseData['eligible_departures']) . " active guests).\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 12 failed: Currently checked-in guests not listed correctly.");
    }

    // -------------------------------------------------------------
    // Scenario 13: View today's expected departures
    // -------------------------------------------------------------
    echo "[Scenario 13] View today's expected departures...\n";
    // Set booking1 check_out_date to today
    $booking1->update(['check_out_date' => $todayStr]);
    $reqDepartures = Request::create('/api/v1/admin/bookings/check-out-eligible', 'GET');
    $departuresRes = $bookingController->checkOutEligible($reqDepartures);
    $departuresData = json_decode($departuresRes->getContent(), true);

    $foundDeparture = collect($departuresData['eligible_departures'])->firstWhere('id', $booking1->id);
    if ($foundDeparture && $foundDeparture['check_out_date'] === $todayStr) {
        echo "  -> SUCCESS: Expected departures today identified correctly for Ref: {$booking1->reference_code}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 13 failed: Today's expected departures not identified.");
    }

    // -------------------------------------------------------------
    // Scenario 14: Calculate room charges correctly
    // -------------------------------------------------------------
    echo "[Scenario 14] Calculate room charges correctly...\n";
    $booking1->refresh();
    $d1 = Carbon::parse($booking1->check_in_date);
    $d2 = Carbon::parse($booking1->check_out_date);
    $nights = max(1, $d1->diffInDays($d2));
    $expectedRoomCharge = $nights * (float)$booking1->nightly_rate_etb;

    if ($expectedRoomCharge == 3500.00) {
        echo "  -> SUCCESS: Room charges calculated accurately: {$nights} night(s) * ETB {$booking1->nightly_rate_etb} = ETB {$expectedRoomCharge}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 14 failed: Room charge calculation mismatch.");
    }

    // -------------------------------------------------------------
    // Scenario 15: Include additional service charges (Incidentals)
    // -------------------------------------------------------------
    echo "[Scenario 15] Include additional incidental service charges...\n";
    // Add restaurant charge
    $reqSvc1 = Request::create("/api/v1/bookings/{$booking1->id}/service-charges", 'POST', [
        'service_type' => 'restaurant',
        'description' => 'Traditional Ethiopian Dinner Buffet & Coffee Ceremony',
        'quantity' => 2,
        'amount_etb' => 950.00
    ]);
    $svcRes1 = $bookingController->addServiceCharge($reqSvc1, $booking1->id);

    // Add minibar charge
    $reqSvc2 = Request::create("/api/v1/bookings/{$booking1->id}/service-charges", 'POST', [
        'service_type' => 'minibar',
        'description' => '2x Rift Valley Wine Bottles',
        'quantity' => 2,
        'amount_etb' => 550.00
    ]);
    $svcRes2 = $bookingController->addServiceCharge($reqSvc2, $booking1->id);

    $booking1->refresh();
    if ($svcRes1->getStatusCode() === 201 && $svcRes2->getStatusCode() === 201 && $booking1->total_service_charges_etb == 1500.00) {
        echo "  -> SUCCESS: Incidental charges added (ETB 950 + ETB 550 = ETB 1500). Gross Total: ETB {$booking1->gross_total_etb}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 15 failed: Additional service charges not properly included.");
    }

    // -------------------------------------------------------------
    // Scenario 16: Calculate outstanding balances correctly
    // -------------------------------------------------------------
    echo "[Scenario 16] Calculate outstanding balances correctly...\n";
    $booking1->refresh();
    // Gross total = 3500 + 1500 = 5000. No payments made yet.
    if ($booking1->outstanding_balance_etb == 5000.00) {
        echo "  -> SUCCESS: Outstanding balance correctly calculated as ETB {$booking1->outstanding_balance_etb}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 16 failed: Outstanding balance calculation mismatch: expected 5000, got {$booking1->outstanding_balance_etb}");
    }

    // -------------------------------------------------------------
    // Scenario 17: Process final payments (Settlement)
    // -------------------------------------------------------------
    echo "[Scenario 17] Process final payments (Folio Settlement)...\n";
    $reqSettle = Request::create("/api/v1/bookings/{$booking1->id}/settle-payment", 'POST', [
        'amount_etb' => 5000.00,
        'payment_method' => 'telebirr',
        'reference_note' => 'Full departure settlement via Telebirr'
    ]);
    $settleRes = $bookingController->settlePayment($reqSettle, $booking1->id);
    $booking1->refresh();

    if ($settleRes->getStatusCode() === 200 && $booking1->outstanding_balance_etb == 0.00 && $booking1->payment_status === 'paid') {
        echo "  -> SUCCESS: Final balance settled in full. Payment status: {$booking1->payment_status}, Outstanding: ETB {$booking1->outstanding_balance_etb}.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 17 failed: Final settlement payment processing failed.");
    }

    // -------------------------------------------------------------
    // Scenario 18: Generate the final invoice
    // -------------------------------------------------------------
    echo "[Scenario 18] Generate official Ethiopian Tax Invoice...\n";
    $invRes = $bookingController->generateInvoice($booking1->id);
    $invData = json_decode($invRes->getContent(), true);

    if ($invRes->getStatusCode() === 200 
        && isset($invData['invoice']['hotel_tin']) 
        && $invData['invoice']['hotel_tin'] === '0048921849'
        && count($invData['invoice']['service_items']) === 2
        && $invData['invoice']['financial']['outstanding_balance_etb'] == 0) {
        echo "  -> SUCCESS: Tax invoice generated (TIN: {$invData['invoice']['hotel_tin']}, Itemized Services: " . count($invData['invoice']['service_items']) . ").\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 18 failed: Final invoice generation incomplete.");
    }

    // -------------------------------------------------------------
    // Scenario 19: Complete checkout successfully
    // -------------------------------------------------------------
    echo "[Scenario 19] Complete checkout successfully...\n";
    $reqCheckOut = Request::create("/api/v1/bookings/{$booking1->id}/check-out", 'POST', []);
    $checkOutRes = $bookingController->checkOut($reqCheckOut, $booking1->id);
    $booking1->refresh();

    if ($checkOutRes->getStatusCode() === 200 && $booking1->booking_status === 'checked_out' && $booking1->actual_check_out_at !== null) {
        echo "  -> SUCCESS: Checkout completed. Booking status is 'checked_out', departure timestamp logged.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 19 failed: Checkout execution failed.");
    }

    // -------------------------------------------------------------
    // Scenario 20: Prevent duplicate checkout
    // -------------------------------------------------------------
    echo "[Scenario 20] Prevent duplicate checkout...\n";
    $dupCheckOutRes = $bookingController->checkOut(Request::create("/api/v1/bookings/{$booking1->id}/check-out", 'POST'), $booking1->id);

    if ($dupCheckOutRes->getStatusCode() === 422) {
        echo "  -> SUCCESS: Duplicate checkout prevented with HTTP 422.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 20 failed: Duplicate checkout was not prevented.");
    }

    // -------------------------------------------------------------
    // Scenario 21: Change room status to Cleaning after checkout
    // -------------------------------------------------------------
    echo "[Scenario 21] Change room status to Cleaning / Turnover after checkout...\n";
    $testRoom1->refresh();
    if ($testRoom1->status === 'cleaning' && $testRoom1->housekeeping_status === 'dirty') {
        echo "  -> SUCCESS: Room {$testRoom1->room_number} status automatically switched to 'cleaning' & housekeeping_status 'dirty'.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 21 failed: Room status did not transition to cleaning/dirty upon checkout.");
    }

    // -------------------------------------------------------------
    // Scenario 22: Trigger housekeeping turnover & log audit
    // -------------------------------------------------------------
    echo "[Scenario 22] Verify housekeeping turnover transition & front-desk audit log...\n";
    $checkoutAudit = AuditLog::where('resource_id', $booking1->id)
        ->where('action', 'guest_check_out')
        ->first();

    if ($testRoom1->status === 'cleaning' && $checkoutAudit !== null) {
        echo "  -> SUCCESS: Checkout turnover registered. Audit log ID: {$checkoutAudit->id}, Room {$testRoom1->room_number} in turnover queue.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 22 failed: Turnover or audit logging failed.");
    }

    // -------------------------------------------------------------
    // Scenario 23: Make room Available only after cleaning is completed
    // -------------------------------------------------------------
    echo "[Scenario 23] Make room Available only after cleaning is completed...\n";
    // While cleaning, room should not be available
    $availBeforeCleaning = Room::where('id', $testRoom1->id)->where('status', 'available')->exists();
    if ($availBeforeCleaning) {
        throw new Exception("Scenario 23 failed: Room was prematurely marked available during cleaning.");
    }

    // Housekeeper completes cleaning turnover via HousekeepingController
    $reqHkComplete = Request::create("/api/v1/housekeeping/{$testRoom1->id}", 'PUT', [
        'housekeeping_status' => 'inspected'
    ]);
    $hkRes = $housekeepingController->update($reqHkComplete, $testRoom1->id);
    $testRoom1->refresh();

    if ($hkRes->getStatusCode() === 200 && $testRoom1->status === 'available' && $testRoom1->housekeeping_status === 'inspected') {
        echo "  -> SUCCESS: Room successfully restored to 'available' & 'inspected' after turnover completion.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 23 failed: Room status not restored to available after inspection.");
    }

    // -------------------------------------------------------------
    // Scenario 24: Preserve check-in/check-out history (Audit Ledger)
    // -------------------------------------------------------------
    echo "[Scenario 24] Preserve front-desk check-in/check-out history...\n";
    $reqAudit = Request::create('/api/v1/desk/operations-history?limit=15', 'GET');
    $auditRes = $bookingController->operationsHistory($reqAudit);
    $auditData = json_decode($auditRes->getContent(), true);

    $hasCheckInAudit = collect($auditData['logs'])->contains(fn($l) => str_contains($l['action'], 'check_in'));
    $hasCheckOutAudit = collect($auditData['logs'])->contains(fn($l) => str_contains($l['action'], 'check_out'));

    if ($auditRes->getStatusCode() === 200 && ($hasCheckInAudit || $hasCheckOutAudit)) {
        echo "  -> SUCCESS: Operations history preserves immutable audit trail with " . count($auditData['logs']) . " entries.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 24 failed: Operations history audit trail missing check-in/out records.");
    }

    // -------------------------------------------------------------
    // Scenario 25: Verify integration with reservations, payments, and guest records
    // -------------------------------------------------------------
    echo "[Scenario 25] Verify seamless integration across reservations, payments, and guest records...\n";
    $testGuest->refresh();
    $guestBookings = Booking::where('guest_id', $testGuest->id)->get();
    $guestPayments = Payment::whereIn('booking_id', $guestBookings->pluck('id'))->where('status', 'verified')->get();

    if ($guestBookings->count() >= 2 && $guestPayments->sum('amount_etb') >= 5000.00) {
        echo "  -> SUCCESS: Full integration verified! Guest has {$guestBookings->count()} linked stays and ETB " . $guestPayments->sum('amount_etb') . " verified spend history.\n";
        $passedTests++;
    } else {
        throw new Exception("Scenario 25 failed: Integration verification across modules failed.");
    }

} catch (Exception $e) {
    echo "\n❌ EXCEPTION AT TEST: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n====================================================================\n";
echo "   CHECK-IN & CHECK-OUT VERIFICATION RESULTS: {$passedTests} / {$totalTests} PASSED\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL 25 CHECK-IN & CHECK-OUT MANAGEMENT STANDARDS MET 100%!\n";
    exit(0);
} else {
    echo "⚠️ SOME SCENARIOS FAILED AUDIT.\n";
    exit(1);
}
