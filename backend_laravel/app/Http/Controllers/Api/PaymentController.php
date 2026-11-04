<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\Room;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Refund;
use App\Models\Invoice;
use App\Models\BookingServiceCharge;
use App\Services\ChapaGatewayService;
use App\Services\TelebirrGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected ChapaGatewayService $chapa;
    protected TelebirrGatewayService $telebirr;

    public function __construct(ChapaGatewayService $chapa, TelebirrGatewayService $telebirr)
    {
        $this->chapa = $chapa;
        $this->telebirr = $telebirr;
    }
    /**
     * List all payments with SLA calculation, filtering, and aggregate KPI metrics.
     */
    public function index(Request $request)
    {
        $filter = $request->input('filter');
        $method = $request->input('method');
        $status = $request->input('status');
        $search = $request->input('search');

        $query = Payment::with(['booking.guest', 'booking.room.category']);

        if ($filter === 'revenue' || $filter === 'verified') {
            $query->where('status', 'verified');
        } elseif ($filter === 'telebirr') {
            $query->where('payment_method', 'telebirr');
        } elseif ($filter === 'cbe_birr') {
            $query->where('payment_method', 'cbe_birr');
        } elseif ($filter === 'chapa') {
            $query->where('payment_method', 'chapa');
        } elseif ($filter === 'pending') {
            $query->where('status', 'pending_verification');
        } elseif ($filter === 'rejected') {
            $query->where('status', 'rejected');
        }

        if ($method && $method !== 'all') {
            $query->where('payment_method', $method);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('booking', function ($bq) use ($search) {
                      $bq->where('reference_code', 'like', "%{$search}%")
                         ->orWhereHas('guest', function ($gq) use ($search) {
                             $gq->where('full_name', 'like', "%{$search}%");
                         })
                         ->orWhereHas('room', function ($rq) use ($search) {
                             $rq->where('room_number', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $query->orderByRaw("CASE WHEN status = 'pending_verification' THEN 0 ELSE 1 END")
              ->orderBy('submitted_at', 'asc')
              ->orderBy('created_at', 'desc');

        $payments = $query->get();

        $now = Carbon::now();
        $overdueCount = 0;

        $enriched = $payments->map(function ($p) use ($now, &$overdueCount) {
            $submitted = $p->submitted_at ? Carbon::parse($p->submitted_at) : ($p->created_at ? Carbon::parse($p->created_at) : $now);
            $minutesElapsed = max(0, $submitted->diffInMinutes($now));
            $targetMinutes = 20;
            $minutesRemaining = max(0, $targetMinutes - $minutesElapsed);
            $isOverdue = ($p->status === 'pending_verification' && $minutesElapsed > $targetMinutes);

            if ($isOverdue) {
                $overdueCount++;
            }

            return [
                'id' => $p->id,
                'transaction_id' => $p->transaction_id,
                'booking_id' => $p->booking_id,
                'booking_ref' => $p->booking?->reference_code ?: '#ETH-00000',
                'payment_method' => $p->payment_method,
                'method' => $p->payment_method,
                'amount_etb' => (float)$p->amount_etb,
                'amount_usd' => (float)$p->amount_usd,
                'claimed_amount_etb' => (float)($p->claimed_amount_etb ?: $p->amount_etb),
                'status' => $p->status,
                'receipt_url' => $p->receipt_url,
                'submitted_at' => $p->submitted_at,
                'paid_at' => $p->paid_at,
                'rejection_reason' => $p->rejection_reason,
                'guest_name' => $p->booking?->guest?->full_name ?: 'Guest',
                'guest_phone' => $p->booking?->guest?->phone ?: 'N/A',
                'guest_email' => $p->booking?->guest?->email ?: 'N/A',
                'room_number' => $p->booking?->room?->room_number ?: 'N/A',
                'room_title' => $p->booking?->room?->title ?: 'Room',
                'category_name' => $p->booking?->room?->category?->name ?: 'Deluxe',
                'check_in_date' => $p->booking?->check_in_date,
                'check_out_date' => $p->booking?->check_out_date,
                'review_target_sla' => '20 minutes',
                'minutes_elapsed' => $minutesElapsed,
                'minutes_remaining' => $minutesRemaining,
                'is_overdue' => $isOverdue,
                'sla' => [
                    'target_minutes' => $targetMinutes,
                    'minutes_elapsed' => $minutesElapsed,
                    'minutes_remaining' => $minutesRemaining,
                    'is_overdue' => $isOverdue,
                    'submitted_at' => $p->submitted_at,
                ],
            ];
        });

        // Compute aggregate metrics
        $totalRev = (float)Payment::where('status', 'verified')->sum('amount_etb');
        $tbRev = (float)Payment::where('payment_method', 'telebirr')->where('status', 'verified')->sum('amount_etb');
        $tbCount = Payment::where('payment_method', 'telebirr')->where('status', 'verified')->count();
        $cbeRev = (float)Payment::where('payment_method', 'cbe_birr')->where('status', 'verified')->sum('amount_etb');
        $cbeCount = Payment::where('payment_method', 'cbe_birr')->where('status', 'verified')->count();
        $chapaRev = (float)Payment::where('payment_method', 'chapa')->where('status', 'verified')->sum('amount_etb');
        $chapaCount = Payment::where('payment_method', 'chapa')->where('status', 'verified')->count();
        $pendingCount = Payment::where('status', 'pending_verification')->count();
        $rejectedCount = Payment::where('status', 'rejected')->count();

        $metricsData = [
            'total_revenue_etb' => $totalRev,
            'revenue_etb' => $totalRev,
            'telebirr_total_etb' => $tbRev,
            'telebirr_count' => $tbCount,
            'cbe_birr_total_etb' => $cbeRev,
            'cbe_birr_count' => $cbeCount,
            'chapa_total_etb' => $chapaRev,
            'chapa_count' => $chapaCount,
            'pending_verification_count' => $pendingCount,
            'rejected_count' => $rejectedCount,
            'overdue_reviews_count' => $overdueCount,
        ];

        return response()->json([
            'payments' => $enriched,
            'metrics' => $metricsData,
            'kpis' => $metricsData,
        ]);
    }

    /**
     * Upload Guest Payment Receipt / Proof of Transfer screenshot.
     */
    public function uploadReceipt(Request $request)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'booking_id' => 'nullable|string',
            'payment_id' => 'nullable|string',
            'claimed_amount_etb' => 'nullable|numeric',
            'transaction_reference' => 'nullable|string',
        ]);

        $bookingId = $request->input('booking_id');
        $paymentId = $request->input('payment_id');
        $claimedAmount = $request->input('claimed_amount_etb');
        $txRef = $request->input('transaction_reference');

        if (!$bookingId && !$paymentId) {
            return response()->json(['error' => 'booking_id or payment_id is required.'], 400);
        }

        $file = $request->file('receipt');
        $filename = 'receipt-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $destinationPath = public_path('uploads/receipts');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }
        $file->move($destinationPath, $filename);
        $receiptUrl = '/uploads/receipts/' . $filename;

        // Locate or create payment
        $payment = null;
        if ($paymentId) {
            $payment = Payment::where('id', $paymentId)->orWhere('transaction_id', $paymentId)->first();
        }
        if (!$payment && $bookingId) {
            $payment = Payment::where('booking_id', $bookingId)->first();
        }

        if ($payment) {
            $payment->update([
                'receipt_url' => $receiptUrl,
                'status' => 'pending_verification',
                'submitted_at' => now(),
                'claimed_amount_etb' => $claimedAmount ?: $payment->claimed_amount_etb,
                'transaction_id' => $txRef ?: $payment->transaction_id,
            ]);
            $effectiveBookingId = $payment->booking_id;
        } else {
            $payment = Payment::create([
                'id' => 'pay-' . ($bookingId ?: time()),
                'transaction_id' => $txRef ?: ('TX-' . rand(10000, 99999)),
                'booking_id' => $bookingId,
                'payment_method' => 'telebirr',
                'amount_etb' => $claimedAmount ?: 3500,
                'amount_usd' => round(($claimedAmount ?: 3500) / 64),
                'claimed_amount_etb' => $claimedAmount ?: 3500,
                'receipt_url' => $receiptUrl,
                'status' => 'pending_verification',
                'submitted_at' => now(),
            ]);
            $effectiveBookingId = $bookingId;
        }

        // Update booking state
        if ($effectiveBookingId) {
            Booking::where('id', $effectiveBookingId)
                ->orWhere('reference_code', $effectiveBookingId)
                ->update([
                    'booking_status' => 'pending_verification',
                    'payment_status' => 'pending_verification',
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment receipt uploaded successfully and submitted for admin review.',
            'receipt_url' => $receiptUrl,
            'payment' => $payment,
        ], 201);
    }

    /**
     * Admin Review & Approval / Rejection Workflow.
     */
    public function review(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string',
        ]);

        $action = $validated['action'];
        $rejectionReason = $validated['rejection_reason'] ?? null;
        $reviewerId = $request->user()?->id ?: 'usr-admin-1';

        return DB::transaction(function () use ($id, $action, $rejectionReason, $reviewerId, $request) {
            $payment = Payment::where('id', $id)
                ->orWhere('transaction_id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            $booking = Booking::with('room', 'guest')
                ->where('id', $payment->booking_id)
                ->lockForUpdate()
                ->first();

            if ($action === 'approve') {
                $payment->update([
                    'status' => 'verified',
                    'paid_at' => now(),
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);

                if ($booking) {
                    $booking->update([
                        'booking_status' => 'confirmed',
                        'payment_status' => 'paid',
                    ]);
                }

                AuditLog::create([
                    'id' => 'aud-' . time() . '-' . rand(100, 999),
                    'user_id' => $reviewerId,
                    'user_name' => $request->user()?->full_name ?: 'System Admin',
                    'user_role' => $request->user()?->role ?: 'admin',
                    'action' => 'payment_approved',
                    'resource_type' => 'payment',
                    'resource_id' => $payment->id,
                    'details' => [
                        'transaction_id' => $payment->transaction_id,
                        'amount_etb' => $payment->amount_etb,
                        'booking_ref' => $booking?->reference_code,
                    ],
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment approved and booking confirmed successfully.',
                    'payment' => ['id' => $payment->id, 'status' => 'verified'],
                    'booking' => ['id' => $booking?->id, 'booking_status' => 'confirmed', 'payment_status' => 'paid'],
                ]);
            } else {
                $payment->update([
                    'status' => 'rejected',
                    'rejection_reason' => trim($rejectionReason),
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                ]);

                if ($booking) {
                    $booking->update([
                        'booking_status' => 'rejected',
                        'payment_status' => 'rejected',
                        'hold_expires_at' => null,
                        'notes' => ($booking->notes ? $booking->notes . "\n" : '') . '[Payment Rejected] ' . trim($rejectionReason),
                    ]);

                    if ($booking->room && $booking->room->status !== 'maintenance') {
                        $booking->room->update(['status' => 'available']);
                    }
                }

                AuditLog::create([
                    'id' => 'aud-' . time() . '-' . rand(100, 999),
                    'user_id' => $reviewerId,
                    'user_name' => $request->user()?->full_name ?: 'System Admin',
                    'user_role' => $request->user()?->role ?: 'admin',
                    'action' => 'payment_rejected',
                    'resource_type' => 'payment',
                    'resource_id' => $payment->id,
                    'details' => [
                        'transaction_id' => $payment->transaction_id,
                        'reason' => $rejectionReason,
                        'booking_ref' => $booking?->reference_code,
                    ],
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment rejected. Room has been automatically released and made available.',
                    'rejection_reason' => trim($rejectionReason),
                    'payment' => ['id' => $payment->id, 'status' => 'rejected'],
                    'booking' => ['id' => $booking?->id, 'booking_status' => 'rejected', 'payment_status' => 'rejected'],
                ]);
            }
        });
    }

    /**
     * Verify payment directly.
     */
    public function verify(Request $request, $id)
    {
        $payment = Payment::where('id', $id)->orWhere('transaction_id', $id)->firstOrFail();
        $payment->update([
            'status' => 'verified',
            'paid_at' => now(),
            'reviewed_by' => $request->user()?->id ?: 'usr-admin-1',
            'reviewed_at' => now(),
        ]);

        if ($payment->booking) {
            $payment->booking->update([
                'booking_status' => 'confirmed',
                'payment_status' => 'paid',
            ]);
        }

        return response()->json(['message' => 'Payment verified successfully in MySQL.']);
    }

    /**
     * Simulate live webhook payment settlement for Telebirr / Chapa / CBE Birr.
     */
    public function simulateWebhook(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'gateway' => 'nullable|string',
            'amount' => 'nullable|numeric',
        ]);

        $txId = $validated['transaction_id'];
        $gateway = $validated['gateway'] ?? 'telebirr';
        $amount = (float)($validated['amount'] ?? 4500);

        return DB::transaction(function () use ($txId, $gateway, $amount, $request) {
            $payment = Payment::where('id', $txId)->orWhere('transaction_id', $txId)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'verified',
                    'paid_at' => now(),
                    'payment_method' => $gateway,
                ]);

                if ($payment->booking) {
                    $payment->booking->update([
                        'booking_status' => 'confirmed',
                        'payment_status' => 'paid',
                    ]);
                }
            }

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => 'system-webhook',
                'user_name' => 'Automated Payment Webhook',
                'user_role' => 'system',
                'action' => 'webhook_simulation_success',
                'resource_type' => 'payment',
                'resource_id' => $payment ? $payment->id : $txId,
                'details' => ['gateway' => $gateway, 'transaction_id' => $txId, 'amount' => $amount],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => "Simulated live {$gateway} webhook clearance successfully recorded in MySQL!",
                'result' => [
                    'transaction_id' => $txId,
                    'gateway' => $gateway,
                    'status' => 'verified',
                    'amount' => $amount,
                ],
            ]);
        });
    }

    /**
     * Chapa IPN Webhook Receiver.
     */
    public function chapaWebhook(Request $request)
    {
        $txRef = $request->input('tx_ref');
        $status = $request->input('status');

        if ($status === 'success' && $txRef) {
            $payment = Payment::where('transaction_id', $txRef)->first();
            if ($payment) {
                $payment->update(['status' => 'verified', 'paid_at' => now()]);
                if ($payment->booking) {
                    $payment->booking->update(['booking_status' => 'confirmed', 'payment_status' => 'paid']);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Telebirr IPN Webhook Receiver.
     */
    public function telebirrWebhook(Request $request)
    {
        $outTradeNo = $request->input('outTradeNo');
        $tradeStatus = $request->input('tradeStatus');

        if ($tradeStatus === 'COMPLETED' && $outTradeNo) {
            $payment = Payment::where('transaction_id', $outTradeNo)->first();
            if ($payment) {
                $payment->update(['status' => 'verified', 'paid_at' => now()]);
                if ($payment->booking) {
                    $payment->booking->update(['booking_status' => 'confirmed', 'payment_status' => 'paid']);
                }
            }
        }

        return response()->json(['code' => 0, 'message' => 'success']);
    }

    /**
     * CBE Birr Reconciliation Receiver.
     */
    public function cbeBirrWebhook(Request $request)
    {
        $txRef = $request->input('tx_ref');
        if ($txRef) {
            $payment = Payment::where('transaction_id', $txRef)->first();
            if ($payment) {
                $payment->update(['status' => 'verified', 'paid_at' => now()]);
                if ($payment->booking) {
                    $payment->booking->update(['booking_status' => 'confirmed', 'payment_status' => 'paid']);
                }
            }
        }

        return response()->json(['status' => 'success', 'message' => 'CBE Birr payment reconciled in MySQL.']);
    }

    /**
     * Initialize live gateway payment session (Chapa or Telebirr).
     */
    public function initializeGateway(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'gateway' => 'nullable|string|in:chapa,telebirr',
            'return_url' => 'nullable|string',
        ]);

        $booking = Booking::with(['guest', 'room'])
            ->where('id', $validated['booking_id'])
            ->orWhere('reference_code', $validated['booking_id'])
            ->firstOrFail();

        $gateway = $validated['gateway'] ?? 'telebirr';
        $amount = (float)$booking->total_price_etb;

        if ($gateway === 'chapa') {
            $result = $this->chapa->initialize($booking, $amount, $validated['return_url'] ?? null);
        } else {
            $result = $this->telebirr->initialize($booking, $amount);
        }

        return response()->json($result);
    }

    /**
     * Generate unique sequential payment receipt number.
     */
    protected function generateReceiptNumber(): string
    {
        $year = date('Y');
        $count = Payment::whereNotNull('receipt_number')
            ->where('receipt_number', 'like', "RCP-{$year}-%")
            ->count() + 1;
        $candidate = sprintf("RCP-%s-%05d", $year, $count);
        while (Payment::where('receipt_number', $candidate)->exists()) {
            $count++;
            $candidate = sprintf("RCP-%s-%05d", $year, $count);
        }
        return $candidate;
    }

    /**
     * Record a new front-desk payment (Cash, Card, Bank Transfer, Telebirr, CBE Birr).
     */
    public function recordPayment(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'amount_etb' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,telebirr,cbe_birr,chapa,card,credit_card,debit_card,other',
            'transaction_reference' => 'nullable|string',
            'is_advance_deposit' => 'nullable|boolean',
            'idempotency_key' => 'nullable|string|max:128',
            'notes' => 'nullable|string',
            'receipt_number' => 'nullable|string',
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = Payment::with(['booking.guest', 'booking.room'])
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already recorded (idempotent submission).',
                    'is_duplicate' => true,
                    'payment' => $existing,
                    'receipt_number' => $existing->receipt_number,
                    'outstanding_balance_etb' => $existing->booking?->outstanding_balance_etb ?? 0,
                ], 200);
            }
        }

        return DB::transaction(function () use ($validated, $request, $idempotencyKey) {
            $booking = Booking::with(['guest', 'room', 'payments', 'serviceCharges'])
                ->where('id', $validated['booking_id'])
                ->orWhere('reference_code', $validated['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $amountEtb = (float)$validated['amount_etb'];
            $amountUsd = round($amountEtb / 64, 2);
            $receiptNumber = $validated['receipt_number'] ?? $this->generateReceiptNumber();
            $staffId = $request->user()?->id ?: 'usr-frontdesk-1';
            $staffName = $request->user()?->full_name ?: 'Front Desk Reception';
            $staffRole = $request->user()?->role ?: 'receptionist';

            $txId = $validated['transaction_reference'] ?: ('TX-' . date('Ymd') . '-' . rand(10000, 99999));

            $payment = Payment::create([
                'id' => 'pay-' . time() . '-' . rand(100, 999),
                'transaction_id' => $txId,
                'receipt_number' => $receiptNumber,
                'booking_id' => $booking->id,
                'payment_method' => $validated['payment_method'],
                'is_advance_deposit' => (bool)($validated['is_advance_deposit'] ?? false),
                'amount_etb' => $amountEtb,
                'amount_usd' => $amountUsd,
                'status' => 'verified',
                'paid_at' => now(),
                'processed_by' => $staffId,
                'reviewed_by' => $staffId,
                'reviewed_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Refresh booking payments and recalculate balance
            $booking->load('payments', 'serviceCharges');
            $grossTotal = (float)$booking->gross_total_etb;
            $verifiedPaid = (float)$booking->payments
                ->filter(fn($p) => in_array($p->status, ['verified', 'completed']))
                ->sum(fn($p) => max(0, (float)$p->amount_etb - (float)($p->refunded_amount_etb ?? 0)));
            $outstanding = max(0, $grossTotal - $verifiedPaid);

            $newPaymentStatus = ($outstanding <= 0) ? 'paid' : 'partially_paid';
            $updateData = ['payment_status' => $newPaymentStatus];
            if ($booking->booking_status === 'pending_verification') {
                $updateData['booking_status'] = 'confirmed';
            }
            $booking->update($updateData);

            AuditLog::create([
                'id' => 'aud-' . time() . '-' . rand(100, 999),
                'user_id' => $staffId,
                'user_name' => $staffName,
                'user_role' => $staffRole,
                'action' => 'payment_recorded',
                'resource_type' => 'payment',
                'resource_id' => $payment->id,
                'details' => [
                    'booking_id' => $booking->id,
                    'reference_code' => $booking->reference_code,
                    'amount_etb' => $amountEtb,
                    'method' => $validated['payment_method'],
                    'receipt_number' => $receiptNumber,
                    'is_advance_deposit' => (bool)($validated['is_advance_deposit'] ?? false),
                    'outstanding_balance_etb' => $outstanding,
                ],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successfully recorded and verified.',
                'payment' => $payment->fresh(['booking.guest', 'booking.room']),
                'receipt_number' => $receiptNumber,
                'gross_total_etb' => $grossTotal,
                'total_paid_etb' => $verifiedPaid,
                'outstanding_balance_etb' => $outstanding,
                'payment_status' => $newPaymentStatus,
            ], 201);
        });
    }

    /**
     * Prevent hard deletion of completed financial transactions.
     */
    public function destroy($id)
    {
        return response()->json([
            'error' => 'Completed financial transactions cannot be deleted. Use refund or reversal workflows to ensure audit integrity.'
        ], 403);
    }
}
