require('dotenv').config();
const crypto = require('crypto');
const pool = require('../mysql_db');

class PaymentService {
  /**
   * Initialize a Chapa transaction for cards & local wallets
   */
  async initializeChapa({ bookingId, txRef, amount, email, firstName, lastName, phone }) {
    const returnUrl = `${process.env.BASE_URL || 'http://localhost:5000'}/?status=success&ref=${txRef}`;
    const callbackUrl = `${process.env.BASE_URL || 'http://localhost:5000'}/api/v1/payments/webhook/chapa`;

    const payload = {
      amount: amount.toString(),
      currency: 'ETB',
      email: email || 'guest@ethiopianhotel.com',
      first_name: firstName || 'Valued',
      last_name: lastName || 'Guest',
      phone_number: phone || '+251 911 000000',
      tx_ref: txRef,
      callback_url: callbackUrl,
      return_url: returnUrl,
      customization: {
        title: 'Ethiopian Boutique Hotel Stay',
        description: `Reservation payment for booking ${txRef}`
      }
    };

    // Store pending payment in MySQL
    await pool.query(`
      INSERT INTO payments (id, transaction_id, booking_id, payment_method, amount_etb, status, gateway_reference)
      VALUES (?, ?, ?, 'chapa', ?, 'pending', ?)
      ON DUPLICATE KEY UPDATE status = 'pending', gateway_reference = VALUES(gateway_reference)
    `, [`pay-${bookingId}`, txRef, bookingId, amount, `CHAPA-INIT-${txRef}`]);

    // Check if live Chapa key is available, else provide simulated hosted link
    const isLive = process.env.CHAPA_SECRET_KEY && !process.env.CHAPA_SECRET_KEY.includes('mock');
    let checkoutUrl = `${process.env.BASE_URL || 'http://localhost:5000'}/checkout/chapa?ref=${txRef}`;

    if (isLive) {
      try {
        const response = await fetch(`${process.env.CHAPA_API_URL || 'https://api.chapa.co/v1'}/transaction/initialize`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${process.env.CHAPA_SECRET_KEY}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.status === 'success' && result.data?.checkout_url) {
          checkoutUrl = result.data.checkout_url;
        }
      } catch (err) {
        console.warn('[PaymentService] Chapa live call failed, falling back to local gateway checkout:', err.message);
      }
    }

    return {
      status: 'success',
      checkout_url: checkoutUrl,
      transaction_id: txRef,
      gateway: 'chapa'
    };
  }

  /**
   * Initialize Telebirr USSD / H5 Payment
   */
  async initializeTelebirr({ bookingId, txRef, amount, phone }) {
    const telebirrOrderNo = `TB-${Date.now()}`;

    // Store in MySQL
    await pool.query(`
      INSERT INTO payments (id, transaction_id, booking_id, payment_method, amount_etb, status, gateway_reference)
      VALUES (?, ?, ?, 'telebirr', ?, 'pending', ?)
      ON DUPLICATE KEY UPDATE status = 'pending', gateway_reference = VALUES(gateway_reference)
    `, [`pay-${bookingId}`, txRef, bookingId, amount, telebirrOrderNo]);

    return {
      status: 'success',
      telebirr_order_no: telebirrOrderNo,
      transaction_id: txRef,
      ussd_code: `*127*${process.env.TELEBIRR_SHORT_CODE || '100223'}*${amount}#`,
      gateway: 'telebirr',
      qr_payload: JSON.stringify({
        scheme: 'telebirr',
        merchant: process.env.TELEBIRR_SHORT_CODE || '100223',
        order: telebirrOrderNo,
        amount: amount,
        ref: txRef
      })
    };
  }

  /**
   * Process and verify Chapa Webhook Notification
   */
  async processChapaWebhook(signature, body) {
    const rawBody = typeof body === 'string' ? body : JSON.stringify(body);

    // Verify HMAC SHA256 signature if secret is provided
    if (process.env.CHAPA_WEBHOOK_SECRET && signature) {
      const hash = crypto.createHmac('sha256', process.env.CHAPA_WEBHOOK_SECRET).update(rawBody).digest('hex');
      if (hash !== signature && !process.env.CHAPA_WEBHOOK_SECRET.includes('mock')) {
        throw new Error('Invalid Chapa webhook signature verification.');
      }
    }

    const data = typeof body === 'string' ? JSON.parse(body) : body;
    const txRef = data.tx_ref || data.reference;

    if (!txRef) {
      throw new Error('Missing tx_ref in Chapa webhook payload.');
    }

    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      // Update payment record in MySQL
      const [updateResult] = await conn.query(`
        UPDATE payments
        SET status = 'verified',
            gateway_reference = COALESCE(?, gateway_reference),
            raw_response = ?,
            paid_at = CURRENT_TIMESTAMP
        WHERE transaction_id = ? OR transaction_id = ?
      `, [data.reference || data.chapa_reference, rawBody, txRef, txRef.replace('TX-', '')]);

      // Update associated booking status
      await conn.query(`
        UPDATE bookings b
        JOIN payments p ON p.booking_id = b.id
        SET b.payment_status = 'paid',
            b.booking_status = 'confirmed'
        WHERE p.transaction_id = ? OR p.transaction_id = ?
      `, [txRef, txRef.replace('TX-', '')]);

      await conn.commit();
      console.log(`[Chapa Webhook] Successfully settled transaction ${txRef} in MySQL.`);
      return { success: true, txRef };
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  }

  /**
   * Process Telebirr IPN / Webhook Callback
   */
  async processTelebirrWebhook(payload) {
    const data = typeof payload === 'string' ? JSON.parse(payload) : payload;
    const txRef = data.outTradeNo || data.tx_ref;

    if (!txRef) {
      throw new Error('Missing outTradeNo in Telebirr webhook.');
    }

    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      await conn.query(`
        UPDATE payments
        SET status = 'verified',
            gateway_reference = ?,
            raw_response = ?,
            paid_at = CURRENT_TIMESTAMP
        WHERE transaction_id = ? OR gateway_reference = ?
      `, [data.tradeNo || data.telebirrOrderNo, JSON.stringify(data), txRef, txRef]);

      await conn.query(`
        UPDATE bookings b
        JOIN payments p ON p.booking_id = b.id
        SET b.payment_status = 'paid',
            b.booking_status = 'confirmed'
        WHERE p.transaction_id = ? OR p.gateway_reference = ?
      `, [txRef, txRef]);

      await conn.commit();
      console.log(`[Telebirr Webhook] Successfully settled transaction ${txRef} in MySQL.`);
      return { success: true, txRef };
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  }

  /**
   * Process CBE Birr / Bank Transfer Verification
   */
  async processCbeBirrVerification(txRef, cbeReference) {
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      await conn.query(`
        UPDATE payments
        SET status = 'verified',
            gateway_reference = ?,
            raw_response = ?,
            paid_at = CURRENT_TIMESTAMP
        WHERE transaction_id = ?
      `, [cbeReference || `CBE-VERIFIED-${Date.now()}`, JSON.stringify({ verified_by: 'Staff/Automated Reconciliation', cbeReference }), txRef]);

      await conn.query(`
        UPDATE bookings b
        JOIN payments p ON p.booking_id = b.id
        SET b.payment_status = 'paid',
            b.booking_status = 'confirmed'
        WHERE p.transaction_id = ?
      `, [txRef]);

      await conn.commit();
      return { success: true, txRef };
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  }
}

module.exports = new PaymentService();
