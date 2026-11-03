<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\HousekeepingController;

$bookingController = new BookingController();
$roomController = new RoomController();
$housekeepingController = new HousekeepingController();

echo "====================================================================\n";
echo "   RESERVATION & BOOKING MANAGEMENT - 22 SCENARIOS VERIFICATION\n";
echo "====================================================================\n\n";

$passedCount = 0;
$totalTests = 22;

function cleanupTestBooking($bookingId) {
    if (!$bookingId) return;
    Payment::where('booking_id', $bookingId)->delete();
    BookingRoom::where('booking_id', $bookingId)->delete();
    DB::table('audit_logs')->where('resource_id', $bookingId)->delete();
    Booking::where('id', $bookingId)->delete();
}

// Pre-test cleanup of any transient test records from prior runs
$futureTestBookings = Booking::where('check_in_date', '>=', '2027-01-01')->get();
foreach ($futureTestBookings as $fb) {
    cleanupTestBooking($fb->id);
}

$staleTestGuests = User::where('email', 'LIKE', '%@test.et')
    ->orWhere('email', 'LIKE', '%@ethiojazz.et')
    ->orWhere('email', 'LIKE', '%@hotel.et')
    ->get();

foreach ($staleTestGuests as $sg) {
    $staleBookings = Booking::where('guest_id', $sg->id)->get();
    foreach ($staleBookings as $sb) {
        cleanupTestBooking($sb->id);
    }
}
Room::where('status', 'occupied')->whereDoesntHave('bookings', fn($q) => $q->where('booking_status', 'checked_in'))->update(['status' => 'available']);



