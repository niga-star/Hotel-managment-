-- ==============================================================
-- Migration: Upgrade Authentication Module & Security Controls
-- Adds email verification, password reset tokens, and provisions
-- designated administrator: amirhassen2027@gmail.com
-- ==============================================================

USE hotel_management_db;

-- 1. Add verification and reset columns to users table
ALTER TABLE users 
ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
ADD COLUMN verification_token VARCHAR(255) NULL AFTER password_hash,
ADD COLUMN verification_token_expires DATETIME NULL AFTER verification_token,
ADD COLUMN reset_password_token VARCHAR(255) NULL AFTER verification_token_expires,
ADD COLUMN reset_password_expires DATETIME NULL AFTER reset_password_token;

-- 2. Add index on phone number and verification tokens
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_verif_token ON users(verification_token);
CREATE INDEX idx_users_reset_token ON users(reset_password_token);

-- 3. Mark existing demo users as verified so existing functionality is preserved
UPDATE users SET is_verified = 1;

-- 4. Provision or Upgrade the designated Administrator account
-- Email: amirhassen2027@gmail.com
-- Role: super_admin (is_verified = 1)
INSERT INTO users (
    id, role, is_verified, full_name, email, phone, password_hash, avatar_url
) VALUES (
    'usr-admin-amir',
    'super_admin',
    1,
    'Amir Hassen',
    'amirhassen2027@gmail.com',
    '+251 911 000001',
    '$2b$12$DoNOQAt5/34TTdrgCtW3IuBba0VRP.9ESBaG.B3xpOW2pLVMgfOOG',
    '/avatars/default.png'
) ON DUPLICATE KEY UPDATE 
    role = 'super_admin',
    is_verified = 1,
    full_name = 'Amir Hassen';
