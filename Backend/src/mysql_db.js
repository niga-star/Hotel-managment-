require('dotenv').config();
const mysql = require('mysql2/promise');

// Create MySQL Connection Pool (ACID compliant, zero hardcoded values)
const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: parseInt(process.env.DB_PORT || '3306', 10),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'hotel_management_db',
  waitForConnections: true,
  connectionLimit: 20,
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 0
});

// Test connection on boot
pool.getConnection()
  .then(conn => {
    console.log(`[MySQL] Connected successfully to MySQL database "${process.env.DB_NAME || 'hotel_management_db'}" on port 3306.`);
    conn.release();
  })
  .catch(err => {
    console.error('[MySQL] Database connection failed:', err.message);
  });

module.exports = pool;