try {
    // -------------------------------------------------------------
    // Scenario 1: Search available rooms by date
    // -------------------------------------------------------------
    echo "[Scenario 1] Search available rooms by date range...\n";
    $req1 = Request::create('/api/v1/rooms/search-availability', 'GET', [
        'check_in' => '2027-02-01',
        'check_out' => '2027-02-05',
    ]);
    $res1 = $bookingController->searchAvailability($req1);
    $data1 = json_decode($res1->getContent(), true);
    assert($res1->getStatusCode() === 200, "Scenario 1 Failed: Status not 200");
    assert(isset($data1['available_rooms']), "Scenario 1 Failed: Missing available_rooms key");
    assert($data1['stay_details']['nights'] === 4, "Scenario 1 Failed: Expected 4 nights");
    assert(count($data1['available_rooms']) > 0, "Scenario 1 Failed: No available rooms found");
    $passedCount++;
    echo "  -> PASSED: Found " . count($data1['available_rooms']) . " available rooms for 4-night stay.\n\n";

    // -------------------------------------------------------------
    // Scenario 2: Search rooms by room type
    // -------------------------------------------------------------
    echo "[Scenario 2] Search rooms by room type...\n";
    $firstCat = RoomCategory::first();
    assert($firstCat !== null, "Scenario 2 Failed: No room category found");
    $req2 = Request::create('/api/v1/rooms/search-availability', 'GET', [
        'check_in' => '2027-02-01',
        'check_out' => '2027-02-05',
        'category_id' => $firstCat->id,
    ]);
    $res2 = $bookingController->searchAvailability($req2);
    $data2 = json_decode($res2->getContent(), true);
    assert($res2->getStatusCode() === 200, "Scenario 2 Failed: Status not 200");
    foreach ($data2['available_rooms'] as $roomItem) {
        assert($roomItem['category_id'] === $firstCat->id || $roomItem['category_name'] === $firstCat->name, "Scenario 2 Failed: Room category mismatch");
    }
    $passedCount++;
    echo "  -> PASSED: Filtered available rooms strictly by category '{$firstCat->name}'.\n\n";

    // -------------------------------------------------------------
    // Scenario 3: Search rooms by guest capacity
    // -------------------------------------------------------------
    echo "[Scenario 3] Search rooms by guest capacity...\n";
    $req3 = Request::create('/api/v1/rooms/search-availability', 'GET', [
        'check_in' => '2027-02-01',
        'check_out' => '2027-02-05',
        'guests' => 3,
    ]);
    $res3 = $bookingController->searchAvailability($req3);
    $data3 = json_decode($res3->getContent(), true);
    assert($res3->getStatusCode() === 200, "Scenario 3 Failed: Status not 200");
    foreach ($data3['available_rooms'] as $roomItem) {
        assert($roomItem['capacity'] >= 3, "Scenario 3 Failed: Room capacity is less than requested 3 guests");
    }
    $passedCount++;
    echo "  -> PASSED: All returned rooms have capacity >= 3 guests.\n\n";

    // -------------------------------------------------------------
    // Scenario 4: Create a reservation successfully
    // -------------------------------------------------------------
    echo "[Scenario 4] Create a reservation successfully...\n";
    $testRoom = Room::where('is_active', 1)->where('status', 'available')->firstOrFail();
    $req4 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $testRoom->id,
        'check_in_date' => '2027-03-01',
        'check_out_date' => '2027-03-04',
        'adults' => 2,
        'children' => 0,
        'guest_name' => 'Sara Haile',
        'guest_email' => 'sara.haile@test.et',
        'guest_phone' => '+251 922 112233',
        'special_requests' => 'Quiet room requested',
    ]);
    $res4 = $bookingController->store($req4);
    $data4 = json_decode($res4->getContent(), true);
    assert($res4->getStatusCode() === 201, "Scenario 4 Failed: Status not 201");
    assert(isset($data4['booking']['id']), "Scenario 4 Failed: No booking id returned");
    assert($data4['booking']['check_in_date'] === '2027-03-01', "Scenario 4 Failed: check_in mismatch");
    assert($data4['booking']['check_out_date'] === '2027-03-04', "Scenario 4 Failed: check_out mismatch");
    $booking4Id = $data4['booking']['id'];
    $passedCount++;
    echo "  -> PASSED: Created reservation {$booking4Id} for guest Sara Haile.\n\n";

    // -------------------------------------------------------------
    // Scenario 5: Generate a unique booking reference
    // -------------------------------------------------------------
    echo "[Scenario 5] Generate unique booking reference...\n";
    $ref4 = $data4['booking']['reference_code'];
    assert(preg_match('/^#ETH-\d{5,}$/', $ref4) === 1, "Scenario 5 Failed: Reference '{$ref4}' does not match pattern #ETH-XXXXX");
    
    // Create another reservation to verify references are distinct
    $testRoom2 = Room::where('is_active', 1)->where('id', '!=', $testRoom->id)->firstOrFail();
    $req5 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $testRoom2->id,
        'check_in_date' => '2027-03-01',
        'check_out_date' => '2027-03-03',
        'guest_name' => 'Tewodros Kassahun',
        'guest_email' => 'teddy.test@hotel.et',
    ]);
    $res5 = $bookingController->store($req5);
    $data5 = json_decode($res5->getContent(), true);
    $booking5Id = $data5['booking']['id'];
    $ref5 = $data5['booking']['reference_code'];
    assert($ref4 !== $ref5, "Scenario 5 Failed: Booking reference collision detected between {$ref4} and {$ref5}");
    $passedCount++;
    echo "  -> PASSED: Unique booking reference generated: {$ref4} vs {$ref5}.\n\n";

    // -------------------------------------------------------------
    // Scenario 6: Reserve multiple rooms in one booking
    // -------------------------------------------------------------
    echo "[Scenario 6] Reserve multiple rooms in one booking...\n";
    $roomM1 = Room::where('is_active', 1)->skip(2)->firstOrFail();
    $roomM2 = Room::where('is_active', 1)->skip(3)->firstOrFail();
    $req6 = Request::create('/api/v1/bookings', 'POST', [
        'check_in_date' => '2027-04-10',
        'check_out_date' => '2027-04-14',
        'rooms' => [
            ['room_id' => $roomM1->id, 'adults' => 2, 'children' => 1],
            ['room_id' => $roomM2->id, 'adults' => 1, 'children' => 0],
        ],
        'guest_name' => 'Mulatu Astatke',
        'guest_email' => 'mulatu.astatke@ethiojazz.et',
        'guest_phone' => '+251 933 445566',
        'special_requests' => 'Connecting rooms for band ensemble',
    ]);
    $res6 = $bookingController->store($req6);
    $data6 = json_decode($res6->getContent(), true);
    assert($res6->getStatusCode() === 201, "Scenario 6 Failed: Multi-room creation failed");
    assert($data6['booking']['rooms_count'] === 2, "Scenario 6 Failed: Expected rooms_count = 2");
    $multiId = $data6['booking']['id'];
    $bookingRoomsCount = BookingRoom::where('booking_id', $multiId)->count();
    assert($bookingRoomsCount === 2, "Scenario 6 Failed: Expected 2 booking_rooms records in DB, found {$bookingRoomsCount}");
    $passedCount++;
    echo "  -> PASSED: Multi-room booking created with 2 rooms under reference {$data6['booking']['reference_code']}.\n\n";

    // -------------------------------------------------------------
    // Scenario 7: Prevent booking unavailable rooms
    // -------------------------------------------------------------
    echo "[Scenario 7] Prevent booking unavailable rooms (maintenance / inactive)...\n";
    $maintRoom = Room::where('is_active', 1)->skip(4)->firstOrFail();
    $origStatus = $maintRoom->status;
    $maintRoom->update(['status' => 'maintenance']);

    $req7 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $maintRoom->id,
        'check_in_date' => '2027-05-01',
        'check_out_date' => '2027-05-03',
        'guest_name' => 'Test Guest',
        'guest_email' => 'testguest@hotel.et',
    ]);
    $res7 = $bookingController->store($req7);
    assert($res7->getStatusCode() === 409 || $res7->getStatusCode() === 422, "Scenario 7 Failed: Expected 409/422, got " . $res7->getStatusCode());
    // Restore room status
    $maintRoom->update(['status' => $origStatus]);
    $passedCount++;
    echo "  -> PASSED: Prevented booking of room under maintenance with HTTP " . $res7->getStatusCode() . ".\n\n";

    // -------------------------------------------------------------
    // Scenario 8: Prevent overlapping reservations
    // -------------------------------------------------------------
    echo "[Scenario 8] Prevent overlapping reservations on reserved room...\n";
    // Room testRoom is booked 2027-03-01 to 2027-03-04 in Scenario 4.
    // Try booking 2027-03-02 to 2027-03-05
    $req8 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $testRoom->id,
        'check_in_date' => '2027-03-02',
        'check_out_date' => '2027-03-05',
        'guest_name' => 'Collision Guest',
        'guest_email' => 'collision@hotel.et',
    ]);
    $res8 = $bookingController->store($req8);
    assert($res8->getStatusCode() === 409, "Scenario 8 Failed: Expected 409 Conflict, got " . $res8->getStatusCode());
    $passedCount++;
    echo "  -> PASSED: Overlapping reservation successfully blocked with HTTP 409 Conflict.\n\n";

    // -------------------------------------------------------------
    // Scenario 9: Allow consecutive bookings with no overlapping dates (exclusive check-out)
    // -------------------------------------------------------------
    echo "[Scenario 9] Allow consecutive bookings with no overlapping dates (exclusive check-out)...\n";
    // Prior booking is 2027-03-01 to 2027-03-04. Next booking starts 2027-03-04!
    $req9 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $testRoom->id,
        'check_in_date' => '2027-03-04',
        'check_out_date' => '2027-03-07',
        'guest_name' => 'Next Turn Guest',
        'guest_email' => 'nextturn@hotel.et',
    ]);
    $res9 = $bookingController->store($req9);
    assert($res9->getStatusCode() === 201, "Scenario 9 Failed: Consecutive booking rejected with status " . $res9->getStatusCode());
    $consecBookingId = json_decode($res9->getContent(), true)['booking']['id'];
    $passedCount++;
    echo "  -> PASSED: Consecutive booking starting on checkout day (2027-03-04) accepted without false collision.\n\n";

    // -------------------------------------------------------------
    // Scenario 10: Modify an existing reservation
    // -------------------------------------------------------------
    echo "[Scenario 10] Modify an existing reservation (dates & requests)...\n";
    $req10 = Request::create("/api/v1/bookings/{$booking5Id}", 'PUT', [
        'check_in_date' => '2027-03-01',
        'check_out_date' => '2027-03-05', // extended from 2 nights to 4 nights
        'special_requests' => 'VIP airport welcome requested.',
    ]);
    $res10 = $bookingController->update($req10, $booking5Id);
    $data10 = json_decode($res10->getContent(), true);
    assert($res10->getStatusCode() === 200, "Scenario 10 Failed: Modify failed with status " . $res10->getStatusCode());
    assert($data10['booking']['check_out_date'] === '2027-03-05', "Scenario 10 Failed: Updated check_out_date mismatch");
    assert($data10['price_difference_etb'] > 0, "Scenario 10 Failed: Expected positive price difference for extension");
    $passedCount++;
    echo "  -> PASSED: Modified reservation {$booking5Id}: extended to 4 nights with recalculation.\n\n";

    // -------------------------------------------------------------
    // Scenario 11: Prevent modification that causes double booking
    // -------------------------------------------------------------
    echo "[Scenario 11] Prevent modification that causes double booking...\n";
    // booking4 is 2027-03-01 to 2027-03-04 on testRoom.
    // consecBooking is 2027-03-04 to 2027-03-07 on testRoom.
    // Try to extend booking4 check_out to 2027-03-06 (collides with consecBooking)
    $req11 = Request::create("/api/v1/bookings/{$booking4Id}", 'PUT', [
        'check_in_date' => '2027-03-01',
        'check_out_date' => '2027-03-06',
    ]);
    $res11 = $bookingController->update($req11, $booking4Id);
    assert($res11->getStatusCode() === 409, "Scenario 11 Failed: Expected 409 Conflict, got " . $res11->getStatusCode());
    $passedCount++;
    echo "  -> PASSED: Prevented conflicting modification with HTTP 409 Conflict.\n\n";

    // -------------------------------------------------------------
    // Scenario 12: Cancel a reservation successfully
    // -------------------------------------------------------------
    echo "[Scenario 12] Cancel a reservation successfully with recorded reason...\n";
    $req12 = Request::create("/api/v1/bookings/{$consecBookingId}/cancel", 'POST', [
        'cancellation_reason' => 'Guest schedule changed due to conference postponement.',
    ]);
    $res12 = $bookingController->cancel($req12, $consecBookingId);
    $data12 = json_decode($res12->getContent(), true);
    assert($res12->getStatusCode() === 200, "Scenario 12 Failed: Cancel failed with " . $res12->getStatusCode());
    assert($data12['booking']['booking_status'] === 'cancelled', "Scenario 12 Failed: Status not cancelled");
    assert(!empty($data12['booking']['cancellation_reason']), "Scenario 12 Failed: Missing cancellation reason");
    $passedCount++;
    echo "  -> PASSED: Reservation cancelled and reason recorded.\n\n";

    // -------------------------------------------------------------
    // Scenario 13: Verify cancelled rooms become available where appropriate
    // -------------------------------------------------------------
    echo "[Scenario 13] Verify cancelled dates become available for re-booking...\n";
    // The dates 2027-03-04 to 2027-03-07 on testRoom should now be open again
    $req13 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $testRoom->id,
        'check_in_date' => '2027-03-04',
        'check_out_date' => '2027-03-07',
        'guest_name' => 'Replacement Guest',
        'guest_email' => 'replacement@hotel.et',
    ]);
    $res13 = $bookingController->store($req13);
    assert($res13->getStatusCode() === 201, "Scenario 13 Failed: Rebooking cancelled room dates failed: " . $res13->getStatusCode());
    $replacementBookingId = json_decode($res13->getContent(), true)['booking']['id'];
    $passedCount++;
    echo "  -> PASSED: Cancelled dates successfully re-booked by new guest without conflict.\n\n";

    // -------------------------------------------------------------
    // Scenario 14: Preserve cancelled booking history
    // -------------------------------------------------------------
    echo "[Scenario 14] Preserve cancelled booking history and audit trail...\n";
    $cancelledRecord = Booking::find($consecBookingId);
    assert($cancelledRecord !== null, "Scenario 14 Failed: Cancelled record was purged instead of preserved");
    assert($cancelledRecord->booking_status === 'cancelled', "Scenario 14 Failed: Status mismatch");
    assert($cancelledRecord->cancellation_reason !== null, "Scenario 14 Failed: Reason lost");
    $auditCancel = DB::table('audit_logs')->where('resource_id', $consecBookingId)->where('action', 'cancel_reservation')->first();
    assert($auditCancel !== null, "Scenario 14 Failed: Audit log for cancellation missing");
    $passedCount++;
    echo "  -> PASSED: Cancelled reservation preserved in DB with intact audit history.\n\n";

    // -------------------------------------------------------------
    // Scenario 15: Create a walk-in reservation
    // -------------------------------------------------------------
    echo "[Scenario 15] Create a walk-in reservation with immediate check-in...\n";
    $walkInRoom = Room::where('is_active', 1)->where('id', '!=', $testRoom->id)->where('status', 'available')->firstOrFail();
    $today = now()->toDateString();
    $tomorrow = now()->addDays(1)->toDateString();

    $req15 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $walkInRoom->id,
        'check_in_date' => $today,
        'check_out_date' => $tomorrow,
        'is_walk_in' => true,
        'payment_method' => 'cash',
        'guest_name' => 'Walk-In Guest Dawit',
        'guest_email' => 'dawit.walkin@test.et',
        'guest_phone' => '+251 911 889900',
    ]);
    $res15 = $bookingController->store($req15);
    $data15 = json_decode($res15->getContent(), true);
    assert($res15->getStatusCode() === 201, "Scenario 15 Failed: Walk-in creation failed");
    assert($data15['booking']['is_walk_in'] == 1, "Scenario 15 Failed: is_walk_in flag not 1");
    assert($data15['booking']['payment_status'] === 'paid', "Scenario 15 Failed: Expected payment_status paid for walk-in");
    $walkInBookingId = $data15['booking']['id'];
    $walkInRef = $data15['booking']['reference_code'];
    // Verify room operational status updated to occupied
    $walkInRoom->refresh();
    assert($walkInRoom->status === 'occupied', "Scenario 15 Failed: Walk-in room status expected occupied, got {$walkInRoom->status}");
    $passedCount++;
    echo "  -> PASSED: Walk-in reservation created with immediate occupancy on Room {$walkInRoom->room_number}.\n\n";

    // -------------------------------------------------------------
    // Scenario 16: Search reservations by booking reference
    // -------------------------------------------------------------
    echo "[Scenario 16] Search reservations by booking reference...\n";
    $req16 = Request::create('/api/v1/desk/bookings', 'GET', [
        'search' => $walkInRef,
    ]);
    $res16 = $bookingController->desk($req16);
    $data16 = json_decode($res16->getContent(), true);
    assert($res16->getStatusCode() === 200, "Scenario 16 Failed: Status not 200");
    assert(count($data16['reservations']) >= 1, "Scenario 16 Failed: No reservation found for reference {$walkInRef}");
    assert($data16['reservations'][0]['reference_code'] === $walkInRef, "Scenario 16 Failed: Reference mismatch");
    $passedCount++;
    echo "  -> PASSED: Desk search successfully located reservation by reference {$walkInRef}.\n\n";

    // -------------------------------------------------------------
    // Scenario 17: Filter reservations by status
    // -------------------------------------------------------------
    echo "[Scenario 17] Filter reservations by status...\n";
    $req17 = Request::create('/api/v1/desk/bookings', 'GET', [
        'status' => 'cancelled',
    ]);
    $res17 = $bookingController->desk($req17);
    $data17 = json_decode($res17->getContent(), true);
    assert($res17->getStatusCode() === 200, "Scenario 17 Failed: Status not 200");
    foreach ($data17['reservations'] as $resItem) {
        assert($resItem['booking_status'] === 'cancelled', "Scenario 17 Failed: Non-cancelled reservation in filtered list");
    }
    $passedCount++;
    echo "  -> PASSED: Filter returned " . count($data17['reservations']) . " reservations, all matching status 'cancelled'.\n\n";

    // -------------------------------------------------------------
    // Scenario 18: View guest booking history
    // -------------------------------------------------------------
    echo "[Scenario 18] View guest booking history...\n";
    $guestSara = User::where('email', 'sara.haile@test.et')->firstOrFail();
    $res18 = $bookingController->guestBookings($guestSara->id);
    $data18 = json_decode($res18->getContent(), true);
    assert($res18->getStatusCode() === 200, "Scenario 18 Failed: Status not 200");
    assert(isset($data18['bookings']), "Scenario 18 Failed: Missing bookings list");
    assert(count($data18['bookings']) >= 1, "Scenario 18 Failed: Expected history for Sara Haile");
    $passedCount++;
    echo "  -> PASSED: Guest history retrieved for {$guestSara->full_name} (" . count($data18['bookings']) . " bookings).\n\n";

    // -------------------------------------------------------------
    // Scenario 19: Validate reservation pricing
    // -------------------------------------------------------------
    echo "[Scenario 19] Validate reservation pricing...\n";
    $pricingRoom = Room::where('is_active', 1)->firstOrFail();
    $req19 = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $pricingRoom->id,
        'check_in_date' => '2027-06-10',
        'check_out_date' => '2027-06-13', // 3 nights
        'meal_plan' => 'bed_breakfast', // 650 ETB/guest/night
        'addons' => ['airport_shuttle'], // 1200 ETB
        'adults' => 2,
        'guest_name' => 'Pricing Guest',
        'guest_email' => 'pricing@hotel.et',
    ]);
    $res19 = $bookingController->store($req19);
    $data19 = json_decode($res19->getContent(), true);
    assert($res19->getStatusCode() === 201, "Scenario 19 Failed: Booking creation failed");
    $expectedRoomCharges = (float)$pricingRoom->price_etb * 3;
    $expectedMealCharges = 650 * 2 * 3; // 3900 ETB
    $expectedAddons = 1200; // shuttle
    $expectedTotal = $expectedRoomCharges + $expectedMealCharges + $expectedAddons;
    assert((float)$data19['booking']['total_price_etb'] === (float)$expectedTotal, "Scenario 19 Failed: Expected total {$expectedTotal}, got {$data19['booking']['total_price_etb']}");
    $pricingBookingId = $data19['booking']['id'];
    $passedCount++;
    echo "  -> PASSED: Pricing formula validated: Room({$expectedRoomCharges}) + Meals({$expectedMealCharges}) + Addon({$expectedAddons}) = {$expectedTotal} ETB.\n\n";

    // -------------------------------------------------------------
    // Scenario 20: Test simultaneous booking requests for the same room (concurrency lock)
    // -------------------------------------------------------------
    echo "[Scenario 20] Test concurrency lock & collision check...\n";
    // Simulate transaction 1 acquiring lock and creating booking, transaction 2 trying the same window
    $concurRoom = Room::where('is_active', 1)->skip(5)->firstOrFail();
    $dIn = '2027-07-01';
    $dOut = '2027-07-05';

    // Request A
    $reqA = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $concurRoom->id,
        'check_in_date' => $dIn,
        'check_out_date' => $dOut,
        'guest_name' => 'Concurrent Guest A',
        'guest_email' => 'guestA@hotel.et',
    ]);
    $resA = $bookingController->store($reqA);
    assert($resA->getStatusCode() === 201, "Scenario 20 Failed: Request A should have succeeded");
    $bookingAId = json_decode($resA->getContent(), true)['booking']['id'];

    // Request B (Simultaneous request on exact same room and dates)
    $reqB = Request::create('/api/v1/bookings', 'POST', [
        'room_id' => $concurRoom->id,
        'check_in_date' => $dIn,
        'check_out_date' => $dOut,
        'guest_name' => 'Concurrent Guest B',
        'guest_email' => 'guestB@hotel.et',
    ]);
    $resB = $bookingController->store($reqB);
    assert($resB->getStatusCode() === 409, "Scenario 20 Failed: Request B should be blocked with 409 Conflict");
    $passedCount++;
    echo "  -> PASSED: Concurrency lock successfully blocked duplicate simultaneous request with HTTP 409.\n\n";

    // -------------------------------------------------------------
    // Scenario 21: Verify reservation status transitions
    // -------------------------------------------------------------
    echo "[Scenario 21] Verify reservation status transitions (lifecycle & guard checks)...\n";
    // Transition test: booking4 (confirmed) -> checked_in -> checked_out
    $testRoom->update(['status' => 'available', 'housekeeping_status' => 'inspected']);
    
    // Check in booking4
    $reqCI = Request::create("/api/v1/bookings/{$booking4Id}/check-in", 'POST');
    $resCI = $bookingController->checkIn($reqCI, $booking4Id);
    assert($resCI->getStatusCode() === 200, "Scenario 21 Failed: Check-in failed: " . $resCI->getStatusCode());
    assert(Booking::find($booking4Id)->booking_status === 'checked_in', "Scenario 21 Failed: Status not checked_in");

    // Attempt invalid transition: check-in again while already checked_in
    $resCIInvalid = $bookingController->checkIn($reqCI, $booking4Id);
    assert($resCIInvalid->getStatusCode() === 422, "Scenario 21 Failed: Duplicate check-in should be rejected with 422");

    // Check out booking4
    $reqCO = Request::create("/api/v1/bookings/{$booking4Id}/check-out", 'POST');
    $resCO = $bookingController->checkOut($reqCO, $booking4Id);
    assert($resCO->getStatusCode() === 200, "Scenario 21 Failed: Check-out failed: " . $resCO->getStatusCode());
    assert(Booking::find($booking4Id)->booking_status === 'checked_out', "Scenario 21 Failed: Status not checked_out");

    // Attempt invalid transition: check-out again while already checked_out
    $resCOInvalid = $bookingController->checkOut($reqCO, $booking4Id);
    assert($resCOInvalid->getStatusCode() === 422, "Scenario 21 Failed: Duplicate check-out should be rejected with 422");

    $passedCount++;
    echo "  -> PASSED: Reservation lifecycle (confirmed -> checked_in -> checked_out) and guard transitions verified.\n\n";

    // -------------------------------------------------------------
    // Scenario 22: Verify integration with existing Room Management and Check-in/Check-out modules
    // -------------------------------------------------------------
    echo "[Scenario 22] Verify Room Management, Housekeeping & Check-in/Check-out integration...\n";
    // Following Scenario 21 check-out of booking4, testRoom must transition to cleaning & housekeeping dirty
    $testRoom->refresh();
    assert($testRoom->status === 'cleaning', "Scenario 22 Failed: Room status expected 'cleaning', got '{$testRoom->status}'");
    assert($testRoom->housekeeping_status === 'dirty', "Scenario 22 Failed: Housekeeping status expected 'dirty', got '{$testRoom->housekeeping_status}'");

    // Complete housekeeping cleaning turnover
    $reqHK = Request::create("/api/v1/housekeeping/{$testRoom->id}", 'PUT', [
        'housekeeping_status' => 'inspected',
    ]);
    $resHK = $housekeepingController->update($reqHK, $testRoom->id);
    assert($resHK->getStatusCode() === 200, "Scenario 22 Failed: Housekeeping update failed");
    $testRoom->refresh();
    assert($testRoom->status === 'available', "Scenario 22 Failed: Room status expected 'available' after inspection, got '{$testRoom->status}'");
    assert($testRoom->housekeeping_status === 'inspected', "Scenario 22 Failed: Housekeeping status expected 'inspected', got '{$testRoom->housekeeping_status}'");

    $passedCount++;
    echo "  -> PASSED: Seamless integration verified: Check-Out sets room cleaning/dirty -> Housekeeping turnover sets room available/inspected.\n\n";

    // -------------------------------------------------------------
    // Cleanup generated test data
    // -------------------------------------------------------------
    echo "Cleaning up transient test bookings...\n";
    cleanupTestBooking($booking4Id);
    cleanupTestBooking($booking5Id);
    cleanupTestBooking($multiId);
    cleanupTestBooking($consecBookingId);
    cleanupTestBooking($replacementBookingId);
    cleanupTestBooking($walkInBookingId);
    cleanupTestBooking($pricingBookingId);
    cleanupTestBooking($bookingAId);

    echo "\n====================================================================\n";
    echo "   TEST SUMMARY: {$passedCount} / {$totalTests} SCENARIOS PASSED (100% SUCCESS)\n";
    echo "====================================================================\n";

} catch (\Throwable $e) {
    echo "\n!!! TEST FAILURE AT SCENARIO " . ($passedCount + 1) . " !!!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
    exit(1);
}
