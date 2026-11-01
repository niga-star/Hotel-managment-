/**
 * Backend Validation Utilities
 * Strict validation for Full Name, Email, International Phone, and Strong Password
 */

/**
 * Validates Full Name:
 * - 3 to 30 characters after trimming
 * - Alphabetic characters only (including Unicode letters for international names)
 * - Single space between words allowed (no leading, trailing, or multiple consecutive spaces)
 * - Rejects numbers, emojis, special characters
 */
function validateFullName(rawName) {
  if (typeof rawName !== 'string') {
    const msg = 'Full Name must be a string.';
    return { valid: false, error: msg, message: msg };
  }

  // Normalize spaces: replace multiple spaces with single space, trim ends
  const trimmed = rawName.trim().replace(/\s+/g, ' ');

  if (trimmed.length < 3) {
    const msg = 'Full Name must be at least 3 characters long.';
    return { valid: false, error: msg, message: msg };
  }

  if (trimmed.length > 30) {
    const msg = 'Full Name cannot exceed 30 characters.';
    return { valid: false, error: msg, message: msg };
  }

  // Regex matching Unicode letters and single spaces between words:
  // ^[\p{L}]+(?: [\p{L}]+)*$
  const nameRegex = /^[\p{L}]+(?: [\p{L}]+)*$/u;
  if (!nameRegex.test(trimmed)) {
    const msg = 'Full Name must contain alphabetic letters only (no numbers, emojis, or special symbols).';
    return { valid: false, error: msg, message: msg };
  }

  return { valid: true, normalized: trimmed, normalizedName: trimmed };
}

/**
 * Validates Email:
 * - Standard syntax check
 * - Lowercases and trims
 */
function validateEmail(rawEmail) {
  if (typeof rawEmail !== 'string') {
    const msg = 'Email address must be a string.';
    return { valid: false, error: msg, message: msg };
  }

  const trimmed = rawEmail.trim().toLowerCase();
  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

  if (!emailRegex.test(trimmed)) {
    const msg = 'Please enter a valid email address.';
    return { valid: false, error: msg, message: msg };
  }

  if (trimmed.length > 160) {
    const msg = 'Email address cannot exceed 160 characters.';
    return { valid: false, error: msg, message: msg };
  }

  return { valid: true, normalized: trimmed, normalizedEmail: trimmed };
}

/**
 * Validates Strong Password:
 * - At least 8 characters
 * - At least one uppercase letter (A-Z)
 * - At least one lowercase letter (a-z)
 * - At least one digit (0-9)
 * - At least one special character (!@#$%^&* etc.)
 */
function validatePassword(password) {
  if (typeof password !== 'string') {
    const msg = 'Password must be a string.';
    return { valid: false, error: msg, message: msg };
  }

  const checks = {
    minLength: password.length >= 8,
    hasUpper: /[A-Z]/.test(password),
    hasLower: /[a-z]/.test(password),
    hasDigit: /[0-9]/.test(password),
    hasSpecial: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(password)
  };

  const missing = [];
  if (!checks.minLength) missing.push('at least 8 characters');
  if (!checks.hasUpper) missing.push('one uppercase letter (A–Z)');
  if (!checks.hasLower) missing.push('one lowercase letter (a–z)');
  if (!checks.hasDigit) missing.push('one number (0–9)');
  if (!checks.hasSpecial) missing.push('one special character (e.g. @, #, $, %, !, &)');

  if (missing.length > 0) {
    const msg = `Password must contain ${missing.join(', ')}.`;
    return {
      valid: false,
      error: msg,
      message: msg,
      checks,
      rules: checks
    };
  }

  return { valid: true, checks, rules: checks };
}

/**
 * Validates and Normalizes International Phone Number to E.164 format:
 * - Accepts numbers with or without leading +
 * - Cleans formatting characters (spaces, dashes, parentheses)
 * - Ensures digits only after leading +
 * - Length between 7 and 15 digits (ITU-T E.164 standard)
 */
function validatePhoneNumber(rawPhone, dialCode = '+251') {
  if (typeof rawPhone !== 'string') {
    const msg = 'Phone number must be a string.';
    return { valid: false, error: msg, message: msg };
  }

  // Remove spaces, dashes, dots, parentheses
  let cleaned = rawPhone.trim().replace(/[\s\-\(\)\.]/g, '');

  if (!cleaned) {
    const msg = 'Phone number is required.';
    return { valid: false, error: msg, message: msg };
  }

  // Reject alphabetic characters or unexpected symbols
  if (/[^\d+]/.test(cleaned)) {
    const msg = 'Phone number must contain digits only.';
    return { valid: false, error: msg, message: msg };
  }

  // Normalize with international dial code if missing leading +
  let normalized;
  if (cleaned.startsWith('+')) {
    normalized = cleaned;
  } else if (cleaned.startsWith('00')) {
    normalized = '+' + cleaned.substring(2);
  } else if (cleaned.startsWith('0')) {
    const baseCode = dialCode.startsWith('+') ? dialCode : `+${dialCode}`;
    normalized = `${baseCode}${cleaned.substring(1)}`;
  } else {
    const baseCode = dialCode.startsWith('+') ? dialCode : `+${dialCode}`;
    normalized = `${baseCode}${cleaned}`;
  }

  // E.164 regex: + followed by 7 to 15 digits
  const e164Regex = /^\+[1-9]\d{6,14}$/;
  if (!e164Regex.test(normalized)) {
    const msg = 'Please enter a valid international phone number for the selected country (7 to 15 digits).';
    return {
      valid: false,
      error: msg,
      message: msg
    };
  }

  return { valid: true, normalized, e164: normalized };
}

module.exports = {
  validateFullName,
  validateEmail,
  validatePassword,
  validatePhoneNumber
};
