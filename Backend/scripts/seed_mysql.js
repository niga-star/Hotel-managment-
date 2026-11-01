require('dotenv').config();
const bcrypt = require('bcryptjs');
const pool = require('../src/mysql_db');

async function seedMySQL() {
  const conn = await pool.getConnection();
  try {
    const [existing] = await conn.query('SELECT COUNT(*) AS count FROM users');
    if (existing[0].count > 0) {
      console.log('[MySQL] Database already contains records. Skipping seed.');
      return;
    }

    console.log('[MySQL] Seeding tables in MySQL database...');
    const defaultPassword = bcrypt.hashSync('hotel1234', 10);

    // 1. Users
    const users = [
      ['usr-admin-1', 'super_admin', 'Solomon Hailemariam', 'admin@ethiopianhotel.com', '+251 911 000111', defaultPassword, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=face'],
      ['usr-staff-1', 'receptionist', 'Makeda Assefa', 'reception@ethiopianhotel.com', '+251 911 222333', defaultPassword, 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-1', 'guest', 'Abebe Kebede', 'abebe@ethin.com', '+251 911 234567', defaultPassword, 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-2', 'guest', 'Sara Tadesse', 'sara@ethin.com', '+251 912 345678', defaultPassword, 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-3', 'guest', 'Saba Gidey', 'saba@ethin.com', '+251 913 456789', defaultPassword, 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-4', 'guest', 'Almaz Demeke', 'almaz@ethin.com', '+251 914 567890', defaultPassword, 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-5', 'guest', 'Yared Tilahun', 'yared@ethin.com', '+251 915 678901', defaultPassword, 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&h=150&fit=crop&crop=face'],
      ['usr-guest-6', 'guest', 'Analisa Ethiopian', 'aya@ethin.com', '+251 916 789012', defaultPassword, 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&h=150&fit=crop&crop=face']
    ];

    await conn.query(`
      INSERT INTO users (id, role, full_name, email, phone, password_hash, avatar_url)
      VALUES ?
    `, [users]);

    // 2. Categories
    const categories = [
      ['cat-suite', 'Suite', 'suite', '#4F46E5', 'Luxury suites with timber craft and bespoke living quarters.', 2500, 390],
      ['cat-deluxe', 'Deluxe', 'deluxe', '#D4A329', 'Spacious deluxe rooms with king comfort and balconies.', 1800, 250],
      ['cat-standard', 'Standard', 'standard', '#64748B', 'Standard rooms with modern comfort and garden views.', 1200, 180]
    ];
    await conn.query(`
      INSERT INTO room_categories (id, name, slug, badge_color, description, base_price_etb, base_price_usd)
      VALUES ?
    `, [categories]);

    // 3. Amenities
    const amenities = [
      ['amen-wifi', 'Free Wi-Fi', 'Wifi'],
      ['amen-breakfast', 'Breakfast Included', 'Utensils'],
      ['amen-shuttle', 'Airport Shuttle', 'Car'],
      ['amen-spa', 'Spa', 'Sparkles'],
      ['amen-pool', 'Swimming Pool', 'Waves'],
      ['amen-view', 'Mountain View', 'Eye'],
      ['amen-balcony', 'Private Balcony', 'Sun'],
      ['amen-service', '24/7 Room Service', 'Clock']
    ];
    await conn.query(`INSERT INTO amenities (id, name, icon_name) VALUES ?`, [amenities]);

    // 4. Rooms
    const rooms = [
      ['rm-101', '101', 'cat-suite', 'Executive Ethiopian Suite', 'available', 1, 2, 2500, 390],
      ['rm-102', '102', 'cat-deluxe', 'Boutique Ethiopian Deluxe', 'occupied', 1, 2, 1800, 250],
      ['rm-103', '103', 'cat-suite', 'Lalibela Rock Heritage Suite', 'maintenance', 1, 3, 2800, 420],
      ['rm-104', '104', 'cat-deluxe', 'Semien Valley View Deluxe', 'available', 1, 2, 1900, 260],
      ['rm-105', '105', 'cat-suite', 'Axum Palace Royal Suite', 'available', 1, 4, 3200, 450],
      ['rm-106', '106', 'cat-deluxe', 'Entoto Sunset Deluxe', 'occupied', 1, 2, 1800, 250],
      ['rm-107', '107', 'cat-suite', 'Blue Nile Grand Suite', 'available', 1, 2, 2600, 380],
      ['rm-108', '108', 'cat-standard', 'Gondar Castle Standard', 'maintenance', 1, 2, 1200, 180],
      ['rm-109', '109', 'cat-suite', 'Bale Mountain Highland Suite', 'available', 1, 2, 2400, 350],
      ['rm-110', '110', 'cat-standard', 'Rift Valley Garden Room', 'available', 1, 2, 1200, 180],
      ['rm-111', '111', 'cat-standard', 'Harar Traditional Room', 'maintenance', 1, 2, 1100, 170],
      ['rm-112', '112', 'cat-suite', 'Abyssinia Presidential Suite', 'available', 1, 4, 3500, 490],
      ['rm-201', '201', 'cat-deluxe', 'Simien Luxury Deluxe Room', 'occupied', 2, 2, 1800, 250],
      ['rm-202', '202', 'cat-standard', 'Debre Damo Standard Room', 'available', 2, 2, 1200, 180]
    ];
    await conn.query(`
      INSERT INTO rooms (id, room_number, category_id, title, status, floor, capacity, price_etb, price_usd)
      VALUES ?
    `, [rooms]);

    // 5. Room Images
    const roomImages = [
      ['img-101', 'rm-101', 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&fit=crop', 1, 0],
      ['img-102', 'rm-102', 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&fit=crop', 1, 0],
      ['img-103', 'rm-103', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&fit=crop', 1, 0],
      ['img-104', 'rm-104', 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800&fit=crop', 1, 0],
      ['img-105', 'rm-105', 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800&fit=crop', 1, 0],
      ['img-106', 'rm-106', 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&fit=crop', 1, 0],
      ['img-107', 'rm-107', 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&fit=crop', 1, 0],
      ['img-108', 'rm-108', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&fit=crop', 1, 0],
      ['img-109', 'rm-109', 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=800&fit=crop', 1, 0],
      ['img-110', 'rm-110', 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&fit=crop', 1, 0],
      ['img-111', 'rm-111', 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=800&fit=crop', 1, 0],
      ['img-112', 'rm-112', 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&fit=crop', 1, 0],
      ['img-201', 'rm-201', 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&fit=crop', 1, 0],
      ['img-202', 'rm-202', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&fit=crop', 1, 0]
    ];
    await conn.query(`
      INSERT INTO room_images (id, room_id, image_url, is_primary, display_order)
      VALUES ?
    `, [roomImages]);

    // 6. Room Amenities Junction
    const roomAmenities = [
      ['rm-101', 'amen-wifi'], ['rm-101', 'amen-breakfast'], ['rm-101', 'amen-shuttle'], ['rm-101', 'amen-spa'],
      ['rm-102', 'amen-wifi'], ['rm-102', 'amen-breakfast'], ['rm-102', 'amen-view'],
      ['rm-104', 'amen-wifi'], ['rm-104', 'amen-breakfast'], ['rm-104', 'amen-shuttle'],
      ['rm-112', 'amen-wifi'], ['rm-112', 'amen-breakfast'], ['rm-112', 'amen-spa'], ['rm-112', 'amen-pool']
    ];
    await conn.query(`INSERT INTO room_amenities (room_id, amenity_id) VALUES ?`, [roomAmenities]);

    // 7. Bookings & Payments
    const bookings = [
      ['bk-1', '#ETH-89274', 'usr-guest-1', 'rm-104', '2026-10-21', '2026-10-25', 2, 0, 4500, 390, 'confirmed', 'paid', 0, 'qr_hash_89274'],
      ['bk-2', '#ETH-31084', 'usr-guest-2', 'rm-201', '2026-10-19', '2026-10-21', 2, 1, 8200, 620, 'checked_in', 'pending', 0, 'qr_hash_31084'],
      ['bk-3', '#ETH-77123', 'usr-guest-5', 'rm-102', '2026-10-18', '2026-10-23', 1, 0, 3100, 250, 'checked_in', 'paid', 0, 'qr_hash_77123'],
      ['bk-4', '#ETH-49210', 'usr-guest-4', 'rm-106', '2026-10-21', '2026-10-28', 2, 0, 5400, 400, 'confirmed', 'pending', 0, 'qr_hash_49210'],
      ['bk-5', '#ETH-99412', 'usr-guest-3', 'rm-112', '2026-10-21', '2026-10-28', 2, 0, 14000, 1200, 'confirmed', 'paid', 0, 'qr_hash_99412'],
      ['bk-6', '#ETH-55610', 'usr-guest-6', 'rm-101', '2026-10-24', '2026-10-28', 2, 0, 10000, 380, 'confirmed', 'paid', 0, 'qr_hash_55610']
    ];
    await conn.query(`
      INSERT INTO bookings (id, reference_code, guest_id, room_id, check_in_date, check_out_date, adults_count, children_count, total_price_etb, total_price_usd, booking_status, payment_status, is_walk_in, qr_verification_hash)
      VALUES ?
    `, [bookings]);

    const payments = [
      ['pay-bk-1', 'TX-84920', 'bk-1', 'telebirr', 4500, 390, 'verified'],
      ['pay-bk-2', 'TX-31084', 'bk-2', 'cbe_birr', 8200, 620, 'pending'],
      ['pay-bk-3', 'TX-77123', 'bk-3', 'chapa', 3100, 250, 'verified'],
      ['pay-bk-4', 'TX-49210', 'bk-4', 'cash', 5400, 400, 'pending'],
      ['pay-bk-5', 'TX-99412', 'bk-5', 'telebirr', 14000, 1200, 'verified'],
      ['pay-bk-6', 'TX-55610', 'bk-6', 'chapa', 10000, 380, 'verified']
    ];
    await conn.query(`
      INSERT INTO payments (id, transaction_id, booking_id, payment_method, amount_etb, amount_usd, status)
      VALUES ?
    `, [payments]);

    // 8. Reviews
    const reviews = [
      ['rev-1', 'bk-1', 'usr-guest-1', 'rm-101', 5, 'Pleasant stay in Ethiopian, and comfortable rooms with pleasant environment.'],
      ['rev-2', 'bk-2', 'usr-guest-2', 'rm-101', 5, 'Pleasant stay in Ethiopian, and comfortable rooms with great views and staff.'],
      ['rev-3', 'bk-3', 'usr-guest-5', 'rm-101', 5, 'Pleasant stay in Ethiopian, and of our prioritized trip. Beautiful hotel setting.']
    ];
    await conn.query(`
      INSERT INTO reviews (id, booking_id, guest_id, room_id, rating, comment)
      VALUES ?
    `, [reviews]);

    console.log('[MySQL] Seeding completed with 100% relational integrity.');
  } catch (err) {
    console.error('[MySQL] Seeding error:', err);
  } finally {
    conn.release();
    process.exit(0);
  }
}

seedMySQL();
