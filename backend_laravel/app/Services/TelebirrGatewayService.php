<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AuditLog;

class TelebirrGatewayService
{
    protected string $appId;
    protected string $appKey;
    protected string $shortCode;

    public function __construct()
    {
        $this->appId = env('TELEBIRR_APP_ID', 'test_telebirr_app_id');
        $this->appKey = env('TELEBIRR_APP_KEY', 'test_telebirr_app_key');
        $this->shortCode = env('TELEBIRR_SHORT_CODE', '100124');
    }

    /**
     * Initialize Telebirr Payment Session & H5 Redirect.
     */
    public function initialize(Booking $booking, float $amount): array
    {
        $outTradeNo = 'TX-TB-' . time() . '-' . rand(1000, 9999);

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'id' => 'pay-' . $booking->id,
                'transaction_id' => $outTradeNo,
                'payment_method' => 'telebirr',
                'amount_etb' => $amount,
                'amount_usd' => round($amount / 64),
                'claimed_amount_etb' => $amount,
                'status' => 'pending',
                'submitted_at' => now(),
            ]
        );

        $payUrl = "https://telebirr.ethiopia.et/h5/pay?outTradeNo={$outTradeNo}&amount={$amount}";
        $qrCodeData = "telebirr://pay?order={$outTradeNo}&amount={$amount}&merchant={$this->shortCode}";

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $booking->guest_id,
            'user_name' => $booking->guest?->full_name ?: 'Guest',
            'user_role' => 'guest',
            'action' => 'telebirr_payment_init',
            'resource_type' => 'payment',
            'resource_id' => $payment->id,
            'details' => ['outTradeNo' => $outTradeNo, 'amount_etb' => $amount],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'status' => 'success',
            'outTradeNo' => $outTradeNo,
            'checkout_url' => $payUrl,
            'qr_code_data' => $qrCodeData,
            'amount' => $amount,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * Process Telebirr IPN Callback.
     */
    public function processWebhook(array $payload): array
    {
        $outTradeNo = $payload['outTradeNo'] ?? null;
        $tradeStatus = $payload['tradeStatus'] ?? null;

        if (!$outTradeNo) {
            throw new \Exception('outTradeNo missing from Telebirr webhook');
        }

        $payment = Payment::where('transaction_id', $outTradeNo)->first();
        if (!$payment) {
            throw new \Exception("Telebirr payment for order {$outTradeNo} not found");
        }

        if ($tradeStatus === 'COMPLETED' || $tradeStatus === 'SUCCESS') {
            $payment->update([
                'status' => 'verified',
                'paid_at' => now(),
            ]);

            $booking = Booking::find($payment->booking_id);
            if ($booking) {
                $booking->update([
                    'booking_status' => 'confirmed',
                    'payment_status' => 'paid',
                ]);
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => 'system-webhook',
                'user_name' => 'Telebirr IPN Webhook',
                'user_role' => 'system',
                'action' => 'telebirr_payment_cleared',
                'resource_type' => 'payment',
                'resource_id' => $payment->id,
                'details' => ['outTradeNo' => $outTradeNo, 'amount' => $payload['totalAmount'] ?? $payment->amount_etb],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return ['code' => 0, 'message' => 'success'];
        }

        return ['code' => -1, 'message' => 'Telebirr payment incomplete'];
    }
}
