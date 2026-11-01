-- ==============================================================
-- Luxury Ethiopian Hotel Management System
-- MySQL Relational Database Schema (Zero Hardcoding)
-- Character Set: utf8mb4 | Engine: InnoDB (ACID Compliant)
-- ==============================================================

USE hotel_management_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS room_amenities;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS room_images;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS room_categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Guests, Front Desk Staff, Super Admins)
CREATE TABLE users (
    id VARCHAR(64) PRIMARY KEY,
    role ENUM('super_admin', 'receptionist', 'guest') NOT NULL DEFAULT 'guest',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) UNIQUE NOT NULL,
    phone VARCHAR(30) NOT NULL, -- Normalized international E.164 format (+251..., +1...)
    password_hash VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255) NULL,
    verification_token_expires DATETIME NULL,
    reset_password_token VARCHAR(255) NULL,
    reset_password_expires DATETIME NULL,
    avatar_url VARCHAR(500) DEFAULT '/avatars/default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Room Categories Table
CREATE TABLE room_categories (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- 'Suite', 'Deluxe', 'Standard'
    slug VARCHAR(60) UNIQUE NOT NULL,
    badge_color VARCHAR(30) DEFAULT '#0B4D34',
    description TEXT,
    base_price_etb DECIMAL(12, 2) NOT NULL,
    base_price_usd DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Rooms Inventory Table
CREATE TABLE rooms (
    id VARCHAR(64) PRIMARY KEY,
    room_number VARCHAR(20) UNIQUE NOT NULL, -- '101', '102', '201', '301'...
    category_id VARCHAR(64) NOT NULL,
    title VARCHAR(150) NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available',
    floor INT NOT NULL DEFAULT 1,
    capacity INT NOT NULL DEFAULT 2,
    price_etb DECIMAL(12, 2) NOT NULL,
    price_usd DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_category FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Room Media Gallery Table
CREATE TABLE room_images (
    id VARCHAR(64) PRIMARY KEY,
    room_id VARCHAR(64) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(100),
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Amenities & Junction Table
CREATE TABLE amenities (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(80) UNIQUE NOT NULL,
    icon_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_amenities (
    room_id VARCHAR(64) NOT NULL,
    amenity_id VARCHAR(64) NOT NULL,
    PRIMARY KEY (room_id, amenity_id),
    CONSTRAINT fk_ra_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_ra_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bookings & Reservations Table
CREATE TABLE bookings (
    id VARCHAR(64) PRIMARY KEY,
    reference_code VARCHAR(30) UNIQUE NOT NULL, -- e.g. '#ETH-89274'
    guest_id VARCHAR(64) NOT NULL,
    room_id VARCHAR(64) NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    adults_count INT NOT NULL DEFAULT 1,
    children_count INT NOT NULL DEFAULT 0,
    total_price_etb DECIMAL(12, 2) NOT NULL,
    total_price_usd DECIMAL(10, 2) NOT NULL,
    booking_status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled') NOT NULL DEFAULT 'confirmed',
    payment_status ENUM('pending', 'paid', 'refunded') NOT NULL DEFAULT 'paid',
    is_walk_in TINYINT(1) NOT NULL DEFAULT 0,
    qr_verification_hash VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_guest FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Payment Transactions Table
CREATE TABLE payments (
    id VARCHAR(64) PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL, -- e.g. 'TX-84920'
    booking_id VARCHAR(64) NOT NULL,
    payment_method ENUM('telebirr', 'cbe_birr', 'chapa', 'cash', 'credit_card') NOT NULL,
    amount_etb DECIMAL(12, 2) NOT NULL,
    amount_usd DECIMAL(10, 2),
    status ENUM('verified', 'pending', 'failed', 'refunded') NOT NULL DEFAULT 'verified',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Verified Guest Reviews Table
CREATE TABLE reviews (
    id VARCHAR(64) PRIMARY KEY,
    booking_id VARCHAR(64) UNIQUE,
    guest_id VARCHAR(64) NOT NULL,
    room_id VARCHAR(64) NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_reviews_guest FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indices for rapid concurrency checking & filtering
CREATE INDEX idx_bookings_dates ON bookings (room_id, check_in_date, check_out_date);
CREATE INDEX idx_bookings_status ON bookings (booking_status);
CREATE INDEX idx_rooms_status ON rooms (status);
CREATE INDEX idx_payments_method ON payments (payment_method);
