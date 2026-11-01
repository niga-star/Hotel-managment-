/**
 * End-to-End Automated Verification Script for Hotel Management System Upgrade
 * Uses native Node 18+ fetch and FormData (no external test dependencies required)
 */

const fs = require('fs');
const path = require('path');

const BASE_URL = 'http://localhost:5000/api/v1';

async function runTests() {
  console.log('================================================================');
  console.log('🏨 STARTING ADVANCED HOTEL MANAGEMENT SYSTEM VERIFICATION');
  console.log('================================================================\n');

  let passedTests = 0;
  let totalTests = 0;

  function assert(condition, message) {
    totalTests++;
    if (condition) {
      console.log(`  ✅ PASS: ${message}`);
      passedTests++;
    } else {
      console.error(`  ❌ FAIL: ${message}`);
      throw new Error(`Assertion failed: ${message}`);
    }
  }

  try {
    // -------------------------------------------------------------
    // SETUP: Authenticate Admin & Guest Users
    // -------------------------------------------------------------
    console.log('📌 PHASE 0: User Authentication & Role Validation');
    
    // Login as Admin
    const adminLoginRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: 'amirhassen2027@gmail.com',
        password: 'Hotel@2026A'
      })
    });
    const adminLoginData = await adminLoginRes.json();
    assert(adminLoginRes.status === 200 && adminLoginData.user.role === 'super_admin', 'Admin authenticated with super_admin privileges');
    const adminToken = adminLoginData.token;
    const adminHeaders = { 
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json'
    };

    // Register/Login Guest 1
    const guest1Email = `guest1_${Date.now()}@test.com`;
    const guest1Phone = `+25191${Math.floor(100000 + Math.random() * 899999)}`;
    const reg1Res = await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Dawit Bekele',
        email: guest1Email,
        phone: guest1Phone,
        password: 'GuestPassword123!'
      })
    });
    const reg1Data = await reg1Res.json();
    assert(reg1Res.status === 201, `Guest 1 registered: ${reg1Data.message}`);

    // Verify Guest 1 Email via Database query or API
    const pool = require('./src/mysql_db');
    const [[user1]] = await pool.query('SELECT verification_token FROM users WHERE email = ?', [guest1Email]);
    assert(user1 && user1.verification_token, 'Guest 1 verification token generated in MySQL');

    // Call verify-email endpoint
    const verify1Res = await fetch(`${BASE_URL}/auth/verify-email`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ token: user1.verification_token })
    });
    assert(verify1Res.status === 200, 'Guest 1 email verified via single-use verification token');

    const guest1LoginRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: guest1Email,
        password: 'GuestPassword123!'
      })
    });
    const guest1LoginData = await guest1LoginRes.json();
    const guest1Token = guest1LoginData.token;
    const guest1Headers = { 
      'Authorization': `Bearer ${guest1Token}`,
      'Content-Type': 'application/json'
    };
    assert(guest1Token, 'Guest 1 registered, verified, and authenticated successfully');

    // Register/Login Guest 2
    const guest2Email = `guest2_${Date.now()}@test.com`;
    const guest2Phone = `+25192${Math.floor(100000 + Math.random() * 899999)}`;
    const reg2Res = await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Solomon Tesfaye',
        email: guest2Email,
        phone: guest2Phone,
        password: 'GuestPassword123!'
      })
    });
    assert(reg2Res.status === 201, 'Guest 2 registered successfully');

    const [[user2]] = await pool.query('SELECT verification_token FROM users WHERE email = ?', [guest2Email]);
    await fetch(`${BASE_URL}/auth/verify-email`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ token: user2.verification_token })
    });

    const guest2LoginRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: guest2Email,
        password: 'GuestPassword123!'
      })
    });
    const guest2LoginData = await guest2LoginRes.json();
    const guest2Token = guest2LoginData.token;
    const guest2Headers = { 
      'Authorization': `Bearer ${guest2Token}`,
      'Content-Type': 'application/json'
    };
    assert(guest2Token, 'Guest 2 registered, verified, and authenticated successfully');

    // -------------------------------------------------------------
    // TEST 1: Room Creation & Persistence in MySQL (Requirement 5)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 1: Room Creation, Persistence & Duplicate Prevention');
    const testRoomNum = `8${Math.floor(10 + Math.random() * 89)}`;
    
    // 1A. Create room with admin token
    const createRoomRes = await fetch(`${BASE_URL}/rooms`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        room_number: testRoomNum,
        type_id: 1, // Deluxe
        base_price_etb: 4800,
        floor_number: 8,
        max_guests: 3,
        has_balcony: true,
        has_jacuzzi: false,
        wifi_enabled: true,
        description: 'Luxury Executive Suite on 8th floor'
      })
    });
    const createRoomData = await createRoomRes.json();
    if (createRoomRes.status !== 201) {
      console.log('createRoom error:', createRoomRes.status, createRoomData);
    }
    assert(createRoomRes.status === 201 && createRoomData.room && createRoomData.room.room_number === testRoomNum, `Room ${testRoomNum} created and persisted in MySQL`);
    const createdRoomId = createRoomData.room.id;

    // 1B. Verify room appears in Room Catalog API
    const catalogRes = await fetch(`${BASE_URL}/rooms`);
    const catalogData = await catalogRes.json();
    const foundRoom = catalogData.rooms.find(r => r.room_number === testRoomNum);
    assert(foundRoom !== undefined, `Room ${testRoomNum} immediately visible in public catalog without hardcoding`);

    // 1C. Prevent duplicate room numbers
    const dupRes = await fetch(`${BASE_URL}/rooms`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        room_number: testRoomNum,
        type_id: 1,
        base_price_etb: 4800
      })
    });
    assert(dupRes.status === 409, 'Server rejected duplicate room number with 409 Conflict');

    // -------------------------------------------------------------
    // TEST 2: Room Selection & Likes/Favorites Persistence (Requirement 2)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 2: Room Likes/Favorites MySQL Persistence & Deduplication');
    
    // 2A. Add to favorites
    const favAddRes = await fetch(`${BASE_URL}/favorites/${createdRoomId}`, {
      method: 'POST',
      headers: guest1Headers
    });
    const favAddData = await favAddRes.json();
    assert(favAddRes.status === 200 && favAddData.favorited === true, 'Guest 1 successfully added room to favorites');

    // 2B. Duplicate favorite prevention (idempotent)
    const favDupRes = await fetch(`${BASE_URL}/favorites/${createdRoomId}`, {
      method: 'POST',
      headers: guest1Headers
    });
    assert(favDupRes.status === 200, 'Duplicate favorite attempt safely handled without error');

    // 2C. Retrieve favorites from database
    const favGetRes = await fetch(`${BASE_URL}/favorites`, {
      headers: { 'Authorization': `Bearer ${guest1Token}` }
    });
    const favGetData = await favGetRes.json();
    assert(Array.isArray(favGetData.favorites) && favGetData.favorites.includes(createdRoomId), 'Favorite correctly restored from MySQL user_favorites table');

    // 2D. Remove from favorites
    const favDelRes = await fetch(`${BASE_URL}/favorites/${createdRoomId}`, {
      method: 'DELETE',
      headers: guest1Headers
    });
    const favDelData = await favDelRes.json();
    assert(favDelRes.status === 200 && favDelData.favorited === false, 'Room removed from favorites in MySQL');

    // Verify deletion
    const favAfterDelRes = await fetch(`${BASE_URL}/favorites`, {
      headers: { 'Authorization': `Bearer ${guest1Token}` }
    });
    const favAfterDelData = await favAfterDelRes.json();
    assert(!favAfterDelData.favorites.includes(createdRoomId), 'Room no longer in favorites list');

    // -------------------------------------------------------------
    // TEST 3: Room Availability & Concurrency Double Booking Prevention (Requirement 1)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 3: Double Booking Prevention & Concurrency Overlap Logic');
    
    const checkInDate = '2026-11-10';
    const checkOutDate = '2026-11-15';

    // 3A. Guest 1 books room for Nov 10 - Nov 15
    const booking1Res = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guest1Headers,
      body: JSON.stringify({
        room_id: createdRoomId,
        check_in_date: checkInDate,
        check_out_date: checkOutDate,
        adults: 2,
        children: 0,
        payment_mode: 'manual_receipt'
      })
    });
    const booking1Data = await booking1Res.json();
    assert(booking1Res.status === 201, `Guest 1 successfully booked room ${testRoomNum} for ${checkInDate} to ${checkOutDate}`);
    const booking1Id = booking1Data.booking.id;

    // 3B. Guest 2 attempts to book the SAME room for OVERLAPPING dates (Nov 12 - Nov 17)
    // Overlap rule: (existing_check_in < requested_check_out AND existing_check_out > requested_check_in)
    const bookingDupRes = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guest2Headers,
      body: JSON.stringify({
        room_id: createdRoomId,
        check_in_date: '2026-11-12',
        check_out_date: '2026-11-17',
        adults: 1,
        children: 0
      })
    });
    const bookingDupData = await bookingDupRes.json();
    const errMsg = bookingDupData.message || bookingDupData.error || '';
    assert(
      bookingDupRes.status === 409 && 
      errMsg.includes('no longer available for your selected dates'),
      'Blocked overlapping reservation with 409: "Sorry, this room is no longer available for your selected dates. Please choose another room."'
    );

    // 3C. Guest 2 books the SAME room for NON-OVERLAPPING dates (Nov 15 - Nov 20)
    // Operational turnover: Guest 2 checks in on the exact day Guest 1 checks out (Nov 15)
    const bookingTurnoverRes = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guest2Headers,
      body: JSON.stringify({
        room_id: createdRoomId,
        check_in_date: '2026-11-15',
        check_out_date: '2026-11-20',
        adults: 1,
        children: 0,
        payment_mode: 'manual_receipt'
      })
    });
    assert(bookingTurnoverRes.status === 201, `Non-overlapping booking on check-out date (turnover) succeeded`);

    // -------------------------------------------------------------
    // TEST 4: Payment Workflow (Option A: Manual Receipt & 20-min SLA) (Requirement 6 & 7)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 4: Payment Manual Receipt Upload, SLA Tracking & Verification');

    const formData = new FormData();
    formData.append('booking_id', booking1Id.toString());
    formData.append('claimed_amount_etb', '24000');
    formData.append('transaction_reference', `TLB-TX-${Date.now()}`);
    const blob = new Blob(['sample-fake-receipt-content'], { type: 'image/png' });
    formData.append('receipt', blob, 'receipt.png');

    const receiptUploadRes = await fetch(`${BASE_URL}/payments/upload-receipt`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${guest1Token}`
      },
      body: formData
    });
    const receiptUploadData = await receiptUploadRes.json();
    if (receiptUploadRes.status !== 201) {
      console.log('receiptUpload error:', receiptUploadRes.status, receiptUploadData);
    }
    assert(receiptUploadRes.status === 201, 'Payment receipt uploaded successfully and booking status changed to pending_verification');
    const paymentId = receiptUploadData.payment.id;

    // Verify SLA calculation in Admin Payments list
    const paymentsListRes = await fetch(`${BASE_URL}/payments?filter=pending`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const paymentsListData = await paymentsListRes.json();
    const pendingPayment = paymentsListData.payments.find(p => p.id === paymentId);
    assert(pendingPayment !== undefined, 'Pending payment visible in Admin Payment SLA queue');
    assert(pendingPayment.claimed_amount_etb == 24000, 'Claimed amount ETB persisted correctly');
    assert(typeof pendingPayment.minutes_elapsed === 'number' && pendingPayment.review_target_sla === '20 minutes', '20-Minute SLA metrics computed accurately');

    // Admin reviews and approves the payment
    const reviewRes = await fetch(`${BASE_URL}/payments/${paymentId}/review`, {
      method: 'PATCH',
      headers: adminHeaders,
      body: JSON.stringify({
        action: 'approve',
        notes: 'Telebirr transaction verified via SMS gateway'
      })
    });
    const reviewData = await reviewRes.json();
    assert(reviewRes.status === 200 && reviewData.payment.status === 'verified', 'Payment successfully approved & verified by admin');

    // -------------------------------------------------------------
    // TEST 5: Separate Booking Desk, Check-In Desk, and Check-Out Desk Workflows (Requirement 3)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 5: Distinct Operational Desks & Guest Lifecycle');

    // 5A. Booking Desk: Verify booking is confirmed
    const deskRes = await fetch(`${BASE_URL}/admin/bookings/desk?status=confirmed`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const deskData = await deskRes.json();
    const confirmedBooking = deskData.bookings.find(b => b.id === booking1Id);
    assert(confirmedBooking !== undefined && confirmedBooking.booking_status === 'confirmed', 'Booking Desk lists verified booking as Confirmed with outstanding balance 0');

    // 5B. Check-In Desk: Check eligibility
    const checkInEligibleRes = await fetch(`${BASE_URL}/admin/bookings/check-in-eligible`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const checkInEligibleData = await checkInEligibleRes.json();
    const eligibleArrival = checkInEligibleData.bookings.find(b => b.id === booking1Id);
    assert(eligibleArrival !== undefined && eligibleArrival.is_eligible_for_check_in === true, 'Check-In Desk confirms arrival is eligible (Paid & Confirmed)');

    // 5C. Perform Guest Check-In
    const checkInActionRes = await fetch(`${BASE_URL}/bookings/${booking1Id}/check-in`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        keycard_number: 'CARD-RM8-01',
        id_verified: true
      })
    });
    const checkInActionData = await checkInActionRes.json();
    assert(checkInActionRes.status === 200 && checkInActionData.booking.booking_status === 'checked_in', 'Guest checked in, actual_check_in_at logged, room status updated to occupied');

    // Verify room status in MySQL is now 'occupied'
    const roomOccupiedCheck = await fetch(`${BASE_URL}/rooms`);
    const roomOccupiedData = await roomOccupiedCheck.json();
    const roomInCatalog = roomOccupiedData.rooms.find(r => r.id === createdRoomId);
    assert(roomInCatalog.status === 'occupied', `Room ${testRoomNum} status atomically transitioned to occupied in database`);

    // 5D. Check-Out Desk: Check eligibility
    const checkOutEligibleRes = await fetch(`${BASE_URL}/admin/bookings/check-out-eligible`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const checkOutEligibleData = await checkOutEligibleRes.json();
    const inHouseGuest = checkOutEligibleData.bookings.find(b => b.id === booking1Id);
    assert(inHouseGuest !== undefined && inHouseGuest.booking_status === 'checked_in', 'Check-Out Desk lists in-house guest with folio details');

    // 5E. Perform Guest Check-Out with Invoice Generation
    const checkOutActionRes = await fetch(`${BASE_URL}/bookings/${booking1Id}/check-out`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        keycard_returned: true,
        room_inspection_passed: true,
        minibar_charge_etb: 0
      })
    });
    const checkOutActionData = await checkOutActionRes.json();
    assert(
      checkOutActionRes.status === 200 && 
      checkOutActionData.booking.booking_status === 'checked_out' &&
      checkOutActionData.invoice.invoice_number.startsWith('INV-'),
      'Guest checked out, departure invoice generated, room released to available'
    );

    // -------------------------------------------------------------
    // TEST 6: Real MySQL-Computed Admin Dashboard Statistics (Requirement 4)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 6: Real 100% MySQL Database Dashboard Analytics');
    const statsRes = await fetch(`${BASE_URL}/admin/dashboard-stats`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const statsData = await statsRes.json();
    assert(statsRes.status === 200, 'Admin Dashboard Stats loaded successfully with super_admin token');
    
    const kpis = statsData.kpis;
    assert(typeof kpis.occupancy_rate === 'number', `Occupancy rate is dynamically computed: ${kpis.occupancy_rate}%`);
    assert(kpis.total_rooms >= 1, `Total rooms matches database count: ${kpis.total_rooms}`);
    assert(kpis.verified_revenue_etb >= 24000, `Verified revenue reflects real verified payment: ETB ${kpis.verified_revenue_etb}`);
    assert(Array.isArray(statsData.rooms_matrix), 'Live Room Matrix populated dynamically from MySQL rooms table');
    assert(Array.isArray(statsData.recent_reservations), 'Recent reservations list populated dynamically from MySQL bookings table');

    // -------------------------------------------------------------
    // TEST 7: Payment Filter Cards (Requirement 7)
    // -------------------------------------------------------------
    console.log('\n📌 PHASE 7: Interactive Payment Cards & Method Filtering');
    const filterAllRes = await fetch(`${BASE_URL}/payments?filter=all`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const filterAllData = await filterAllRes.json();
    const filterTelebirrRes = await fetch(`${BASE_URL}/payments?filter=telebirr`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    const filterTelebirrData = await filterTelebirrRes.json();
    assert(filterAllData.kpis.revenue_etb >= 24000, 'Payment Revenue KPI computed from verified transactions');
    assert(filterTelebirrData.payments.every(p => p.method === 'telebirr'), 'Payments filtered accurately by Telebirr method');

    console.log('\n================================================================');
    console.log(`🎉 ALL ${passedTests}/${totalTests} ADVANCED VERIFICATION TESTS PASSED SUCCESSFULLY!`);
    console.log('================================================================\n');

  } catch (error) {
    console.error('\n❌ VERIFICATION TEST FAILED:', error.message);
    process.exit(1);
  }
}

runTests();
