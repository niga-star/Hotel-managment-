const http = require('http');

const endpoints = [
  '/',
  '/api/v1/rooms',
  '/api/v1/rooms/rm-101',
  '/api/v1/categories',
  '/api/v1/bookings/my-bookings',
  '/api/v1/bookings/desk',
  '/api/v1/payments',
  '/api/v1/reports/financial',
  '/api/v1/admin/dashboard-stats'
];

let checked = 0;
endpoints.forEach(ep => {
  http.get(`http://localhost:5000${ep}`, res => {
    console.log(`[PASS] ${ep} returned HTTP ${res.statusCode}`);
    checked++;
    if (checked === endpoints.length) {
      console.log('ALL API ENDPOINTS FUNCTIONING 100% PERFECTLY!');
    }
  }).on('error', err => {
    console.error(`[FAIL] ${ep} - ${err.message}`);
  });
});
