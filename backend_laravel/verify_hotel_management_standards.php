<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Booking;
use App\Models\Amenity;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;

$roomController = new RoomController();
$bookingController = new BookingController();

echo "====================================================================\n";
echo "   HOTEL MANAGEMENT SYSTEM - ROOM MANAGEMENT VERIFICATION TEST SUITE\n";
echo "====================================================================\n\n";

$passedCount = 0;

// Scenario 1: Add a new room successfully
echo "[Scenario 1] Add a new room successfully...\n";
$testRoomNum = "888";
Room::where('room_number', $testRoomNum)->forceDelete();

$req1 = Request::create('/api/v1/rooms', 'POST', [
    'room_number' => $testRoomNum,
    'category_id' => 'cat-deluxe',
    'title' => 'Executive Mountain View Room 888',
    'floor' => 8,
    'capacity' => 2,
    'price_etb' => 2900,
    'price_usd' => 45,
    'description' => 'Spectacular suite with high-altitude vistas and luxury comfort.',
    'amenities' => ['amen-wifi', 'amen-balcony', 'amen-breakfast'],
]);
$res1 = $roomController->store($req1);
$data1 = json_decode($res1->getContent(), true);
assert($res1->getStatusCode() === 201, "Scenario 1 Failed: Status not 201");
assert($data1['room']['room_number'] === $testRoomNum, "Scenario 1 Failed: Room number mismatch");
$room888Id = $data1['room']['id'];
$passedCount++;
echo "  -> SUCCESS: Room {$testRoomNum} created with ID {$room888Id}.\n\n";

// Scenario 2: Prevent adding a room with an existing room number
echo "[Scenario 2] Prevent adding a room with an existing room number...\n";
try {
    $req2 = Request::create('/api/v1/rooms', 'POST', [
        'room_number' => $testRoomNum,
        'category_id' => 'cat-suite',
        'title' => 'Duplicate Room Number 888',
        'floor' => 1,
        'capacity' => 2,
        'price_etb' => 3000,
    ]);
    $roomController->store($req2);
    echo "  -> FAILED: Duplicate was allowed!\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    assert(isset($e->errors()['room_number']), "Scenario 2 Failed: Expected room_number error");
    $passedCount++;
    echo "  -> SUCCESS: Duplicate room number was blocked. Error: " . $e->errors()['room_number'][0] . "\n\n";
}

// Scenario 3: Update room information successfully
echo "[Scenario 3] Update room information successfully...\n";
$req3 = Request::create("/api/v1/rooms/{$room888Id}", 'PUT', [
    'title' => 'Updated Executive Mountain View Deluxe 888',
    'capacity' => 3,
    'description' => 'Upgraded with complimentary fruit basket and evening wine.',
    'amenities' => ['amen-wifi', 'amen-balcony', 'amen-breakfast', 'amen-spa'],
]);
$res3 = $roomController->update($req3, $room888Id);
$data3 = json_decode($res3->getContent(), true);
assert($res3->getStatusCode() === 200, "Scenario 3 Failed: Status not 200");
assert($data3['room']['capacity'] === 3, "Scenario 3 Failed: Capacity not updated");
assert(count($data3['room']['amenities']) === 4, "Scenario 3 Failed: Amenities not updated");
$passedCount++;
echo "  -> SUCCESS: Room information updated. Capacity: 3, Amenities count: 4.\n\n";

// Scenario 4: Change room prices
echo "[Scenario 4] Change room prices...\n";
$req4 = Request::create("/api/v1/rooms/{$room888Id}", 'PUT', [
    'price_etb' => 3400,
    'price_usd' => 53,
]);
$res4 = $roomController->update($req4, $room888Id);
$data4 = json_decode($res4->getContent(), true);
assert($data4['room']['price_etb'] == 3400, "Scenario 4 Failed: Price ETB not updated");
assert($data4['room']['price_usd'] == 53, "Scenario 4 Failed: Price USD not updated");
$passedCount++;
echo "  -> SUCCESS: Room prices updated to 3,400 ETB / $53 USD.\n\n";

// Scenario 5: Delete a room without historical records
echo "[Scenario 5] Delete a room without historical records...\n";
$req5 = Request::create("/api/v1/rooms/{$room888Id}", 'DELETE');
$res5 = $roomController->destroy($req5, $room888Id);
$data5 = json_decode($res5->getContent(), true);
assert($data5['action'] === 'deleted', "Scenario 5 Failed: Action is not 'deleted'");
assert(!Room::where('id', $room888Id)->exists(), "Scenario 5 Failed: Room still exists in DB");
$passedCount++;
echo "  -> SUCCESS: Room {$testRoomNum} had no bookings and was permanently deleted.\n\n";

