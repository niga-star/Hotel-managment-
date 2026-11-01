const pool = require('../src/mysql_db');

async function run() {
  try {
    await pool.query('ALTER TABLE rooms ADD COLUMN description TEXT NULL');
    console.log('✅ Added description column to rooms table.');
  } catch (err) {
    if (err.code === 'ER_DUP_FIELDNAME') {
      console.log('Column description already exists.');
    } else {
      console.error(err.message);
    }
  }
  process.exit(0);
}

run();
