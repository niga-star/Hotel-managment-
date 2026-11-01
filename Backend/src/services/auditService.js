const pool = require('../mysql_db');

// Ensure audit_logs table exists
(async function initAuditTable() {
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS audit_logs (
        id VARCHAR(64) PRIMARY KEY,
        user_id VARCHAR(64),
        user_name VARCHAR(150),
        user_role VARCHAR(50),
        action VARCHAR(100) NOT NULL,
        resource_type VARCHAR(50) NOT NULL,
        resource_id VARCHAR(100),
        details JSON,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);
    console.log('[AuditService] Security audit trail table initialized.');
  } catch (err) {
    console.warn('[AuditService] Table initialization notice:', err.message);
  }
})();

/**
 * Record an administrative or operational security action to MySQL
 */
async function logAction({
  userId = 'system',
  userName = 'System Administrator',
  userRole = 'system',
  action,
  resourceType,
  resourceId = null,
  details = {},
  ipAddress = '127.0.0.1'
}) {
  try {
    const id = `aud-${Date.now()}-${Math.floor(1000 + Math.random() * 9000)}`;
    const detailsJson = typeof details === 'string' ? details : JSON.stringify(details);

    await pool.query(`
      INSERT INTO audit_logs (id, user_id, user_name, user_role, action, resource_type, resource_id, details, ip_address)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [id, userId, userName, userRole, action, resourceType, resourceId, detailsJson, ipAddress]);
  } catch (err) {
    console.error('[AuditService] Error logging audit trail:', err.message);
  }
}

/**
 * Retrieve recent audit trail logs
 */
async function getRecentLogs(limit = 100) {
  const [rows] = await pool.query(`
    SELECT * FROM audit_logs
    ORDER BY created_at DESC
    LIMIT ?
  `, [parseInt(limit)]);
  return rows;
}

module.exports = {
  logAction,
  getRecentLogs
};
