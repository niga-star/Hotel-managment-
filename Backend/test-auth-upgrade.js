/**
 * Automated Verification Suite for Authentication Upgrade
 * Tests:
 * 1. Full Name Validation (length, unicode alphabetic, rejection of numbers/symbols)
 * 2. International Phone Validation (E.164, rejection of letters/symbols)
 * 3. Strong Password Validation (8+ chars, upper, lower, number, symbol)
 * 4. User Registration (defaults strictly to role 'guest', is_verified = 0, verification token generated)
 * 5. Email Verification Gate (unverified user login blocked with 403, verification token activation)
 * 6. Admin Account & RBAC Enforcement (amirhassen2027@gmail.com verified, non-admins blocked from admin routes)
 */

const pool = require('./src/mysql_db');

const BASE_URL = 'http://localhost:5000';

async function runTests() {
  console.log('====================================================');
  console.log('🧪 Starting Hotel Authentication & RBAC Test Suite');
  console.log('====================================================\n');

  let passed = 0;
  let failed = 0;

  function assert(condition, testName) {
    if (condition) {
      console.log(`  ✓ [PASS] ${testName}`);
      passed++;
    } else {
      console.error(`  ✗ [FAIL] ${testName}`);
      failed++;
    }
  }

  try {
    // ----------------------------------------------------
    // TEST 1: Full Name Validation
    // ----------------------------------------------------
    console.log('--- Test Group 1: Full Name Validation ---');
    
    // Too short (2 chars)
    let res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Ab',
        phone: '+251 911 234567',
        email: 'test_short_name@hotel.com',
        password: 'Password@123'
      })
    });
    let data = await res.json();
    assert(res.status === 400 && data.error.includes('at least 3 characters'), 'Rejects names shorter than 3 characters ("Ab")');

    // Contains digits ("John123")
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'John123',
        phone: '+251 911 234567',
        email: 'test_num_name@hotel.com',
        password: 'Password@123'
      })
    });
    data = await res.json();
    assert(res.status === 400 && data.error.includes('alphabetic'), 'Rejects names with digits ("John123")');

    // Contains special characters ("John@")
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'John@',
        phone: '+251 911 234567',
        email: 'test_sym_name@hotel.com',
        password: 'Password@123'
      })
    });
    data = await res.json();
    assert(res.status === 400 && data.error.includes('alphabetic'), 'Rejects names with special characters ("John@")');

    // ----------------------------------------------------
    // TEST 2: International Phone Validation
    // ----------------------------------------------------
    console.log('\n--- Test Group 2: International Phone Validation ---');

    // Contains alphabetic letters
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Almaz Ayana',
        phone: '+251 911 PHONE',
        email: 'test_phone_alpha@hotel.com',
        password: 'Password@123'
      })
    });
    data = await res.json();
    assert(res.status === 400, 'Rejects phone numbers containing alphabetic characters');

    // Too short digits
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Almaz Ayana',
        phone: '123',
        email: 'test_phone_short@hotel.com',
        password: 'Password@123'
      })
    });
    data = await res.json();
    assert(res.status === 400, 'Rejects phone numbers with invalid E.164 length');

    // ----------------------------------------------------
    // TEST 3: Strong Password Validation
    // ----------------------------------------------------
    console.log('\n--- Test Group 3: Strong Password Validation ---');

    // Missing uppercase
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Kenenisa Bekele',
        phone: '+251 911 888777',
        email: 'test_pass_upper@hotel.com',
        password: 'password@123'
      })
    });
    data = await res.json();
    assert(res.status === 400 && data.error.includes('uppercase'), 'Rejects passwords missing uppercase letter');

    // Missing special character
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Kenenisa Bekele',
        phone: '+251 911 888777',
        email: 'test_pass_sym@hotel.com',
        password: 'Password123'
      })
    });
    data = await res.json();
    assert(res.status === 400 && data.error.includes('special character'), 'Rejects passwords missing special character');

    // Missing digit
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Kenenisa Bekele',
        phone: '+251 911 888777',
        email: 'test_pass_dig@hotel.com',
        password: 'Password@Special'
      })
    });
    data = await res.json();
    assert(res.status === 400 && data.error.includes('number'), 'Rejects passwords missing numeric digit');

    // ----------------------------------------------------
    // TEST 4: Registration & Role Protection
    // ----------------------------------------------------
    console.log('\n--- Test Group 4: Registration & Role Tamper Prevention ---');

    const testEmail = `guest_test_${Date.now()}@hoteltest.com`;
    const testPhone = `+25191${Math.floor(100000 + Math.random() * 900000)}`;

    // Attacker attempts to register with role = 'super_admin'
    res = await fetch(`${BASE_URL}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name: 'Test Guest Account',
        phone: testPhone,
        email: testEmail,
        password: 'Hotel@2026Password',
        role: 'super_admin' // ATTEMPTED PRIVILEGE ESCALATION
      })
    });
    data = await res.json();
    assert(res.status === 201 && data.success === true, 'Public registration returns 201 Created');

    // Verify in MySQL that role was forced to 'guest' and is_verified is 0
    const [dbRows] = await pool.query('SELECT role, is_verified, verification_token FROM users WHERE email = ?', [testEmail]);
    assert(dbRows.length === 1, 'User record created in MySQL database');
    assert(dbRows[0].role === 'guest', 'Privilege Escalation Blocked: Role defaulted strictly to "guest"');
    assert(dbRows[0].is_verified === 0, 'New user is unverified (is_verified = 0)');
    assert(dbRows[0].verification_token !== null, 'Verification token generated in database');

    const verificationToken = dbRows[0].verification_token;

    // ----------------------------------------------------
    // TEST 5: Email Verification Gate & Activation
    // ----------------------------------------------------
    console.log('\n--- Test Group 5: Email Verification Gate & Login ---');

    // Unverified user login attempt MUST return 403
    res = await fetch(`${BASE_URL}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: testEmail,
        password: 'Hotel@2026Password'
      })
    });
    data = await res.json();
    assert(res.status === 403 && data.unverified === true, 'Unverified user login blocked with 403 and unverified: true');

    // Verify email using single-use token via API
    res = await fetch(`${BASE_URL}/api/v1/auth/verify-email`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ token: verificationToken })
    });
    data = await res.json();
    assert(res.status === 200 && data.success === true, 'Email verification endpoint activates user');

    // Token must now be invalidated (single-use)
    const [verifiedRows] = await pool.query('SELECT is_verified, verification_token FROM users WHERE email = ?', [testEmail]);
    assert(verifiedRows[0].is_verified === 1, 'Database updated: is_verified = 1');
    assert(verifiedRows[0].verification_token === null, 'Security: Verification token cleared after single use');

    // Now login MUST succeed
    res = await fetch(`${BASE_URL}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: testEmail,
        password: 'Hotel@2026Password'
      })
    });
    data = await res.json();
    assert(res.status === 200 && data.token && data.user.role === 'guest', 'Verified guest logs in successfully and receives JWT');
    const guestToken = data.token;

    // ----------------------------------------------------
    // TEST 6: Admin Account & RBAC Authorization Checks
    // ----------------------------------------------------
    console.log('\n--- Test Group 6: Designated Admin & Server-side RBAC ---');

    // Designated Administrator Login (amirhassen2027@gmail.com)
    res = await fetch(`${BASE_URL}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: 'amirhassen2027@gmail.com',
        password: 'Hotel@2026A'
      })
    });
    data = await res.json();
    assert(res.status === 200 && data.user.role === 'super_admin', 'Designated Admin (amirhassen2027@gmail.com) logs in with super_admin role');
    const adminToken = data.token;

    // RBAC: Guest attempts to access Financial Reports (Admin Only) -> 403 Forbidden
    res = await fetch(`${BASE_URL}/api/v1/reports/financial`, {
      headers: { 'Authorization': `Bearer ${guestToken}` }
    });
    assert(res.status === 403, 'RBAC: Guest user blocked from Financial Reports (403 Forbidden)');

    // RBAC: Admin accesses Financial Reports -> 200 OK
    res = await fetch(`${BASE_URL}/api/v1/reports/financial`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    assert(res.status === 200, 'RBAC: Designated Admin authorized for Financial Reports (200 OK)');

    // RBAC: Guest attempts to delete or create a room -> 403 Forbidden
    res = await fetch(`${BASE_URL}/api/v1/rooms`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${guestToken}`
      },
      body: JSON.stringify({
        room_number: '999',
        category_id: 'cat-deluxe',
        price_etb: 5000
      })
    });
    assert(res.status === 403, 'RBAC: Guest blocked from creating hotel rooms (403 Forbidden)');

    // RBAC: Unauthenticated request to admin dashboard stats -> 401 Unauthorized
    res = await fetch(`${BASE_URL}/api/v1/admin/dashboard-stats`);
    assert(res.status === 401, 'RBAC: Unauthenticated request rejected (401 Unauthorized)');

    // RBAC: Admin accesses dashboard stats -> 200 OK
    res = await fetch(`${BASE_URL}/api/v1/admin/dashboard-stats`, {
      headers: { 'Authorization': `Bearer ${adminToken}` }
    });
    assert(res.status === 200, 'RBAC: Designated Admin authorized for Dashboard Stats (200 OK)');

    // Cleanup test user
    await pool.query('DELETE FROM users WHERE email = ?', [testEmail]);
    console.log('\n🧹 Test guest account cleaned up.');

    console.log('\n====================================================');
    console.log(`📊 Test Suite Completed: ${passed} PASSED, ${failed} FAILED`);
    console.log('====================================================\n');

    process.exit(failed > 0 ? 1 : 0);
  } catch (err) {
    console.error('Test Execution Error:', err);
    process.exit(1);
  }
}

runTests();
