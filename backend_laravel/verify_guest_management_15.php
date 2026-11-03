<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Room;
use App\Models\User;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\BookingController;

$guestController = new GuestController();
$bookingController = new BookingController();

echo "====================================================================\n";
echo "   GUEST MANAGEMENT MODULE - 15 SCENARIOS VERIFICATION SUITE\n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 15;

function cleanupGuestAndBookings($emailOrIdNumber) {
    $users = User::where('email', $emailOrIdNumber)
        ->orWhere('id_number', $emailOrIdNumber)
        ->orWhere('phone', $emailOrIdNumber)
        ->get();
    foreach ($users as $user) {
        $bookings = Booking::where('guest_id', $user->id)->get();
        foreach ($bookings as $b) {
            Payment::where('booking_id', $b->id)->delete();
            BookingRoom::where('booking_id', $b->id)->delete();
            DB::table('audit_logs')->where('resource_id', $b->id)->delete();
            $b->delete();
        }
        DB::table('audit_logs')->where('resource_id', $user->id)->delete();
        $user->delete();
    }
}

// Pre-test cleanup
cleanupGuestAndBookings('tirunesh.test@hotel.et');
cleanupGuestAndBookings('haile.test@hotel.et');
cleanupGuestAndBookings('new.guest@hotel.et');
cleanupGuestAndBookings('ET-PASS-990011');
cleanupGuestAndBookings('+251 911 998877');
cleanupGuestAndBookings('+251 911 998878');