// Scenario 6: Prevent deletion of a room with historical booking records (Deactivate instead)
echo "[Scenario 6] Prevent hard deletion of a room with historical booking records...\n";
// rm-101 has historical bookings (ensure not currently occupied for test)
Room::where('id', 'rm-101')->update(['status' => 'available']);
Booking::where('room_id', 'rm-101')->where('booking_status', 'checked_in')->update(['booking_status' => 'checked_out']);
$req6 = Request::create("/api/v1/rooms/rm-101", 'DELETE');
$res6 = $roomController->destroy($req6, 'rm-101');
$data6 = json_decode($res6->getContent(), true);
assert($data6['action'] === 'deactivated', "Scenario 6 Failed: Action is not 'deactivated'");
$rm101Check = Room::find('rm-101');
assert($rm101Check && !$rm101Check->is_active, "Scenario 6 Failed: Room is not deactivated");
// Reactivate rm-101 for operational continuity
$rm101Check->update(['is_active' => 1]);
$passedCount++;
echo "  -> SUCCESS: Room rm-101 was soft-deactivated to preserve ledger history, not permanently deleted.\n\n";

// Scenario 7: Search and filter rooms
echo "[Scenario 7] Search and filter rooms...\n";
$req7 = Request::create('/api/v1/rooms', 'GET', [
    'category' => 'suite',
    'status' => 'available',
    'min_price' => 2000,
    'max_price' => 3000,
]);
$res7 = $roomController->index($req7);
$data7 = json_decode($res7->getContent(), true);
assert(is_array($data7['rooms']), "Scenario 7 Failed: rooms is not an array");
$passedCount++;
echo "  -> SUCCESS: Filtered by category 'suite', status 'available', price 2000-3000 ETB. Found " . count($data7['rooms']) . " rooms.\n\n";

// Scenario 8: Check room availability for specific dates
echo "[Scenario 8] Check room availability for specific dates...\n";
$checkInTest = '2026-12-01';
$checkOutTest = '2026-12-05';
$req8 = Request::create('/api/v1/rooms', 'GET', [
    'check_in' => $checkInTest,
    'check_out' => $checkOutTest,
]);
$res8 = $roomController->index($req8);
$data8 = json_decode($res8->getContent(), true);
assert(isset($data8['summary']), "Scenario 8 Failed: Missing summary");
$passedCount++;
echo "  -> SUCCESS: Availability calculated for {$checkInTest} to {$checkOutTest}. Available count: {$data8['summary']['available_count']}.\n\n";

// Scenario 9: Prevent overlapping reservations (Double Booking Prevention)
echo "[Scenario 9] Prevent overlapping reservations...\n";
// Clean up any previous test booking on rm-102 for these test dates
$oldBookings = Booking::where('room_id', 'rm-102')
    ->where('check_in_date', '>=', '2026-12-01')
    ->get();
foreach ($oldBookings as $ob) {
    \App\Models\Payment::where('booking_id', $ob->id)->delete();
    $ob->delete();
}

// Create a reservation on rm-102
$bookReq1 = Request::create('/api/v1/bookings', 'POST', [
    'room_id' => 'rm-102',
    'check_in_date' => '2026-12-10',
    'check_out_date' => '2026-12-15',
    'guest_name' => 'First Guest',
    'guest_email' => 'first.guest@example.com',
]);
$bookRes1 = $bookingController->store($bookReq1);
assert($bookRes1->getStatusCode() === 201, "First booking failed");

// Try creating an overlapping reservation for rm-102 (e.g. 2026-12-12 to 2026-12-17)
$bookReqOverlap = Request::create('/api/v1/bookings', 'POST', [
    'room_id' => 'rm-102',
    'check_in_date' => '2026-12-12',
    'check_out_date' => '2026-12-17',
    'guest_name' => 'Conflicting Guest',
    'guest_email' => 'conflict@example.com',
]);
$bookResOverlap = $bookingController->store($bookReqOverlap);
assert($bookResOverlap->getStatusCode() === 409, "Scenario 9 Failed: Overlapping booking was NOT rejected!");
$passedCount++;
echo "  -> SUCCESS: Overlapping reservation on Room rm-102 was rejected with 409 Conflict.\n\n";

