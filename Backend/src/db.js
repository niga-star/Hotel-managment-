const { DatabaseSync } = require('node:sqlite');
const path = require('path');
const fs = require('fs');
const bcrypt = require('bcryptjs');

// Database file path
const dbDir = path.join(__dirname, '..', 'data');
if (!fs.existsSync(dbDir)) {
  fs.mkdirSync(dbDir, { recursive: true });
}

const dbPath = path.join(dbDir, 'hotel_management.db');
const db = new DatabaseSync(dbPath);

// Enable WAL mode & foreign keys for high-concurrency ACID transactions
db.exec(`
  PRAGMA journal_mode = WAL;
  PRAGMA foreign_keys = ON;
`);

function initSchema() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id TEXT PRIMARY KEY,
      role TEXT NOT NULL DEFAULT 'guest', -- 'super_admin', 'receptionist', 'guest'
      full_name TEXT NOT NULL,
      email TEXT UNIQUE NOT NULL,
      phone TEXT NOT NULL,
      password_hash TEXT NOT NULL,
      avatar_url TEXT DEFAULT '/avatars/default.png',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS room_categories (
      id TEXT PRIMARY KEY,
      name TEXT UNIQUE NOT NULL, -- 'Suite', 'Deluxe', 'Standard'
      slug TEXT UNIQUE NOT NULL,
      badge_color TEXT DEFAULT '#0B4D34',
      description TEXT,
      base_price_etb REAL NOT NULL,
      base_price_usd REAL NOT NULL
    );

    CREATE TABLE IF NOT EXISTS rooms (
      id TEXT PRIMARY KEY,
      room_number TEXT UNIQUE NOT NULL,
      category_id TEXT NOT NULL REFERENCES room_categories(id),
      title TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'available', -- 'available', 'occupied', 'maintenance'
      floor INTEGER NOT NULL DEFAULT 1,
      capacity INTEGER NOT NULL DEFAULT 2,
      price_etb REAL NOT NULL,
      price_usd REAL NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS room_images (
      id TEXT PRIMARY KEY,
      room_id TEXT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
      image_url TEXT NOT NULL,
      caption TEXT,
      is_primary INTEGER DEFAULT 0,
      display_order INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS amenities (
      id TEXT PRIMARY KEY,
      name TEXT UNIQUE NOT NULL,
      icon_name TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS room_amenities (
      room_id TEXT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
      amenity_id TEXT NOT NULL REFERENCES amenities(id) ON DELETE CASCADE,
      PRIMARY KEY (room_id, amenity_id)
    );

    CREATE TABLE IF NOT EXISTS bookings (
      id TEXT PRIMARY KEY,
      reference_code TEXT UNIQUE NOT NULL, -- e.g. '#ETH-89274'
      guest_id TEXT NOT NULL REFERENCES users(id),
      room_id TEXT NOT NULL REFERENCES rooms(id),
      check_in_date TEXT NOT NULL,
      check_out_date TEXT NOT NULL,
      adults_count INTEGER NOT NULL DEFAULT 1,
      children_count INTEGER NOT NULL DEFAULT 0,
      total_price_etb REAL NOT NULL,
      total_price_usd REAL NOT NULL,
      booking_status TEXT NOT NULL DEFAULT 'confirmed', -- 'confirmed', 'checked_in', 'checked_out', 'cancelled'
      payment_status TEXT NOT NULL DEFAULT 'paid', -- 'pending', 'paid', 'refunded'
      is_walk_in INTEGER NOT NULL DEFAULT 0,
      qr_verification_hash TEXT NOT NULL,
      notes TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS payments (
      id TEXT PRIMARY KEY,
      transaction_id TEXT UNIQUE NOT NULL, -- e.g. 'TX-84920'
      booking_id TEXT NOT NULL REFERENCES bookings(id),
      payment_method TEXT NOT NULL, -- 'telebirr', 'cbe_birr', 'chapa', 'cash', 'credit_card'
      amount_etb REAL NOT NULL,
      amount_usd REAL,
      status TEXT NOT NULL DEFAULT 'verified', -- 'verified', 'pending', 'failed'
      payment_date DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS reviews (
      id TEXT PRIMARY KEY,
      booking_id TEXT UNIQUE REFERENCES bookings(id) ON DELETE CASCADE,
      guest_id TEXT NOT NULL REFERENCES users(id),
      room_id TEXT NOT NULL REFERENCES rooms(id),
      rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
      comment TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
  `);

  console.log('[DB] Relational database schema verified.');
}

function seedDatabase() {
  const userCount = db.prepare('SELECT COUNT(*) AS count FROM users').get().count;
  if (userCount > 0) {
    console.log('[DB] Database already contains records. Skipping seed.');
    return;
  }

  console.log('[DB] Seeding database with production Ethiopian hotel records...');

  const defaultPassword = bcrypt.hashSync('hotel1234', 10);

  // 1. Seed Users (Guest, Front Desk, Super Admin)
  const users = [
    {
      id: 'usr-admin-1',
      role: 'super_admin',
      full_name: 'Solomon Hailemariam',
      email: 'admin@ethiopianhotel.com',
      phone: '+251 911 000111',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-staff-1',
      role: 'receptionist',
      full_name: 'Makeda Assefa',
      email: 'reception@ethiopianhotel.com',
      phone: '+251 911 222333',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-1',
      role: 'guest',
      full_name: 'Abebe Kebede',
      email: 'abebe@ethin.com',
      phone: '+251 911 234567',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-2',
      role: 'guest',
      full_name: 'Sara Tadesse',
      email: 'sara@ethin.com',
      phone: '+251 912 345678',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-3',
      role: 'guest',
      full_name: 'Saba Gidey',
      email: 'saba@ethin.com',
      phone: '+251 913 456789',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-4',
      role: 'guest',
      full_name: 'Almaz Demeke',
      email: 'almaz@ethin.com',
      phone: '+251 914 567890',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-5',
      role: 'guest',
      full_name: 'Yared Tilahun',
      email: 'yared@ethin.com',
      phone: '+251 915 678901',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&h=150&fit=crop&crop=face'
    },
    {
      id: 'usr-guest-6',
      role: 'guest',
      full_name: 'Analisa Ethiopian',
      email: 'aya@ethin.com',
      phone: '+251 916 789012',
      password_hash: defaultPassword,
      avatar_url: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&h=150&fit=crop&crop=face'
    }
  ];

  const insertUser = db.prepare(`
    INSERT INTO users (id, role, full_name, email, phone, password_hash, avatar_url)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `);
  for (const u of users) {
    insertUser.run(u.id, u.role, u.full_name, u.email, u.phone, u.password_hash, u.avatar_url);
  }

  // 2. Seed Categories
  const categories = [
    {
      id: 'cat-suite',
      name: 'Suite',
      slug: 'suite',
      badge_color: '#4F46E5', // Indigo / Purple pill
      description: 'Expansive luxury suites with handcrafted Ethiopian timber architecture, bespoke living quarters, and panoramic vistas.',
      base_price_etb: 2500,
      base_price_usd: 390
    },
    {
      id: 'cat-deluxe',
      name: 'Deluxe',
      slug: 'deluxe',
      badge_color: '#D4A329', // Ethiopian Gold
      description: 'Premium spacious rooms featuring authentic woven accents, king-size comfort, and private balconies.',
      base_price_etb: 1800,
      base_price_usd: 250
    },
    {
      id: 'cat-standard',
      name: 'Standard',
      slug: 'standard',
      badge_color: '#64748B', // Slate Grey
      description: 'Elegantly appointed rooms providing serene comfort, modern amenities, and peaceful garden views.',
      base_price_etb: 1200,
      base_price_usd: 180
    }
  ];

  const insertCat = db.prepare(`
    INSERT INTO room_categories (id, name, slug, badge_color, description, base_price_etb, base_price_usd)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `);
  for (const c of categories) {
    insertCat.run(c.id, c.name, c.slug, c.badge_color, c.description, c.base_price_etb, c.base_price_usd);
  }

  // 3. Seed Amenities
  const amenitiesList = [
    { id: 'amen-wifi', name: 'Free Wi-Fi', icon_name: 'Wifi' },
    { id: 'amen-breakfast', name: 'Breakfast Included', icon_name: 'Utensils' },
    { id: 'amen-shuttle', name: 'Airport Shuttle', icon_name: 'Car' },
    { id: 'amen-spa', name: 'Spa', icon_name: 'Sparkles' },
    { id: 'amen-pool', name: 'Swimming Pool', icon_name: 'Waves' },
    { id: 'amen-view', name: 'Mountain View', icon_name: 'Eye' },
    { id: 'amen-balcony', name: 'Private Balcony', icon_name: 'Sun' },
    { id: 'amen-service', name: '24/7 Room Service', icon_name: 'Clock' }
  ];

  const insertAmenity = db.prepare(`INSERT INTO amenities (id, name, icon_name) VALUES (?, ?, ?)`);
  for (const a of amenitiesList) {
    insertAmenity.run(a.id, a.name, a.icon_name);
  }

  // 4. Seed Rooms matching screenshot states (101-112, 201, 202)
  const roomsList = [
    {
      id: 'rm-101',
      room_number: '101',
      category_id: 'cat-suite',
      title: 'Executive Ethiopian Suite',
      status: 'available',
      floor: 1,
      capacity: 2,
      price_etb: 2500,
      price_usd: 390,
      image: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-shuttle', 'amen-spa', 'amen-balcony']
    },
    {
      id: 'rm-102',
      room_number: '102',
      category_id: 'cat-deluxe',
      title: 'Boutique Ethiopian Deluxe',
      status: 'occupied',
      floor: 1,
      capacity: 2,
      price_etb: 1800,
      price_usd: 250,
      image: 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-view']
    },
    {
      id: 'rm-103',
      room_number: '103',
      category_id: 'cat-suite',
      title: 'Lalibela Rock Heritage Suite',
      status: 'maintenance',
      floor: 1,
      capacity: 3,
      price_etb: 2800,
      price_usd: 420,
      image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-spa', 'amen-pool']
    },
    {
      id: 'rm-104',
      room_number: '104',
      category_id: 'cat-deluxe',
      title: 'Semien Valley View Deluxe',
      status: 'available',
      floor: 1,
      capacity: 2,
      price_etb: 1900,
      price_usd: 260,
      image: 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-shuttle']
    },
    {
      id: 'rm-105',
      room_number: '105',
      category_id: 'cat-suite',
      title: 'Axum Palace Royal Suite',
      status: 'available',
      floor: 1,
      capacity: 4,
      price_etb: 3200,
      price_usd: 450,
      image: 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-shuttle', 'amen-spa', 'amen-pool']
    },
    {
      id: 'rm-106',
      room_number: '106',
      category_id: 'cat-deluxe',
      title: 'Entoto Sunset Deluxe',
      status: 'occupied',
      floor: 1,
      capacity: 2,
      price_etb: 1800,
      price_usd: 250,
      image: 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-view']
    },
    {
      id: 'rm-107',
      room_number: '107',
      category_id: 'cat-suite',
      title: 'Blue Nile Grand Suite',
      status: 'available',
      floor: 1,
      capacity: 2,
      price_etb: 2600,
      price_usd: 380,
      image: 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-shuttle', 'amen-balcony']
    },
    {
      id: 'rm-108',
      room_number: '108',
      category_id: 'cat-standard',
      title: 'Gondar Castle Standard',
      status: 'maintenance',
      floor: 1,
      capacity: 2,
      price_etb: 1200,
      price_usd: 180,
      image: 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast']
    },
    {
      id: 'rm-109',
      room_number: '109',
      category_id: 'cat-suite',
      title: 'Bale Mountain Highland Suite',
      status: 'available',
      floor: 1,
      capacity: 2,
      price_etb: 2400,
      price_usd: 350,
      image: 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-spa']
    },
    {
      id: 'rm-110',
      room_number: '110',
      category_id: 'cat-standard',
      title: 'Rift Valley Garden Room',
      status: 'available',
      floor: 1,
      capacity: 2,
      price_etb: 1200,
      price_usd: 180,
      image: 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast']
    },
    {
      id: 'rm-111',
      room_number: '111',
      category_id: 'cat-standard',
      title: 'Harar Traditional Room',
      status: 'maintenance',
      floor: 1,
      capacity: 2,
      price_etb: 1100,
      price_usd: 170,
      image: 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi']
    },
    {
      id: 'rm-112',
      room_number: '112',
      category_id: 'cat-suite',
      title: 'Abyssinia Presidential Suite',
      status: 'available',
      floor: 1,
      capacity: 4,
      price_etb: 3500,
      price_usd: 490,
      image: 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-shuttle', 'amen-spa', 'amen-pool', 'amen-view']
    },
    {
      id: 'rm-201',
      room_number: '201',
      category_id: 'cat-deluxe',
      title: 'Simien Luxury Deluxe Room',
      status: 'occupied',
      floor: 2,
      capacity: 2,
      price_etb: 1800,
      price_usd: 250,
      image: 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast', 'amen-view']
    },
    {
      id: 'rm-202',
      room_number: '202',
      category_id: 'cat-standard',
      title: 'Debre Damo Standard Room',
      status: 'available',
      floor: 2,
      capacity: 2,
      price_etb: 1200,
      price_usd: 180,
      image: 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&auto=format&fit=crop&q=80',
      amenities: ['amen-wifi', 'amen-breakfast']
    }
  ];

  const insertRoom = db.prepare(`
    INSERT INTO rooms (id, room_number, category_id, title, status, floor, capacity, price_etb, price_usd)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  const insertRoomImage = db.prepare(`
    INSERT INTO room_images (id, room_id, image_url, is_primary, display_order)
    VALUES (?, ?, ?, 1, 0)
  `);
  const insertRoomAmenity = db.prepare(`
    INSERT INTO room_amenities (room_id, amenity_id) VALUES (?, ?)
  `);

  for (const r of roomsList) {
    insertRoom.run(r.id, r.room_number, r.category_id, r.title, r.status, r.floor, r.capacity, r.price_etb, r.price_usd);
    insertRoomImage.run(`img-${r.id}-primary`, r.id, r.image);
    for (const amenId of r.amenities) {
      insertRoomAmenity.run(r.id, amenId);
    }
  }

  // 5. Seed Bookings & Payments from the mockups
  const bookingsData = [
    {
      id: 'bk-1',
      reference_code: '#ETH-89274',
      guest_id: 'usr-guest-1', // Abebe Kebede
      room_id: 'rm-104',
      check_in_date: '2026-10-21',
      check_out_date: '2026-10-25',
      adults_count: 2,
      children_count: 0,
      total_price_etb: 4500,
      total_price_usd: 390,
      booking_status: 'confirmed',
      payment_method: 'telebirr',
      payment_status: 'verified',
      tx_id: 'TX-84920'
    },
    {
      id: 'bk-2',
      reference_code: '#ETH-31084',
      guest_id: 'usr-guest-2', // Sara Tadesse
      room_id: 'rm-201',
      check_in_date: '2026-10-19',
      check_out_date: '2026-10-21',
      adults_count: 2,
      children_count: 1,
      total_price_etb: 8200,
      total_price_usd: 620,
      booking_status: 'checked_in',
      payment_method: 'cbe_birr',
      payment_status: 'pending',
      tx_id: 'TX-31084'
    },
    {
      id: 'bk-3',
      reference_code: '#ETH-77123',
      guest_id: 'usr-guest-5', // Yared Tilahun
      room_id: 'rm-102',
      check_in_date: '2026-10-18',
      check_out_date: '2026-10-23',
      adults_count: 1,
      children_count: 0,
      total_price_etb: 3100,
      total_price_usd: 250,
      booking_status: 'checked_in',
      payment_method: 'chapa',
      payment_status: 'verified',
      tx_id: 'TX-77123'
    },
    {
      id: 'bk-4',
      reference_code: '#ETH-49210',
      guest_id: 'usr-guest-4', // Almaz Demeke
      room_id: 'rm-106',
      check_in_date: '2026-10-21',
      check_out_date: '2026-10-28',
      adults_count: 2,
      children_count: 0,
      total_price_etb: 5400,
      total_price_usd: 400,
      booking_status: 'confirmed',
      payment_method: 'cash',
      payment_status: 'pending',
      tx_id: 'TX-49210'
    },
    {
      id: 'bk-5',
      reference_code: '#ETH-99412',
      guest_id: 'usr-guest-3', // Saba Gidey
      room_id: 'rm-112',
      check_in_date: '2026-10-21',
      check_out_date: '2026-10-28',
      adults_count: 2,
      children_count: 0,
      total_price_etb: 14000,
      total_price_usd: 1200,
      booking_status: 'confirmed',
      payment_method: 'telebirr',
      payment_status: 'verified',
      tx_id: 'TX-99412'
    },
    {
      id: 'bk-6',
      reference_code: '#ETH-55610',
      guest_id: 'usr-guest-6', // Analisa Ethiopian
      room_id: 'rm-101',
      check_in_date: '2026-10-24',
      check_out_date: '2026-10-28',
      adults_count: 2,
      children_count: 0,
      total_price_etb: 10000,
      total_price_usd: 380,
      booking_status: 'confirmed',
      payment_method: 'chapa',
      payment_status: 'verified',
      tx_id: 'TX-55610'
    }
  ];

  const insertBooking = db.prepare(`
    INSERT INTO bookings (
      id, reference_code, guest_id, room_id, check_in_date, check_out_date,
      adults_count, children_count, total_price_etb, total_price_usd,
      booking_status, qr_verification_hash
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);

  const insertPayment = db.prepare(`
    INSERT INTO payments (id, transaction_id, booking_id, payment_method, amount_etb, status)
    VALUES (?, ?, ?, ?, ?, ?)
  `);

  for (const b of bookingsData) {
    const qrHash = `qr_hash_${b.reference_code.replace('#', '')}`;
    insertBooking.run(
      b.id, b.reference_code, b.guest_id, b.room_id, b.check_in_date, b.check_out_date,
      b.adults_count, b.children_count, b.total_price_etb, b.total_price_usd,
      b.booking_status, qrHash
    );

    insertPayment.run(
      `pay-${b.id}`, b.tx_id, b.id, b.payment_method, b.total_price_etb, b.payment_status
    );
  }

  // 6. Seed Reviews
  const reviewsData = [
    {
      id: 'rev-1',
      booking_id: 'bk-1',
      guest_id: 'usr-guest-1',
      room_id: 'rm-101',
      rating: 5,
      comment: 'Pleasant stay in Ethiopian, and comfortable rooms with pleasant environment.'
    },
    {
      id: 'rev-2',
      booking_id: 'bk-2',
      guest_id: 'usr-guest-2',
      room_id: 'rm-101',
      rating: 5,
      comment: 'Pleasant stay in Ethiopian, and comfortable rooms with great views and staff.'
    },
    {
      id: 'rev-3',
      booking_id: 'bk-3',
      guest_id: 'usr-guest-5',
      room_id: 'rm-101',
      rating: 5,
      comment: 'Pleasant stay in Ethiopian, and of our prioritized trip. Beautiful hotel setting.'
    }
  ];

  const insertReview = db.prepare(`
    INSERT INTO reviews (id, booking_id, guest_id, room_id, rating, comment)
    VALUES (?, ?, ?, ?, ?, ?)
  `);
  for (const rev of reviewsData) {
    insertReview.run(rev.id, rev.booking_id, rev.guest_id, rev.room_id, rev.rating, rev.comment);
  }

  console.log('[DB] Seeding completed successfully.');
}

initSchema();
seedDatabase();

module.exports = {
  db
};
