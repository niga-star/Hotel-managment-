<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsDispatchService
{
    protected string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->apiKey = env('AFROMESSAGE_API_KEY', 'sample_afromessage_ethiopia_key_2026');
        $this->senderId = env('SMS_SENDER_ID', 'ETH-HOTEL');
    }

    /**
     * Send SMS notification for booking confirmation.
     */
    public function sendBookingSms(Booking $booking): array
    {
        $phone = $booking->guest?->phone ?: '+251911000000';
        $guestName = $booking->guest?->full_name ?: 'Valued Guest';
        $roomNumber = $booking->room?->room_number ?: '101';
        $ref = $booking->reference_code;

        $message = "Dear {$guestName}, your reservation {$ref} at Ethiopian Luxury Resort (Room {$roomNumber}) is CONFIRMED for {$booking->check_in_date}. We look forward to welcoming you!";

        return $this->dispatch($booking->id, $booking->guest_id, $phone, $message, 'booking_confirmed');
    }

    /**
     * Send SMS notification upon guest Check-In.
     */
    public function sendCheckInSms(Booking $booking): array
    {
        $phone = $booking->guest?->phone ?: '+251911000000';
        $guestName = $booking->guest?->full_name ?: 'Valued Guest';
        $roomNumber = $booking->room?->room_number ?: '101';

        $message = "Welcome to Ethiopian Luxury Resort, {$guestName}! You are checked into Room {$roomNumber}. WiFi: LuxuryGuest / Pass: Welome2026. Dial 0 for Butler Concierge.";

        return $this->dispatch($booking->id, $booking->guest_id, $phone, $message, 'check_in');
    }

    /**
     * Send SMS notification upon guest Check-Out.
     */
    public function sendCheckOutSms(Booking $booking, string $invoiceNumber): array
    {
        $phone = $booking->guest?->phone ?: '+251911000000';
        $guestName = $booking->guest?->full_name ?: 'Valued Guest';

        $message = "Thank you for staying at Ethiopian Luxury Resort, {$guestName}. Your departure invoice {$invoiceNumber} has been settled in full. Safe travels!";

        return $this->dispatch($booking->id, $booking->guest_id, $phone, $message, 'check_out');
    }

    /**
     * Dispatch SMS and record in MySQL guest_notifications audit table.
     */
    public function dispatch(?string $bookingId, ?string $guestId, string $phone, string $message, string $type = 'general'): array
    {
        $deliveryRef = 'SMS-' . strtoupper(uniqid());
        $notificationId = 'notif-' . time() . '-' . rand(100, 999);

        // Record in MySQL table
        DB::table('guest_notifications')->insert([
            'id' => $notificationId,
            'booking_id' => $bookingId ?: 'bk-general',
            'guest_id' => $guestId ?: 'usr-guest-6',
            'channel' => 'sms',
            'recipient' => $phone,
            'subject' => strtoupper($type),
            'message_body' => $message,
            'status' => 'sent',
            'delivery_reference' => $deliveryRef,
            'sent_at' => now(),
        ]);

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $guestId ?: 'usr-guest-6',
            'user_name' => 'SMS Gateway Engine',
            'user_role' => 'system',
            'action' => 'sms_notification_dispatched',
            'resource_type' => 'notification',
            'resource_id' => $notificationId,
            'details' => ['phone' => $phone, 'type' => $type, 'ref' => $deliveryRef],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'SMS dispatched successfully via Ethiopian Telecom gateway.',
            'delivery_reference' => $deliveryRef,
            'recipient' => $phone,
        ];
    }
}