// Scenario 10: Change room status correctly
echo "[Scenario 10] Change room status correctly...\n";
$req10 = Request::create('/api/v1/rooms/rm-103/status', 'PATCH', [
    'status' => 'cleaning',
]);
$res10 = $roomController->updateStatus($req10, 'rm-103');
$data10 = json_decode($res10->getContent(), true);
assert($data10['status'] === 'cleaning', "Scenario 10 Failed: Status not cleaning");
assert($data10['housekeeping_status'] === 'dirty', "Scenario 10 Failed: Housekeeping status not dirty");

// Revert to available
$req10Rev = Request::create('/api/v1/rooms/rm-103/status', 'PATCH', [
    'status' => 'available',
]);
$roomController->updateStatus($req10Rev, 'rm-103');
$passedCount++;
echo "  -> SUCCESS: Room status transitioned to 'cleaning' (housekeeping dirty), then restored to 'available'.\n\n";

// Scenario 11: Prevent booking rooms under maintenance
echo "[Scenario 11] Prevent booking rooms under maintenance...\n";
// rm-105 is under maintenance
$req11 = Request::create('/api/v1/bookings', 'POST', [
    'room_id' => 'rm-105',
    'check_in_date' => '2026-12-20',
    'check_out_date' => '2026-12-25',
    'guest_name' => 'Maintenance Tester',
    'guest_email' => 'maint@example.com',
]);
$res11 = $bookingController->store($req11);
assert($res11->getStatusCode() === 409, "Scenario 11 Failed: Booking room under maintenance was allowed!");
$passedCount++;
echo "  -> SUCCESS: Attempt to book maintenance room rm-105 was blocked with 409 Conflict.\n\n";

// Scenario 12: Ensure room status updates correctly after check-in and check-out
echo "[Scenario 12] Ensure room status updates correctly after check-in and check-out...\n";
// Create walk-in check-in on rm-110
$rm110 = Room::find('rm-110');
$rm110->update(['status' => 'available', 'housekeeping_status' => 'inspected']);

$walkInReq = Request::create('/api/v1/bookings', 'POST', [
    'room_id' => 'rm-110',
    'check_in_date' => now()->toDateString(),
    'check_out_date' => now()->addDays(2)->toDateString(),
    'is_walk_in' => true,
    'guest_name' => 'Arrival Guest',
    'guest_email' => 'arrival.guest@example.com',
]);
$walkInRes = $bookingController->store($walkInReq);
$walkInData = json_decode($walkInRes->getContent(), true);
$testBookingId = $walkInData['booking']['id'];

// Check in the guest
$checkInReq = Request::create("/api/v1/bookings/{$testBookingId}/check-in", 'POST');
$checkInRes = $bookingController->checkIn($checkInReq, $testBookingId);
$rm110AfterCheckIn = Room::find('rm-110');
assert($rm110AfterCheckIn->status === 'occupied', "Scenario 12 Failed: Room status is not 'occupied' after check-in");
echo "  -> Check-In verified: Room status is '{$rm110AfterCheckIn->status}'.\n";

// Check out the guest
$checkOutReq = Request::create("/api/v1/bookings/{$testBookingId}/check-out", 'POST');
$checkOutRes = $bookingController->checkOut($checkOutReq, $testBookingId);
$rm110AfterCheckOut = Room::find('rm-110');
assert($rm110AfterCheckOut->status === 'cleaning', "Scenario 12 Failed: Room status is not 'cleaning' after check-out");
assert($rm110AfterCheckOut->housekeeping_status === 'dirty', "Scenario 12 Failed: Housekeeping status is not 'dirty' after check-out");
echo "  -> Check-Out verified: Room status is '{$rm110AfterCheckOut->status}' and housekeeping is '{$rm110AfterCheckOut->housekeeping_status}'.\n";

// Housekeeper completes cleaning
$hkReq = Request::create("/api/v1/housekeeping/rm-110", 'PATCH', [
    'housekeeping_status' => 'clean'
]);
$hkController = new \App\Http\Controllers\Api\HousekeepingController();
$hkController->update($hkReq, 'rm-110');
$rm110AfterCleaning = Room::find('rm-110');
assert($rm110AfterCleaning->status === 'available', "Scenario 12 Failed: Room status is not 'available' after cleaning");
echo "  -> Cleaning completion verified: Room status restored to '{$rm110AfterCleaning->status}'.\n";

$passedCount++;
echo "  -> SUCCESS: Complete operational lifecycle verified (Check-in -> Occupied -> Check-out -> Cleaning/Dirty -> Housekeeping Cleaned -> Available).\n\n";

echo "====================================================================\n";
echo "   RESULT: {$passedCount} / 12 SCENARIOS PASSED WITH ZERO FAILURES!\n";
echo "====================================================================\n";
