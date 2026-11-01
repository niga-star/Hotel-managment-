/**
 * Comprehensive Automated Verification Suite for:
 * 1. Room Availability After Payment Rejection
 * 2. 6-Digit Email Verification for New Guest Registration
 */

const pool = require('./src/mysql_db');

const BASE_URL = 'http://localhost:5000/api/v1';

async function runVerification() {
  console.log('================================================================');
  console.log('🏨 STARTING VERIFICATION: PAYMENT REJECTION & EMAIL VERIFICATION');
  console.log('================================================================\n');

  let passed = 0;
  let total = 0;

  function assert(condition, message) {
    total++;
    if (condition) {
      console.log(`  ✅ PASS: ${message}`);
      passed++;
    } else {
      console.error(`  ❌ FAIL: ${message}`);
      throw new Error(`Assertion failed: ${message}`);
    }
  }

  try {
    // -------------------------------------------------------------
    // PHASE 0: Authenticate Super Admin
    // -------------------------------------------------------------
    console.log('📌 SETUP: Admin Authentication');
    const adminLoginRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: 'amirhassen2027@gmail.com',
        password: 'Hotel@2026A'
      })
    });
    const adminData = await adminLoginRes.json();
    assert(adminLoginRes.status === 200 && adminData.user.role === 'super_admin', 'Admin authenticated with super_admin privileges');
    const adminToken = adminData.token;
    const adminHeaders = { 
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json'
    };

    // -------------------------------------------------------------
    // PART 1: Room Availability After Payment Rejection
    // -------------------------------------------------------------
    console.log('\n================================================================');
    console.log('📌 PART 1: ROOM AVAILABILITY AFTER PAYMENT REJECTION');
    console.log('================================================================');

    // 1A. Create a fresh dedicated room for this test
    const testRoomNumber = `9${Math.floor(10 + Math.random() * 89)}`;
    const createRoomRes = await fetch(`${BASE_URL}/rooms`, {
      method: 'POST',
      headers: adminHeaders,
      body: JSON.stringify({
        room_number: testRoomNumber,
        type_id: 'cat-deluxe',
        price_etb: 5200,
        floor: 9,
        capacity: 2,
        title: `Luxury Sky Suite ${testRoomNumber}`,
        description: 'Penthouse view suite for rejection workflow test'
      })
    });
    const createRoomData = await createRoomRes.json();
    assert(createRoomRes.status === 201, `Created dedicated test room ${testRoomNumber} in MySQL`);
    const testRoomId = createRoomData.roomId || createRoomData.room.id;

    // 1B. Register Guest A
    const guestAEmail = `guest_a_${Date.now()}@test.com`;
    const regARes = await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Almaz Tefera',
        email: guestAEmail,
        phone: `+25191${Math.floor(100000 + Math.random() * 899999)}`,
        password: 'Password123!'
      })
    });
    assert(regARes.status === 201, 'Guest A registered successfully');

    // Activate Guest A directly in DB for booking test
    await pool.query('UPDATE users SET is_verified = 1 WHERE email = ?', [guestAEmail]);
    const loginARes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: guestAEmail, password: 'Password123!' })
    });
    const guestAToken = (await loginARes.json()).token;
    const guestAHeaders = { 'Authorization': `Bearer ${guestAToken}`, 'Content-Type': 'application/json' };

    // Register Guest B
    const guestBEmail = `guest_b_${Date.now()}@test.com`;
    await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Binyam Kassaye',
        email: guestBEmail,
        phone: `+25192${Math.floor(100000 + Math.random() * 899999)}`,
        password: 'Password123!'
      })
    });
    await pool.query('UPDATE users SET is_verified = 1 WHERE email = ?', [guestBEmail]);
    const loginBRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: guestBEmail, password: 'Password123!' })
    });
    const guestBToken = (await loginBRes.json()).token;
    const guestBHeaders = { 'Authorization': `Bearer ${guestBToken}`, 'Content-Type': 'application/json' };

    // 1C. Guest A books Room for Nov 20 - Nov 25 (manual receipt)
    const checkIn = '2026-11-20';
    const checkOut = '2026-11-25';
    const bookARes = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guestAHeaders,
      body: JSON.stringify({
        room_id: testRoomId,
        check_in_date: checkIn,
        check_out_date: checkOut,
        adults: 2,
        payment_mode: 'manual_receipt'
      })
    });
    const bookAData = await bookARes.json();
    assert(bookARes.status === 201, `Guest A booked room ${testRoomNumber} (${checkIn} to ${checkOut}), status: pending_verification`);
    const bookingAId = bookAData.booking.id;

    // 1D. Verify room is now RESERVED during review: Guest B cannot book overlapping dates
    const bookBOverlapRes = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guestBHeaders,
      body: JSON.stringify({
        room_id: testRoomId,
        check_in_date: '2026-11-22',
        check_out_date: '2026-11-27',
        adults: 1
      })
    });
    assert(
      bookBOverlapRes.status === 409, 
      'Room is reserved while under review: Overlapping booking by Guest B blocked with HTTP 409'
    );

    // Verify availability endpoint confirms room is unavailable
    const availCheckBefore = await fetch(`${BASE_URL}/rooms/availability?check_in=${checkIn}&check_out=${checkOut}&room_id=${testRoomId}`);
    const availDataBefore = await availCheckBefore.json();
    assert(availDataBefore.available === false, 'Availability endpoint confirms room is unavailable for those dates');

    // 1E. Guest A uploads payment receipt
    const formData = new FormData();
    formData.append('booking_id', bookingAId);
    formData.append('claimed_amount_etb', '26000');
    formData.append('transaction_reference', `TLB-REJ-${Date.now()}`);
    const blob = new Blob(['sample-fake-receipt'], { type: 'image/png' });
    formData.append('receipt', blob, 'receipt.png');

    const uploadReceiptRes = await fetch(`${BASE_URL}/payments/upload-receipt`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${guestAToken}` },
      body: formData
    });
    const uploadReceiptData = await uploadReceiptRes.json();
    assert(uploadReceiptRes.status === 201, 'Guest A uploaded payment receipt successfully');
    const paymentId = uploadReceiptData.payment.id;

    // 1F. Admin REJECTS the payment proof
    const rejectionReason = 'Transaction reference could not be verified on Telebirr Merchant portal.';
    const rejectRes = await fetch(`${BASE_URL}/payments/${paymentId}/review`, {
      method: 'PATCH',
      headers: adminHeaders,
      body: JSON.stringify({
        action: 'reject',
        rejection_reason: rejectionReason
      })
    });
    const rejectData = await rejectRes.json();
    assert(rejectRes.status === 200, `Admin rejected payment: "${rejectData.message}"`);

    // 1G. Verify Database Synchronization
    const [[dbBooking]] = await pool.query('SELECT booking_status, payment_status, notes FROM bookings WHERE id = ?', [bookingAId]);
    assert(dbBooking.booking_status === 'rejected', `Booking status synchronized in MySQL: ${dbBooking.booking_status}`);
    assert(dbBooking.payment_status === 'rejected', `Payment status synchronized in MySQL: ${dbBooking.payment_status}`);

    const [[dbPayment]] = await pool.query('SELECT status, rejection_reason FROM payments WHERE id = ?', [paymentId]);
    assert(dbPayment.status === 'rejected', `Payment record status in MySQL: ${dbPayment.status}`);
    assert(dbPayment.rejection_reason === rejectionReason, 'Rejection reason persisted in payments table');

    const [[dbRoom]] = await pool.query('SELECT status FROM rooms WHERE id = ?', [testRoomId]);
    assert(dbRoom.status === 'available', `Physical room status in MySQL is available: ${dbRoom.status}`);

    // 1H. Verify Room Availability is Released Immediately
    const availCheckAfter = await fetch(`${BASE_URL}/rooms/availability?check_in=${checkIn}&check_out=${checkOut}&room_id=${testRoomId}`);
    const availDataAfter = await availCheckAfter.json();
    assert(availDataAfter.available === true, 'Availability endpoint confirms room is now available for those exact dates');

    const catalogCheckAfter = await fetch(`${BASE_URL}/rooms?check_in=${checkIn}&check_out=${checkOut}`);
    const catalogDataAfter = await catalogCheckAfter.json();
    const catalogRoom = catalogDataAfter.rooms.find(r => r.id === testRoomId);
    assert(catalogRoom && catalogRoom.is_available === true, 'Public room catalog shows is_available: true for those dates');

    // 1I. Guest B immediately books the released room for the EXACT SAME dates
    const bookBSuccessRes = await fetch(`${BASE_URL}/bookings`, {
      method: 'POST',
      headers: guestBHeaders,
      body: JSON.stringify({
        room_id: testRoomId,
        check_in_date: checkIn,
        check_out_date: checkOut,
        adults: 2,
        payment_mode: 'manual_receipt'
      })
    });
    assert(bookBSuccessRes.status === 201, `Guest B successfully booked the released room for ${checkIn} to ${checkOut} (HTTP 201)`);

    // -------------------------------------------------------------
    // PART 2: 6-Digit Email Verification for New Guest Registration
    // -------------------------------------------------------------
    console.log('\n================================================================');
    console.log('📌 PART 2: 6-DIGIT EMAIL VERIFICATION FOR NEW GUEST REGISTRATION');
    console.log('================================================================');

    const newGuestEmail = `verify_guest_${Date.now()}@ethin.com`;
    const newGuestPhone = `+25193${Math.floor(100000 + Math.random() * 899999)}`;
    const newGuestPassword = 'SecurePass2026!';

    // 2A. Register New Guest
    const newGuestRegRes = await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Hanna Yohannes',
        email: newGuestEmail,
        phone: newGuestPhone,
        password: newGuestPassword
      })
    });
    const newGuestRegData = await newGuestRegRes.json();
    assert(newGuestRegRes.status === 201, `New guest registered: "${newGuestRegData.message}"`);
    assert(newGuestRegData.expiresInMinutes === 15, 'Response confirms code expiration is 15 minutes');

    // 2B. Verify Database State
    const [[userRow]] = await pool.query(
      'SELECT is_verified, verification_code, verification_token_expires, verification_attempts FROM users WHERE email = ?',
      [newGuestEmail]
    );
    assert(userRow.is_verified === 0, 'New guest starts with is_verified = 0');
    assert(/^\d{6}$/.test(userRow.verification_code), `Real 6-digit numeric verification code generated in MySQL: ${userRow.verification_code}`);
    assert(userRow.verification_attempts === 0, 'Verification attempts counter initialized to 0');
    assert(userRow.verification_token_expires > new Date(), 'Code expiration timestamp is in the future');

    // 2C. Verify Real SMTP Delivery Logged in MySQL
    const [notifs] = await pool.query(
      'SELECT channel, recipient, subject, status, delivery_reference FROM guest_notifications WHERE recipient = ? ORDER BY sent_at DESC LIMIT 1',
      [newGuestEmail]
    );
    assert(notifs.length > 0, 'Real email notification logged in MySQL guest_notifications table');
    assert(notifs[0].subject.includes(userRow.verification_code), `Notification subject contains verification code: "${notifs[0].subject}"`);
    assert(notifs[0].delivery_reference.length > 0, `Real SMTP delivery reference recorded: ${notifs[0].delivery_reference}`);

    // 2D. Verify Unverified User Cannot Log In
    const unverifiedLoginRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail, password: newGuestPassword })
    });
    const unverifiedLoginData = await unverifiedLoginRes.json();
    assert(
      unverifiedLoginRes.status === 403 && unverifiedLoginData.unverified === true,
      'Unverified user login correctly gated with HTTP 403 (unverified: true)'
    );

    // 2E. Security: Incorrect Code Rejection & Attempt Counter
    const wrongCodeRes = await fetch(`${BASE_URL}/auth/verify-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail, code: '000000' })
    });
    const wrongCodeData = await wrongCodeRes.json();
    assert(wrongCodeRes.status === 400, 'Invalid verification code rejected with HTTP 400');
    
    const [[userAfterWrong]] = await pool.query('SELECT verification_attempts FROM users WHERE email = ?', [newGuestEmail]);
    assert(userAfterWrong.verification_attempts === 1, `Verification attempts counter incremented in MySQL: ${userAfterWrong.verification_attempts}`);

    // 2F. Security: Code Expiration Enforcement
    // Temporarily set expiration to 1 minute ago in MySQL
    await pool.query('UPDATE users SET verification_token_expires = NOW() - INTERVAL 1 MINUTE WHERE email = ?', [newGuestEmail]);
    const expiredRes = await fetch(`${BASE_URL}/auth/verify-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail, code: userRow.verification_code })
    });
    const expiredData = await expiredRes.json();
    assert(expiredRes.status === 400 && expiredData.error.includes('expired'), 'Expired verification code rejected with HTTP 400');

    // 2G. Resend Code Functionality & Cooldown
    // Reset cooldown in DB to simulate cooldown passed
    await pool.query('UPDATE users SET code_last_sent_at = NOW() - INTERVAL 2 MINUTE WHERE email = ?', [newGuestEmail]);
    const resendRes = await fetch(`${BASE_URL}/auth/resend-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail })
    });
    const resendData = await resendRes.json();
    assert(resendRes.status === 200, `Resend code succeeded: "${resendData.message}"`);

    // Verify fresh code in DB
    const [[userAfterResend]] = await pool.query(
      'SELECT verification_code, verification_token_expires, verification_attempts FROM users WHERE email = ?',
      [newGuestEmail]
    );
    const freshCode = userAfterResend.verification_code;
    assert(/^\d{6}$/.test(freshCode), `Fresh 6-digit code generated in MySQL: ${freshCode}`);
    assert(userAfterResend.verification_attempts === 0, 'Verification attempts reset to 0 after resend');
    assert(userAfterResend.verification_token_expires > new Date(), 'New expiration timestamp is valid in future');

    // Test immediate resend fails due to 60-second cooldown
    const cooldownRes = await fetch(`${BASE_URL}/auth/resend-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail })
    });
    assert(cooldownRes.status === 429, 'Immediate second resend rejected by 60s cooldown (HTTP 429)');

    // 2H. Successful Verification via 6-Digit Code
    const verifySuccessRes = await fetch(`${BASE_URL}/auth/verify-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail, code: freshCode })
    });
    const verifySuccessData = await verifySuccessRes.json();
    assert(verifySuccessRes.status === 200 && verifySuccessData.success === true, 'Valid 6-digit code verified successfully (HTTP 200)');
    assert(verifySuccessData.token !== undefined, 'Verification response automatically returns authenticated JWT session token');

    // 2I. Verify Database Activation
    const [[userAfterVerified]] = await pool.query(
      'SELECT is_verified, verification_code, verification_token, verification_token_expires, verification_attempts FROM users WHERE email = ?',
      [newGuestEmail]
    );
    assert(userAfterVerified.is_verified === 1, 'User account is now is_verified = 1 in MySQL');
    assert(userAfterVerified.verification_code === null, 'verification_code cleared from MySQL');
    assert(userAfterVerified.verification_token === null, 'verification_token cleared from MySQL');
    assert(userAfterVerified.verification_attempts === 0, 'verification_attempts reset');

    // 2J. Verify Login Now Succeeds
    const loginSuccessRes = await fetch(`${BASE_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: newGuestEmail, password: newGuestPassword })
    });
    const loginSuccessData = await loginSuccessRes.json();
    assert(loginSuccessRes.status === 200 && loginSuccessData.user.is_verified === true, 'Guest can now log into Ethiopian Boutique Stays with credentials');

    console.log('\n================================================================');
    console.log(`🎉 ALL ${passed}/${total} VERIFICATION TESTS PASSED (100% SUCCESS)!`);
    console.log('================================================================\n');
    process.exit(0);

  } catch (error) {
    console.error('\n❌ VERIFICATION TEST FAILED:', error.message);
    process.exit(1);
  }
}

runVerification();
