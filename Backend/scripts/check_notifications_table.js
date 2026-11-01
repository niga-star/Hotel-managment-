const pool = require('../src/mysql_db');

async function fix() {
  try {
    console.log('Modifying guest_notifications table to allow NULL booking_id and guest_id for auth notifications...');
    await pool.query('ALTER TABLE guest_notifications MODIFY COLUMN booking_id VARCHAR(64) NULL');
    await pool.query('ALTER TABLE guest_notifications MODIFY COLUMN guest_id VARCHAR(64) NULL');
    console.log('✅ Successfully updated guest_notifications columns to allow NULL.');
  } catch (err) {
    console.error('Error modifying guest_notifications:', err.message);
  }
  process.exit(0);
}

fix();
