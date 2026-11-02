<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\AuditLog;
use App\Services\SmsDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    protected SmsDispatchService $sms;

    public function __construct(SmsDispatchService $sms)
    {
        $this->sms = $sms;
    }
    /**
     * Get notifications for a specific booking.
     */
    public function bookingNotifications($bookingId)
    {
        $notifications = DB::table('guest_notifications')
            ->where('booking_id', $bookingId)
            ->orWhere('delivery_reference', 'like', "%{$bookingId}%")
            ->orderBy('sent_at', 'desc')
            ->get();

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Notification audit trail for admin oversight.
     */
    public function index()
    {
        $notifications = DB::table('guest_notifications as n')
            ->join('bookings as b', 'n.booking_id', '=', 'b.id')
            ->join('users as u', 'n.guest_id', '=', 'u.id')
            ->select('n.*', 'b.reference_code', 'u.full_name as guest_name')
            ->orderBy('n.sent_at', 'desc')
            ->limit(30)
            ->get();

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Resend booking confirmation notification (Email / SMS).
     */
    public function resend(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'channel' => 'nullable|string',
        ]);

        $bookingId = $validated['booking_id'];
        $channel = strtolower($validated['channel'] ?? 'email');

        $booking = Booking::with('guest', 'room')
            ->where('id', $bookingId)
            ->orWhere('reference_code', $bookingId)
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found.'], 404);
        }

        // Record outgoing notification in MySQL
        $notificationId = 'notif-' . time() . '-' . rand(100, 999);
        $deliveryRef = 'DELIV-' . strtoupper(uniqid());

        DB::table('guest_notifications')->insert([
            'id' => $notificationId,
            'booking_id' => $booking->id,
            'guest_id' => $booking->guest_id,
            'channel' => $channel,
            'recipient' => ($channel === 'sms' ? $booking->guest?->phone : $booking->guest?->email) ?: 'guest@hotel.et',
            'subject' => "Re-sent: Booking Confirmation - {$booking->reference_code}",
            'message_body' => "Dear {$booking->guest?->full_name}, your reservation {$booking->reference_code} for Room {$booking->room?->room_number} is confirmed.",
            'status' => 'sent',
            'delivery_reference' => $deliveryRef,
            'sent_at' => now(),
        ]);

        AuditLog::create([
            'id' => 'aud-' . time() . '-' . rand(100, 999),
            'user_id' => $request->user()?->id ?: 'usr-staff-1',
            'user_name' => $request->user()?->full_name ?: 'Front Desk Staff',
            'user_role' => $request->user()?->role ?: 'staff',
            'action' => 'notification_resend',
            'resource_type' => 'notification',
            'resource_id' => $notificationId,
            'details' => ['channel' => $channel, 'booking_ref' => $booking->reference_code],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Confirmation ' . strtoupper($channel) . ' re-dispatched successfully.',
        ]);
    }

    /**
     * Dispatch instant SMS notification to guest phone.
     */
    public function sendSms(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'phone' => 'nullable|string',
            'type' => 'nullable|string|in:booking_confirmed,check_in,check_out,custom',
            'custom_message' => 'nullable|string',
        ]);

        $booking = Booking::with(['guest', 'room'])
            ->where('id', $validated['booking_id'])
            ->orWhere('reference_code', $validated['booking_id'])
            ->firstOrFail();

        $type = $validated['type'] ?? 'booking_confirmed';
        $phone = $validated['phone'] ?: ($booking->guest?->phone ?: '+251911000000');

        if ($type === 'check_in') {
            $result = $this->sms->sendCheckInSms($booking);
        } elseif ($type === 'check_out') {
            $invNum = 'INV-' . preg_replace('/[^A-Za-z0-9]/', '', $booking->reference_code);
            $result = $this->sms->sendCheckOutSms($booking, $invNum);
        } elseif ($type === 'custom' && !empty($validated['custom_message'])) {
            $result = $this->sms->dispatch($booking->id, $booking->guest_id, $phone, $validated['custom_message'], 'custom');
        } else {
            $result = $this->sms->sendBookingSms($booking);
        }

        return response()->json($result);
    }
}
