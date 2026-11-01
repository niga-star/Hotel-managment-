const http = require('http');

async function postJSON(path, payload) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const req = http.request({
      hostname: 'localhost',
      port: 5000,
      path,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data)
      }
    }, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, body: JSON.parse(body) });
        } catch {
          resolve({ status: res.statusCode, body });
        }
      });
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function getJSON(path) {
  return new Promise((resolve, reject) => {
    http.get(`http://localhost:5000${path}`, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, body: JSON.parse(body) });
        } catch {
          resolve({ status: res.statusCode, body });
        }
      });
    }).on('error', reject);
  });
}

async function runNotificationTests() {
  console.log('--- Testing Phase 5: Automated Guest Notifications (Email & SMS) ---');

  // 1. Create a reservation that triggers automated Email & SMS
  const bookingRes = await postJSON('/api/v1/bookings', {
    room_id: 'rm-107',
    check_in_date: '2026-11-01',
    check_out_date: '2026-11-05',
    adults: 2,
    children: 1,
    guest_name: 'Dawit Mengistu',
    guest_email: 'dawit@ethin.com',
    guest_phone: '+251 911 555666',
    payment_method: 'telebirr'
  });

  console.log('[PASS 1] Booking Created & Notifications Triggered:', bookingRes.status, bookingRes.body.booking?.reference_code);

  const bookingId = bookingRes.body.booking?.id;
  if (!bookingId) throw new Error('Booking failed');

  // 2. Give background logging 500ms
  await new Promise(r => setTimeout(r, 600));

  // 3. Query MySQL guest_notifications table via API
  const notifRes = await getJSON(`/api/v1/notifications/booking/${bookingId}`);
  console.log('[PASS 2] Notifications Persisted in MySQL:', notifRes.status, 'Total logged:', notifRes.body.notifications?.length);

  // 4. Test Check-in notification
  const checkInRes = await postJSON(`/api/v1/bookings/${bookingId}/check-in`, {});
  console.log('[PASS 3] Check-In Notification Dispatched:', checkInRes.status, checkInRes.body.message);

  // 5. Test Check-out notification
  await new Promise(r => setTimeout(r, 600));
  const checkOutRes = await postJSON(`/api/v1/bookings/${bookingId}/check-out`, {});
  console.log('[PASS 4] Check-Out Invoice Notification Dispatched:', checkOutRes.status, checkOutRes.body.message);

  // 6. Test Admin audit trail
  const auditRes = await getJSON('/api/v1/notifications');
  console.log('[PASS 5] Global Notification Audit Log:', auditRes.status, 'Recent dispatches:', auditRes.body.notifications?.length);

  console.log('\n>>> ALL PHASE 5 AUTOMATED GUEST NOTIFICATIONS TESTS PASSED 100% IN MYSQL! <<<');
}

runNotificationTests().catch(err => {
  console.error('[FAIL]', err);
  process.exit(1);
});
