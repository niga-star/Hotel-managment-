/**
 * In-Memory Rate Limiter Middleware
 * Protects auth endpoints against brute force, credential stuffing, and spam
 */

const requestCounts = new Map();

function createRateLimiter({ windowMs = 15 * 60 * 1000, max = 15, message = 'Too many attempts. Please try again later.' }) {
  // Periodically clean up expired entries
  setInterval(() => {
    const now = Date.now();
    for (const [key, data] of requestCounts.entries()) {
      if (now > data.resetTime) {
        requestCounts.delete(key);
      }
    }
  }, 5 * 60 * 1000);

  return (req, res, next) => {
    const ip = req.headers['x-forwarded-for'] || req.socket.remoteAddress || 'local';
    const route = req.baseUrl + req.path;
    const key = `${ip}:${route}`;
    const now = Date.now();

    const record = requestCounts.get(key) || { count: 0, resetTime: now + windowMs };

    if (now > record.resetTime) {
      record.count = 1;
      record.resetTime = now + windowMs;
    } else {
      record.count += 1;
    }

    requestCounts.set(key, record);

    if (record.count > max) {
      const retryAfter = Math.ceil((record.resetTime - now) / 1000);
      res.setHeader('Retry-After', retryAfter);
      return res.status(429).json({
        error: message,
        retryAfterSeconds: retryAfter
      });
    }

    next();
  };
}

module.exports = {
  createRateLimiter,
  authLimiter: createRateLimiter({ windowMs: 15 * 60 * 1000, max: 20, message: 'Too many authentication attempts. Please wait 15 minutes before trying again.' }),
  registerLimiter: createRateLimiter({ windowMs: 15 * 60 * 1000, max: 10, message: 'Too many accounts created from this IP. Please try again later.' }),
  resendLimiter: createRateLimiter({ windowMs: 2 * 60 * 1000, max: 3, message: 'Verification emails are rate-limited. Please wait 2 minutes before requesting another.' })
};
