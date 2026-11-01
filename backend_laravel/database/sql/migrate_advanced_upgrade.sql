-- ============================================================
-- Advanced Migration: Hotel Management System Upgrade
-- Schema adjustments for:
-- 1. Expanded Booking & Payment status lifecycles
-- 2. Check-In & Check-Out tracking metadata
-- 3. Payment receipt uploads, review logs, and 20-min SLA timer
-- 4. User Room Favorites table
-- ============================================================

USE hotel_management_db;

-- 1. Modify bookings table status enum and add check-in/out metadata
ALTER TABLE bookings 
  MODIFY COLUMN booking_status ENUM(
    'pending_payment',
    'pending_verification',
    'confirmed',
    'checked_in',
    'checked_out',
    'rejected',
    'cancelled',
    'expired'
  ) NOT NULL DEFAULT 'confirmed';

ALTER TABLE bookings 
  MODIFY COLUMN payment_status ENUM(
    'pending',
    'pending_verification',
    'paid',
    'rejected',
    'refunded'
  ) NOT NULL DEFAULT 'paid';

-- Add Check-In / Check-Out timestamps and staff log columns if not present
SET @col_exist_checkin = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='bookings' AND COLUMN_NAME='actual_check_in_at');
SET @s1 = IF(@col_exist_checkin = 0, 'ALTER TABLE bookings ADD COLUMN actual_check_in_at DATETIME NULL AFTER check_out_date', 'SELECT 1');
PREPARE stmt1 FROM @s1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @col_exist_checkin_by = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='bookings' AND COLUMN_NAME='checked_in_by');
SET @s2 = IF(@col_exist_checkin_by = 0, 'ALTER TABLE bookings ADD COLUMN checked_in_by VARCHAR(64) NULL AFTER actual_check_in_at', 'SELECT 1');
PREPARE stmt2 FROM @s2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SET @col_exist_checkout = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='bookings' AND COLUMN_NAME='actual_check_out_at');
SET @s3 = IF(@col_exist_checkout = 0, 'ALTER TABLE bookings ADD COLUMN actual_check_out_at DATETIME NULL AFTER checked_in_by', 'SELECT 1');
PREPARE stmt3 FROM @s3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

SET @col_exist_checkout_by = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='bookings' AND COLUMN_NAME='checked_out_by');
SET @s4 = IF(@col_exist_checkout_by = 0, 'ALTER TABLE bookings ADD COLUMN checked_out_by VARCHAR(64) NULL AFTER actual_check_out_at', 'SELECT 1');
PREPARE stmt4 FROM @s4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

SET @col_exist_hold = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='bookings' AND COLUMN_NAME='hold_expires_at');
SET @s5 = IF(@col_exist_hold = 0, 'ALTER TABLE bookings ADD COLUMN hold_expires_at DATETIME NULL AFTER checked_out_by', 'SELECT 1');
PREPARE stmt5 FROM @s5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

-- 2. Modify payments table for receipt upload, review, and 20-min SLA
ALTER TABLE payments 
  MODIFY COLUMN status ENUM(
    'pending',
    'pending_verification',
    'verified',
    'failed',
    'rejected',
    'refunded'
  ) NOT NULL DEFAULT 'verified';

SET @col_exist_receipt = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='receipt_url');
SET @s6 = IF(@col_exist_receipt = 0, 'ALTER TABLE payments ADD COLUMN receipt_url VARCHAR(500) NULL AFTER raw_response', 'SELECT 1');
PREPARE stmt6 FROM @s6; EXECUTE stmt6; DEALLOCATE PREPARE stmt6;

SET @col_exist_claimed = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='claimed_amount_etb');
SET @s7 = IF(@col_exist_claimed = 0, 'ALTER TABLE payments ADD COLUMN claimed_amount_etb DECIMAL(12,2) NULL AFTER receipt_url', 'SELECT 1');
PREPARE stmt7 FROM @s7; EXECUTE stmt7; DEALLOCATE PREPARE stmt7;

SET @col_exist_reason = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='rejection_reason');
SET @s8 = IF(@col_exist_reason = 0, 'ALTER TABLE payments ADD COLUMN rejection_reason TEXT NULL AFTER claimed_amount_etb', 'SELECT 1');
PREPARE stmt8 FROM @s8; EXECUTE stmt8; DEALLOCATE PREPARE stmt8;

SET @col_exist_reviewer = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='reviewed_by');
SET @s9 = IF(@col_exist_reviewer = 0, 'ALTER TABLE payments ADD COLUMN reviewed_by VARCHAR(64) NULL AFTER rejection_reason', 'SELECT 1');
PREPARE stmt9 FROM @s9; EXECUTE stmt9; DEALLOCATE PREPARE stmt9;

SET @col_exist_reviewed_at = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='reviewed_at');
SET @s10 = IF(@col_exist_reviewed_at = 0, 'ALTER TABLE payments ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by', 'SELECT 1');
PREPARE stmt10 FROM @s10; EXECUTE stmt10; DEALLOCATE PREPARE stmt10;

SET @col_exist_submitted_at = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_management_db' AND TABLE_NAME='payments' AND COLUMN_NAME='submitted_at');
SET @s11 = IF(@col_exist_submitted_at = 0, 'ALTER TABLE payments ADD COLUMN submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER reviewed_at', 'SELECT 1');
PREPARE stmt11 FROM @s11; EXECUTE stmt11; DEALLOCATE PREPARE stmt11;

-- 3. Create user_favorites table
CREATE TABLE IF NOT EXISTS user_favorites (
  id VARCHAR(64) PRIMARY KEY,
  user_id VARCHAR(64) NOT NULL,
  room_id VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_room_fav (user_id, room_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- 4. Add helpful indexes for double-booking checks and payment filters
CREATE INDEX IF NOT EXISTS idx_booking_dates_room ON bookings (room_id, check_in_date, check_out_date, booking_status);
CREATE INDEX IF NOT EXISTS idx_payments_filter ON payments (payment_method, status, submitted_at);

SELECT 'Migration completed successfully' AS status;
