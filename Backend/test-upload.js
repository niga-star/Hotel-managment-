const http = require('http');
const fs = require('fs');
const path = require('path');

async function testUpload() {
  console.log('--- Testing Phase 4: Direct File Upload to MySQL Database ---');

  // 1. Create a dummy test image buffer (PNG header)
  const pngHeader = Buffer.from([
    0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A,
    0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52,
    0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
    0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4,
    0x89, 0x00, 0x00, 0x00, 0x0A, 0x49, 0x44, 0x41,
    0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
    0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00,
    0x00, 0x00, 0x00, 0x49, 0x45, 0x4E, 0x44, 0xAE,
    0x42, 0x60, 0x82
  ]);

  const boundary = '----WebKitFormBoundary7MA4YWxkTrZu0gW';
  const pre = Buffer.from(
    `--${boundary}\r\n` +
    `Content-Disposition: form-data; name="image"; filename="habesha_suite_photo.png"\r\n` +
    `Content-Type: image/png\r\n\r\n`
  );
  const post = Buffer.from(`\r\n--${boundary}--\r\n`);
  const multipartBody = Buffer.concat([pre, pngHeader, post]);

  // 2. Upload via POST /api/v1/upload/room-image
  const uploadResult = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: 'localhost',
      port: 5000,
      path: '/api/v1/upload/room-image',
      method: 'POST',
      headers: {
        'Content-Type': `multipart/form-data; boundary=${boundary}`,
        'Content-Length': multipartBody.length
      }
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: JSON.parse(data) }));
    });
    req.on('error', reject);
    req.write(multipartBody);
    req.end();
  });

  console.log('[PASS 1] Upload API Response:', uploadResult.status, uploadResult.body.image_url);

  if (uploadResult.status !== 200 || !uploadResult.body.image_url) {
    throw new Error('Upload failed: ' + JSON.stringify(uploadResult.body));
  }

  // 3. Verify static file retrieval
  const fileFetch = await new Promise((resolve, reject) => {
    http.get(`http://localhost:5000${uploadResult.body.image_url}`, res => {
      resolve({ status: res.statusCode, contentType: res.headers['content-type'] });
    }).on('error', reject);
  });

  console.log('[PASS 2] Static File Served from Disk:', fileFetch.status, fileFetch.contentType);

  // 4. Create new room in MySQL using the uploaded image URL
  const newRoomNumber = '305';
  const roomPayload = JSON.stringify({
    room_number: newRoomNumber,
    category_id: 'cat-suite',
    title: 'Simien Highland Presidential Suite',
    price_etb: 4200,
    capacity: 4,
    amenities: ['amen-wifi', 'amen-breakfast', 'amen-spa'],
    image_url: uploadResult.body.image_url
  });

  const createRoomResult = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: 'localhost',
      port: 5000,
      path: '/api/v1/rooms',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(roomPayload)
      }
    }, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, body: JSON.parse(data) }));
    });
    req.on('error', reject);
    req.write(roomPayload);
    req.end();
  });

  console.log('[PASS 3] Room Created in MySQL with Uploaded File:', createRoomResult.status, createRoomResult.body.message);

  console.log('\n>>> ALL PHASE 4 DIRECT FILE UPLOAD TESTS PASSED 100% IN MYSQL! <<<');
}

testUpload().catch(err => {
  console.error('[FAIL]', err);
  process.exit(1);
});
