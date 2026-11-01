const pool = require('../src/mysql_db');

async function migrate() {
  console.log('--- Migrating MySQL users table for 6-Digit Email Verification ---');
  try {
    // 1. Check if verification_code column exists
    const [cols] = await pool.query('SHOW COLUMNS FROM users');
    const colNames = cols.map(c => c.Field);

    if (!colNames.includes('verification_code')) {
      await pool.query('ALTER TABLE users ADD COLUMN verification_code VARCHAR(10) NULL AFTER verification_token');
      console.log('✅ Added column `verification_code` to users table.');
    } else {
      console.log('Column `verification_code` already exists.');
    }

    if (!colNames.includes('verification_attempts')) {
      await pool.query('ALTER TABLE users ADD COLUMN verification_attempts INT NOT NULL DEFAULT 0 AFTER verification_token_expires');
      console.log('✅ Added column `verification_attempts` to users table.');
    } else {
      console.log('Column `verification_attempts` already exists.');
    }

    if (!colNames.includes('code_last_sent_at')) {
      await pool.query('ALTER TABLE users ADD COLUMN code_last_sent_at DATETIME NULL AFTER verification_attempts');
      console.log('✅ Added column `code_last_sent_at` to users table.');
    } else {
      console.log('Column `code_last_sent_at` already exists.');
    }

    console.log('✅ Database migration completed successfully.');
    process.exit(0);
  } catch (err) {
    console.error('Migration failed:', err);
    process.exit(1);
  }
}

migrate();
