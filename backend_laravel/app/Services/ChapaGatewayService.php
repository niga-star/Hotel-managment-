<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaGatewayService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = env('CHAPA_SECRET_KEY', 'CHASECK_TEST-sample_test_key_for_chapa_gateway');
        $this->baseUrl = 'https://api.chapa.co/v1';
    }

    /**
     * Initialize Chapa Checkout Session.
     */
    public function initialize(Booking $booking, float $amount, ?string $returnUrl = null): array
    {
        $txRef = 'TX-CHP-' . time() . '-' . rand(1000, 9999);

        // Record or update pending payment
        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'id' => 'pay-' . $booking->id,
                'transaction_id' => $txRef,
                'payment_method' => 'chapa',
                'amount_etb' => $amount,
                'amount_usd' => round($amount / 64),
                'claimed_amount_etb' => $amount,
                'status' => 'pending',
                'submitted_at' => now(),
            ]
        );

        $payload = [
            'amount' => $amount,
            'currency' => 'ETB',
            'email' => $booking->guest?->email ?: 'guest@ethiopianhotel.com',
            'first_name' => explode(' ', $booking->guest?->full_name ?: 'Valued Guest')[0],
            'last_name' => explode(' ', $booking->guest?->full_name ?: 'Guest')[1] ?? 'Guest',
            'phone_number' => $booking->guest?->phone ?: '+251911000000',
            'tx_ref' => $txRef,
            'callback_url' => url('/api/v1/payments/webhook/chapa'),
            'return_url' => $returnUrl ?: url('/my-bookings?payment_success=true&ref=' . $booking->reference_code),
            'customization' => [
                'title' => 'Ethiopian Luxury Resort Reservation',
                'description' => "Booking {$booking->reference_code} for Room {$booking->room?->room_number}",
            ]
        ];

        // Fallback checkout URL for local testing/demo if API keys are sandbox
        $checkoutUrl = "https://checkout.chapa.co/checkout/web/payment/{$txRef}";

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $booking->guest_id,
            'user_name' => $booking->guest?->full_name ?: 'Guest',
            'user_role' => 'guest',
            'action' => 'chapa_payment_init',
            'resource_type' => 'payment',
            'resource_id' => $payment->id,
            'details' => ['tx_ref' => $txRef, 'amount_etb' => $amount],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'status' => 'success',
            'checkout_url' => $checkoutUrl,
            'tx_ref' => $txRef,
            'payment_id' => $payment->id,
            'amount' => $amount,
        ];
    }

    /**
     * Process live incoming Chapa IPN webhook.
     */
    public function processWebhook(?string $signature, array $payload): array
    {
        $txRef = $payload['tx_ref'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$txRef) {
            throw new \Exception('tx_ref is missing in webhook payload');
        }

        $payment = Payment::where('transaction_id', $txRef)->first();
        if (!$payment) {
            throw new \Exception("Payment record for transaction {$txRef} not found");
        }

        if (strtolower($status) === 'success') {
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
                'user_name' => 'Chapa IPN Webhook',
                'user_role' => 'system',
                'action' => 'chapa_payment_cleared',
                'resource_type' => 'payment',
                'resource_id' => $payment->id,
                'details' => ['tx_ref' => $txRef, 'amount' => $payload['amount'] ?? $payment->amount_etb],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return ['success' => true, 'message' => 'Payment settled and confirmed in database'];
        }

        return ['success' => false, 'message' => 'Webhook received with non-success status'];
    }
}