try {
    // -------------------------------------------------------------
    // Scenario 1: Register a new guest
    // -------------------------------------------------------------
    echo "[Scenario 1] Register a new guest with comprehensive details...\n";
    $req1 = Request::create('/api/v1/guests', 'POST', [
        'first_name' => 'Tirunesh',
        'last_name' => 'Dibaba',
        'email' => 'tirunesh.test@hotel.et',
        'phone' => '+251 911 998877',
        'gender' => 'female',
        'date_of_birth' => '1985-10-01',
        'nationality' => 'Ethiopian',
        'address' => 'Bole Atlas Avenue, Woreda 03',
        'city' => 'Addis Ababa',
        'country' => 'Ethiopia',
        'id_type' => 'passport',
        'id_number' => 'ET-PASS-990011',
        'id_issue_country' => 'Ethiopia',
        'id_expiry_date' => '2030-10-01',
        'vip_status' => 'vip',
        'preferences' => [
            'smoking' => false,
            'bed_type' => 'king',
            'floor_preference' => 'high',
            'dietary' => 'Ethiopian Fasting (Vegan)',
            'accessibility_needs' => false,
        ],
        'notes' => 'Olympic champion and long-time luxury patron. Prepare welcome fruit basket.',
    ]);
    $res1 = $guestController->store($req1);
    $data1 = json_decode($res1->getContent(), true);
    assert($res1->getStatusCode() === 201, "Scenario 1 Failed: Status not 201, got " . $res1->getStatusCode());
    assert(isset($data1['guest']['id']), "Scenario 1 Failed: Missing guest id");
    assert($data1['guest']['full_name'] === 'Tirunesh Dibaba', "Scenario 1 Failed: full_name mismatch");
    assert($data1['guest']['first_name'] === 'Tirunesh', "Scenario 1 Failed: first_name mismatch");
    assert($data1['guest']['vip_status'] === 'vip', "Scenario 1 Failed: vip_status mismatch");
    $tiruneshId = $data1['guest']['id'];
    $passedTests++;
    echo "  -> PASSED: Guest registered successfully with ID {$tiruneshId} and VIP tier 'vip'.\n\n";

    // -------------------------------------------------------------
    // Scenario 2: Update guest information
    // -------------------------------------------------------------
    echo "[Scenario 2] Update guest information...\n";
    $req2 = Request::create("/api/v1/guests/{$tiruneshId}", 'PUT', [
        'first_name' => 'Tirunesh',
        'last_name' => 'Dibaba',
        'email' => 'tirunesh.test@hotel.et',
        'phone' => '+251 911 998878', // updated phone
        'address' => 'Kazanchis Heritage Avenue, Addis Ababa', // updated address
        'vip_status' => 'vvip', // upgraded to VVIP
        'nationality' => 'Ethiopian',
    ]);
    $res2 = $guestController->update($req2, $tiruneshId);
    $data2 = json_decode($res2->getContent(), true);
    assert($res2->getStatusCode() === 200, "Scenario 2 Failed: Status not 200, got " . $res2->getStatusCode());
    assert($data2['guest']['phone'] === '+251 911 998878', "Scenario 2 Failed: phone was not updated");
    assert($data2['guest']['address'] === 'Kazanchis Heritage Avenue, Addis Ababa', "Scenario 2 Failed: address was not updated");
    assert($data2['guest']['vip_status'] === 'vvip', "Scenario 2 Failed: vip_status was not upgraded to vvip");
    $passedTests++;
    echo "  -> PASSED: Guest details updated successfully: phone, address & VIP tier to 'vvip'.\n\n";

    // -------------------------------------------------------------
    // Scenario 3: View a guest profile
    // -------------------------------------------------------------
    echo "[Scenario 3] View a guest profile & stay dossier...\n";
    $req3 = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $res3 = $guestController->show($req3, $tiruneshId);
    $data3 = json_decode($res3->getContent(), true);
    assert($res3->getStatusCode() === 200, "Scenario 3 Failed: Status not 200");
    assert(isset($data3['guest']), "Scenario 3 Failed: Missing guest object");
    assert(isset($data3['summary']), "Scenario 3 Failed: Missing summary object");
    assert(isset($data3['booking_history']), "Scenario 3 Failed: Missing booking_history object");
    assert($data3['guest']['id'] === $tiruneshId, "Scenario 3 Failed: Guest ID mismatch");
    $passedTests++;
    echo "  -> PASSED: Guest dossier retrieved with complete profile, summary & stay itinerary.\n\n";

    // -------------------------------------------------------------
    // Scenario 4: Search guests by name
    // -------------------------------------------------------------
    echo "[Scenario 4] Search guests by name...\n";
    $req4 = Request::create('/api/v1/guests', 'GET', ['search' => 'Tirunesh']);
    $res4 = $guestController->index($req4);
    $data4 = json_decode($res4->getContent(), true);
    assert($res4->getStatusCode() === 200, "Scenario 4 Failed: Status not 200");
    assert(count($data4['guests']) >= 1, "Scenario 4 Failed: No results for name 'Tirunesh'");
    assert(str_contains($data4['guests'][0]['full_name'], 'Tirunesh'), "Scenario 4 Failed: Name mismatch");
    $passedTests++;
    echo "  -> PASSED: Found " . count($data4['guests']) . " guest(s) matching name 'Tirunesh'.\n\n";

    // -------------------------------------------------------------
    // Scenario 5: Search guests by phone number
    // -------------------------------------------------------------
    echo "[Scenario 5] Search guests by phone number...\n";
    $req5 = Request::create('/api/v1/guests', 'GET', ['search' => '+251 911 998878']);
    $res5 = $guestController->index($req5);
    $data5 = json_decode($res5->getContent(), true);
    assert($res5->getStatusCode() === 200, "Scenario 5 Failed: Status not 200");
    assert(count($data5['guests']) >= 1, "Scenario 5 Failed: No results for phone");
    assert($data5['guests'][0]['phone'] === '+251 911 998878', "Scenario 5 Failed: Phone mismatch");
    $passedTests++;
    echo "  -> PASSED: Located guest by phone number '+251 911 998878'.\n\n";

    // -------------------------------------------------------------
    // Scenario 6: Search guests by email
    // -------------------------------------------------------------
    echo "[Scenario 6] Search guests by email address...\n";
    $req6 = Request::create('/api/v1/guests', 'GET', ['search' => 'tirunesh.test@hotel.et']);
    $res6 = $guestController->index($req6);
    $data6 = json_decode($res6->getContent(), true);
    assert($res6->getStatusCode() === 200, "Scenario 6 Failed: Status not 200");
    assert(count($data6['guests']) >= 1, "Scenario 6 Failed: No results for email");
    assert($data6['guests'][0]['email'] === 'tirunesh.test@hotel.et', "Scenario 6 Failed: Email mismatch");
    $passedTests++;
    echo "  -> PASSED: Located guest by email 'tirunesh.test@hotel.et'.\n\n";

    // -------------------------------------------------------------
    // Scenario 7: Prevent accidental duplicate guest registration
    // -------------------------------------------------------------
    echo "[Scenario 7] Prevent accidental duplicate guest registration...\n";
    // Attempt 1: Duplicate email
    $req7Email = Request::create('/api/v1/guests', 'POST', [
        'first_name' => 'Duplicate',
        'last_name' => 'Guest',
        'email' => 'tirunesh.test@hotel.et', // duplicate email
        'phone' => '+251 944 112233',
    ]);
    $res7Email = $guestController->store($req7Email);
    assert($res7Email->getStatusCode() === 409, "Scenario 7 Failed: Expected 409 Conflict for duplicate email, got " . $res7Email->getStatusCode());

    // Attempt 2: Duplicate phone
    $req7Phone = Request::create('/api/v1/guests', 'POST', [
        'first_name' => 'Duplicate',
        'last_name' => 'Guest',
        'email' => 'other.guest@hotel.et',
        'phone' => '+251 911 998878', // duplicate phone
    ]);
    $res7Phone = $guestController->store($req7Phone);
    assert($res7Phone->getStatusCode() === 409, "Scenario 7 Failed: Expected 409 Conflict for duplicate phone, got " . $res7Phone->getStatusCode());

    // Attempt 3: Duplicate ID number
    $req7Id = Request::create('/api/v1/guests', 'POST', [
        'first_name' => 'Duplicate',
        'last_name' => 'Guest',
        'email' => 'new.guest@hotel.et',
        'phone' => '+251 944 556677',
        'id_number' => 'ET-PASS-990011', // duplicate passport number
    ]);
    $res7Id = $guestController->store($req7Id);
    assert($res7Id->getStatusCode() === 409, "Scenario 7 Failed: Expected 409 Conflict for duplicate ID number, got " . $res7Id->getStatusCode());

    $passedTests++;
    echo "  -> PASSED: Prevented duplicate registration across email, phone & passport number with HTTP 409.\n\n";

    // -------------------------------------------------------------
    // Scenario 8: View guest booking history
    // -------------------------------------------------------------
    echo "[Scenario 8] View guest booking history after creating reservation...\n";
    $testRoom = Room::where('is_active', 1)->firstOrFail();
    $req8 = Request::create('/api/v1/bookings', 'POST', [
        'guest_id' => $tiruneshId,
        'room_id' => $testRoom->id,
        'check_in_date' => '2027-08-01',
        'check_out_date' => '2027-08-04',
        'adults' => 2,
        'special_requests' => 'Quiet corner suite requested',
    ]);
    $res8 = $bookingController->store($req8);
    assert($res8->getStatusCode() === 201, "Scenario 8 Failed: Booking creation failed: " . $res8->getStatusCode());
    $booking1Id = json_decode($res8->getContent(), true)['booking']['id'];
    $ref1 = json_decode($res8->getContent(), true)['booking']['reference_code'];

    // Check history in guest profile
    $req8Hist = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $res8Hist = $guestController->show($req8Hist, $tiruneshId);
    $data8Hist = json_decode($res8Hist->getContent(), true);
    assert(count($data8Hist['booking_history']['all']) >= 1, "Scenario 8 Failed: Booking history empty");
    assert($data8Hist['booking_history']['all'][0]['reference_code'] === $ref1, "Scenario 8 Failed: Reference code mismatch");
    $passedTests++;
    echo "  -> PASSED: Reservation {$ref1} is accurately recorded in Tirunesh's booking history.\n\n";

    // -------------------------------------------------------------
    // Scenario 9: Identify returning guests
    // -------------------------------------------------------------
    echo "[Scenario 9] Identify returning guests (repeat visitors)...\n";
    // Create a 2nd reservation for Tirunesh
    $testRoom2 = Room::where('is_active', 1)->skip(1)->firstOrFail();
    $req9 = Request::create('/api/v1/bookings', 'POST', [
        'guest_id' => $tiruneshId,
        'room_id' => $testRoom2->id,
        'check_in_date' => '2027-09-10',
        'check_out_date' => '2027-09-13',
        'adults' => 1,
    ]);
    $res9 = $bookingController->store($req9);
    $booking2Id = json_decode($res9->getContent(), true)['booking']['id'];

    // Verify guest is recognized as returning
    $req9Prof = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $res9Prof = $guestController->show($req9Prof, $tiruneshId);
    $data9Prof = json_decode($res9Prof->getContent(), true);
    assert($data9Prof['summary']['is_returning'] === true, "Scenario 9 Failed: Expected is_returning = true");
    assert($data9Prof['summary']['total_reservations'] >= 2, "Scenario 9 Failed: Total reservations should be >= 2");

    // Filter returning guests in directory
    $req9Filter = Request::create('/api/v1/guests', 'GET', ['returning' => 'returning']);
    $res9Filter = $guestController->index($req9Filter);
    $data9Filter = json_decode($res9Filter->getContent(), true);
    $foundTirunesh = collect($data9Filter['guests'])->firstWhere('id', $tiruneshId);
    assert($foundTirunesh !== null, "Scenario 9 Failed: Returning guest filter did not include Tirunesh");
    $passedTests++;
    echo "  -> PASSED: Recognized as Returning Guest with 2 reservations; successfully filtered in CRM directory.\n\n";

    // -------------------------------------------------------------
    // Scenario 10: Add and update guest preferences
    // -------------------------------------------------------------
    echo "[Scenario 10] Add and update permanent guest preferences...\n";
    $updatedPrefs = [
        'smoking' => false,
        'bed_type' => 'king',
        'floor_preference' => 'high',
        'dietary' => 'Strictly Fasting Vegan / Organic Fruits',
        'accessibility_needs' => true,
    ];
    $req10 = Request::create("/api/v1/guests/{$tiruneshId}", 'PUT', [
        'first_name' => 'Tirunesh',
        'last_name' => 'Dibaba',
        'email' => 'tirunesh.test@hotel.et',
        'phone' => '+251 911 998878',
        'preferences' => $updatedPrefs,
    ]);
    $res10 = $guestController->update($req10, $tiruneshId);
    $data10 = json_decode($res10->getContent(), true);
    assert($res10->getStatusCode() === 200, "Scenario 10 Failed: Preference update failed");
    assert($data10['guest']['preferences']['dietary'] === 'Strictly Fasting Vegan / Organic Fruits', "Scenario 10 Failed: Dietary preference mismatch");
    assert($data10['guest']['preferences']['accessibility_needs'] === true, "Scenario 10 Failed: Accessibility preference mismatch");
    $passedTests++;
    echo "  -> PASSED: Permanent guest preferences updated and stored in JSON matrix.\n\n";

    // -------------------------------------------------------------
    // Scenario 11: Associate special requests with reservations
    // -------------------------------------------------------------
    echo "[Scenario 11] Associate booking-specific requests vs permanent preferences...\n";
    // Check booking1 notes
    $req11 = Request::create("/api/v1/bookings/{$booking1Id}", 'GET');
    $res11 = $bookingController->show($booking1Id);
    $data11 = json_decode($res11->getContent(), true);
    assert($data11['booking']['special_requests'] === 'Quiet corner suite requested', "Scenario 11 Failed: Booking special requests lost");
    // Verify permanent preferences remain distinct in guest profile
    $req11Prof = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $res11Prof = $guestController->show($req11Prof, $tiruneshId);
    $data11Prof = json_decode($res11Prof->getContent(), true);
    assert($data11Prof['guest']['preferences']['bed_type'] === 'king', "Scenario 11 Failed: Permanent preferences corrupted");
    $passedTests++;
    echo "  -> PASSED: Booking-specific special requests accurately separated from permanent standing preferences.\n\n";

    // -------------------------------------------------------------
    // Scenario 12: Verify guest information appears correctly during reservation creation
    // -------------------------------------------------------------
    echo "[Scenario 12] Real-time returning guest detection during reservation creation...\n";
    $req12 = Request::create('/api/v1/guests/check-duplicate', 'GET', [
        'phone' => '+251 911 998878',
    ]);
    $res12 = $guestController->checkDuplicate($req12);
    $data12 = json_decode($res12->getContent(), true);
    assert($data12['is_duplicate'] === true, "Scenario 12 Failed: Duplicate check failed to detect returning guest");
    assert($data12['matches'][0]['id'] === $tiruneshId, "Scenario 12 Failed: Matched guest ID mismatch");
    $passedTests++;
    echo "  -> PASSED: Returning guest check successfully located existing profile for front desk staff.\n\n";

    // -------------------------------------------------------------
    // Scenario 13: Verify guest information appears correctly during check-in
    // -------------------------------------------------------------
    echo "[Scenario 13] Verify guest info & identification during check-in...\n";
    Booking::find($booking1Id)->update(['booking_status' => 'confirmed']);
    $req13List = Request::create('/api/v1/bookings/check-in-eligible', 'GET');
    $res13List = $bookingController->checkInEligible();
    $data13List = json_decode($res13List->getContent(), true);
    $arrivalEntry = collect($data13List['eligible_arrivals'])->firstWhere('id', $booking1Id);
    assert($arrivalEntry !== null, "Scenario 13 Failed: Arrival entry for booking not found in checkInEligible");
    assert($arrivalEntry['guest_name'] === 'Tirunesh Dibaba', "Scenario 13 Failed: Guest name mismatch");
    assert($arrivalEntry['vip_status'] === 'vvip', "Scenario 13 Failed: VIP tier mismatch in check-in desk");

    // Perform check-in with ID verification
    $testRoom->update(['status' => 'available', 'housekeeping_status' => 'inspected']);
    $req13CI = Request::create("/api/v1/bookings/{$booking1Id}/check-in", 'POST', [
        'id_type' => 'passport',
        'id_number' => 'ET-PASS-990011',
        'nationality' => 'Ethiopian',
    ]);
    $res13CI = $bookingController->checkIn($req13CI, $booking1Id);
    assert($res13CI->getStatusCode() === 200, "Scenario 13 Failed: Check-in failed: " . $res13CI->getStatusCode());
    $testRoom->refresh();
    assert($testRoom->status === 'occupied', "Scenario 13 Failed: Room status expected 'occupied', got {$testRoom->status}");
    $passedTests++;
    echo "  -> PASSED: Guest details & VIP status verified at check-in; room transitioned to 'occupied'.\n\n";

    // -------------------------------------------------------------
    // Scenario 14: Verify role-based access to sensitive guest information
    // -------------------------------------------------------------
    echo "[Scenario 14] Verify role-based access & ID masking...\n";
    // Unauthorized / Guest request (Header X-User-Role: guest)
    $req14Guest = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $req14Guest->headers->set('X-User-Role', 'guest');
    $res14Guest = $guestController->show($req14Guest, $tiruneshId);
    $data14Guest = json_decode($res14Guest->getContent(), true);
    assert(str_ends_with($data14Guest['guest']['id_number'], '0011') && str_starts_with($data14Guest['guest']['id_number'], '***'), "Scenario 14 Failed: Sensitive ID number was not masked for unauthorized caller (Got: {$data14Guest['guest']['id_number']})");

    // Authorized Staff request (Header X-User-Role: receptionist)
    $req14Staff = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $req14Staff->headers->set('X-User-Role', 'receptionist');
    $res14Staff = $guestController->show($req14Staff, $tiruneshId);
    $data14Staff = json_decode($res14Staff->getContent(), true);
    assert($data14Staff['guest']['id_number'] === 'ET-PASS-990011', "Scenario 14 Failed: Sensitive ID number was not revealed to authorized staff");
    $passedTests++;
    echo "  -> PASSED: Role-based privacy verified: Masked for public/guest (******0011) vs Unmasked for Receptionist (ET-PASS-990011).\n\n";

    // -------------------------------------------------------------
    // Scenario 15: Verify guest history remains available after reservation cancellation
    // -------------------------------------------------------------
    echo "[Scenario 15] Verify history retention after cancellation & prevent hard-delete...\n";
    // Cancel booking2
    $req15Cancel = Request::create("/api/v1/bookings/{$booking2Id}/cancel", 'POST', [
        'cancellation_reason' => 'Schedule changed due to athletic training camp.',
    ]);
    $res15Cancel = $bookingController->cancel($req15Cancel, $booking2Id);
    assert($res15Cancel->getStatusCode() === 200, "Scenario 15 Failed: Cancel failed");

    // Check that cancelled stay appears in guest profile
    $req15Prof = Request::create("/api/v1/guests/{$tiruneshId}", 'GET');
    $res15Prof = $guestController->show($req15Prof, $tiruneshId);
    $data15Prof = json_decode($res15Prof->getContent(), true);
    assert(count($data15Prof['booking_history']['cancelled']) >= 1, "Scenario 15 Failed: Cancelled stays missing in profile");
    assert($data15Prof['booking_history']['cancelled'][0]['cancellation_reason'] === 'Schedule changed due to athletic training camp.', "Scenario 15 Failed: Reason lost");

    // Attempt to delete guest with historical records (Must soft-deactivate, NOT hard-delete)
    $req15Del = Request::create("/api/v1/guests/{$tiruneshId}", 'DELETE');
    $res15Del = $guestController->destroy($req15Del, $tiruneshId);
    $data15Del = json_decode($res15Del->getContent(), true);
    assert($res15Del->getStatusCode() === 200, "Scenario 15 Failed: Destroy endpoint failed");
    assert($data15Del['action'] === 'deactivated', "Scenario 15 Failed: Expected 'deactivated' action, got " . $data15Del['action']);
    
    // Verify guest still exists in DB with is_active = 0
    $guestInDb = User::find($tiruneshId);
    assert($guestInDb !== null, "Scenario 15 Failed: Guest was permanently deleted instead of deactivated");
    assert($guestInDb->is_active == 0, "Scenario 15 Failed: is_active flag not set to 0");

    $passedTests++;
    echo "  -> PASSED: Cancelled stay preserved in history; deletion safely soft-deactivated profile without ledger loss.\n\n";

    // -------------------------------------------------------------
    // Cleanup generated test data
    // -------------------------------------------------------------
    echo "Cleaning up transient test bookings...\n";
    cleanupGuestAndBookings('tirunesh.test@hotel.et');
    $testRoom->update(['status' => 'available']);
    $testRoom2->update(['status' => 'available']);

    echo "\n====================================================================\n";
    echo "   TEST SUMMARY: {$passedTests} / {$totalTests} SCENARIOS PASSED (100% SUCCESS)\n";
    echo "====================================================================\n";

} catch (\Throwable $e) {
    echo "\n!!! TEST FAILURE AT SCENARIO " . ($passedTests + 1) . " !!!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
    exit(1);
}
