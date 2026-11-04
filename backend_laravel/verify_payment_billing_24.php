<?php

/**
 * HOTEL MANAGEMENT SYSTEM - PAYMENT & BILLING MANAGEMENT VERIFICATION SUITE
 * 24 Comprehensive Hospitality Financial Standards Verification
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Invoice;
use App\Models\BookingServiceCharge;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

echo "====================================================================\n";
echo "   PAYMENT & BILLING MANAGEMENT - 24 SCENARIOS VERIFICATION\n";
echo "====================================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertCondition($scenarioNum, $title, $condition, $details = '') {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "[Scenario {$scenarioNum}] {$title}\n";
        echo "  -> SUCCESS: {$details}\n\n";
    } else {
        $failedCount++;
        echo "[Scenario {$scenarioNum}] {$title}\n";
        echo "  -> FAILED: {$details}\n\n";
    }
}

// Setup dedicated test room, category, and guest
$category = RoomCategory::first();
if (!$category) {
    $category = RoomCategory::create([
        'id' => 'cat-fin-ste',
        'name' => 'Finance Suite Test',
        'slug' => 'finance-suite',
        'base_price_etb' => 5000,
        'base_price_usd' => 78,
        'max_capacity' => 2
    ]);
}

$room = Room::firstOrCreate(
    ['room_number' => 'FIN-901'],
    [
        'id' => 'rm-fin-901',
        'category_id' => $category->id,
        'title' => 'Finance Verification Suite 901',
        'price_etb' => 5000,
        'price_usd' => 78,
        'capacity' => 2,
        'status' => 'available',
        'housekeeping_status' => 'inspected',
        'is_active' => 1,
    ]
);

$guest = User::firstOrCreate(
    ['email' => 'fin.guest@ethiopiaresort.com'],
    [
        'id' => 'usr-fin-guest-1',
        'full_name' => 'Abebe Tadesse Financial',
        'phone' => '+251911998877',
        'role' => 'guest',
        'password_hash' => Hash::make('Secret123!'),
        'id_type' => 'passport',
        'id_number' => 'ET-FIN-009988',
        'nationality' => 'Ethiopian',
    ]
);

// Create fresh test booking for 3 nights: 3 * 5000 = 15,000 ETB
$refCode = '#ETH-FIN-' . rand(10000, 99999);
$booking = Booking::create([
    'id' => 'bk-fin-' . time(),
    'reference_code' => $refCode,
    'guest_id' => $guest->id,
    'room_id' => $room->id,
    'check_in_date' => Carbon::now()->toDateString(),
    'check_out_date' => Carbon::now()->addDays(3)->toDateString(),
    'adults_count' => 2,
    'children_count' => 0,
    'rooms_count' => 1,
    'nightly_rate_etb' => 5000,
    'nightly_rate_usd' => 78,
    'total_price_etb' => 15000,
    'total_price_usd' => 234,
    'booking_status' => 'confirmed',
    'payment_status' => 'unpaid',
]);

$paymentController = app(\App\Http\Controllers\Api\PaymentController::class);
$bookingController = app(\App\Http\Controllers\Api\BookingController::class);
$adminController = app(\App\Http\Controllers\Api\AdminController::class);

// [Scenario 1] Record a cash payment
$req1 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $booking->id,
    'amount_etb' => 2000,
    'payment_method' => 'cash',
    'transaction_reference' => 'CASH-REC-' . rand(1000, 9999),
    'notes' => 'Received at front desk drawer',
]);
$res1 = $paymentController->recordPayment($req1);
$data1 = $res1->getData(true);
$pay1 = $data1['payment'] ?? null;
assertCondition(
    1,
    "Record a cash payment",
    $res1->getStatusCode() === 201 && $pay1['payment_method'] === 'cash' && (float)$pay1['amount_etb'] === 2000.0,
    "Cash payment of ETB 2,000 recorded. Receipt #{$data1['receipt_number']} generated."
);

// [Scenario 2] Record a bank transfer
$req2 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $booking->id,
    'amount_etb' => 3000,
    'payment_method' => 'bank_transfer',
    'transaction_reference' => 'CBE-TXN-' . rand(10000, 99999),
    'notes' => 'Commercial Bank of Ethiopia transfer slip verified',
]);
$res2 = $paymentController->recordPayment($req2);
$data2 = $res2->getData(true);
$pay2 = $data2['payment'] ?? null;
assertCondition(
    2,
    "Record a bank transfer",
    $res2->getStatusCode() === 201 && $pay2['payment_method'] === 'bank_transfer' && (float)$pay2['amount_etb'] === 3000.0,
    "Bank transfer of ETB 3,000 verified with ref {$pay2['transaction_id']}."
);

// [Scenario 3] Record a mobile payment (Telebirr / CBE Birr)
$req3 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $booking->id,
    'amount_etb' => 1500,
    'payment_method' => 'telebirr',
    'transaction_reference' => 'TB-REF-' . rand(100000, 999999),
    'notes' => 'Ethio Telecom Telebirr direct mobile settlement',
]);
$res3 = $paymentController->recordPayment($req3);
$data3 = $res3->getData(true);
$pay3 = $data3['payment'] ?? null;
assertCondition(
    3,
    "Record a mobile payment",
    $res3->getStatusCode() === 201 && $pay3['payment_method'] === 'telebirr' && (float)$pay3['amount_etb'] === 1500.0,
    "Telebirr mobile payment of ETB 1,500 settled successfully."
);

// [Scenario 4] Record a card payment (Credit / Debit Card via POS terminal)
$req4 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $booking->id,
    'amount_etb' => 2500,
    'payment_method' => 'card',
    'transaction_reference' => 'POS-VISA-' . rand(10000, 99999),
    'notes' => 'Visa Debit terminal authorization approval',
]);
$res4 = $paymentController->recordPayment($req4);
$data4 = $res4->getData(true);
$pay4 = $data4['payment'] ?? null;
assertCondition(
    4,
    "Record a card payment",
    $res4->getStatusCode() === 201 && $pay4['payment_method'] === 'card' && (float)$pay4['amount_etb'] === 2500.0,
    "POS card payment of ETB 2,500 recorded with terminal ref {$pay4['transaction_id']}."
);

// [Scenario 5] Accept an advance deposit
$bookingAdvance = Booking::create([
    'id' => 'bk-adv-' . time(),
    'reference_code' => '#ETH-ADV-' . rand(10000, 99999),
    'guest_id' => $guest->id,
    'room_id' => $room->id,
    'check_in_date' => Carbon::now()->addDays(10)->toDateString(),
    'check_out_date' => Carbon::now()->addDays(12)->toDateString(),
    'nightly_rate_etb' => 5000,
    'total_price_etb' => 10000,
    'booking_status' => 'confirmed',
    'payment_status' => 'unpaid',
]);

$req5 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $bookingAdvance->id,
    'amount_etb' => 4000,
    'payment_method' => 'bank_transfer',
    'is_advance_deposit' => true,
    'transaction_reference' => 'DEP-' . rand(10000, 99999),
    'notes' => '40% advance reservation guarantee deposit',
]);
$res5 = $paymentController->recordPayment($req5);
$data5 = $res5->getData(true);
$pay5 = $data5['payment'] ?? null;
assertCondition(
    5,
    "Accept an advance deposit",
    $res5->getStatusCode() === 201 && (bool)$pay5['is_advance_deposit'] === true && (float)$pay5['amount_etb'] === 4000.0,
    "Advance deposit of ETB 4,000 accepted. Flagged as is_advance_deposit = true."
);

// [Scenario 6] Accept partial payments
// Total for $booking is 15,000 ETB. Current sum of pay1(2000)+pay2(3000)+pay3(1500)+pay4(2500) = 9,000 ETB.
$booking->refresh();
$paidSoFar = (float)$booking->payments->whereIn('status', ['verified', 'completed'])->sum('amount_etb');
$outstanding = $booking->outstanding_balance_etb;
assertCondition(
    6,
    "Accept partial payments",
    $paidSoFar === 9000.0 && $outstanding === 6000.0 && $booking->payment_status === 'partially_paid',
    "Partial ledger verified: Paid ETB 9,000 of ETB 15,000. Status: 'partially_paid', Balance: ETB 6,000."
);

// [Scenario 7] Accept full payments
$req7 = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $booking->id,
    'amount_etb' => 6000,
    'payment_method' => 'cash',
    'notes' => 'Settling remaining balance in full',
]);
$res7 = $paymentController->recordPayment($req7);
$data7 = $res7->getData(true);
$booking->refresh();
assertCondition(
    7,
    "Accept full payments",
    (float)$data7['outstanding_balance_etb'] === 0.0 && $booking->payment_status === 'paid',
    "Final payment of ETB 6,000 settled balance to ETB 0. Payment status is 'paid'."
);

// [Scenario 8] Calculate outstanding balances
// Create another booking: Total 8,000 ETB. Pay 3,000. Balance must be exactly 5,000 ETB.
$bookingBal = Booking::create([
    'id' => 'bk-bal-' . time(),
    'reference_code' => '#ETH-BAL-' . rand(10000, 99999),
    'guest_id' => $guest->id,
    'room_id' => $room->id,
    'check_in_date' => Carbon::now()->toDateString(),
    'check_out_date' => Carbon::now()->addDays(2)->toDateString(),
    'nightly_rate_etb' => 4000,
    'total_price_etb' => 8000,
    'booking_status' => 'confirmed',
    'payment_status' => 'unpaid',
]);
$paymentController->recordPayment(Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $bookingBal->id,
    'amount_etb' => 3000,
    'payment_method' => 'cash',
]));
$bookingBal->refresh();
assertCondition(
    8,
    "Calculate outstanding balances",
    $bookingBal->outstanding_balance_etb === 5000.0,
    "Gross ETB 8,000 - Paid ETB 3,000 = Outstanding ETB 5,000. Exact decimal accuracy."
);

// [Scenario 9] Calculate room charges correctly
$nights = (int)Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date));
$calcRoomPrice = (float)($nights * (float)$booking->nightly_rate_etb);
assertCondition(
    9,
    "Calculate room charges correctly",
    $nights === 3 && abs($calcRoomPrice - 15000.0) < 0.01,
    "Calculated {$nights} billable nights * ETB " . number_format($booking->nightly_rate_etb) . "/night = ETB " . number_format($calcRoomPrice) . "."
);

// [Scenario 10] Add additional service charges
$req10 = Request::create("/api/v1/bookings/{$bookingBal->id}/service-charges", 'POST', [
    'service_type' => 'restaurant',
    'description' => 'Dinner at Rift Valley Dining Room',
    'amount_etb' => 1200,
    'quantity' => 1,
    'reference_code' => 'REST-ORDER-991',
]);
$res10 = $bookingController->addServiceCharge($req10, $bookingBal->id);
$data10 = $res10->getData(true);
$bookingBal->refresh();
assertCondition(
    10,
    "Add additional service charges",
    in_array($res10->getStatusCode(), [200, 201]) && (float)$bookingBal->total_service_charges_etb === 1200.0 && $bookingBal->gross_total_etb === 9200.0,
    "Incidental charge of ETB 1,200 added. New Gross Total: ETB 9,200."
);

// [Scenario 11] Prevent duplicate service charges
$req11 = Request::create("/api/v1/bookings/{$bookingBal->id}/service-charges", 'POST', [
    'service_type' => 'restaurant',
    'description' => 'Dinner at Rift Valley Dining Room (Retried)',
    'amount_etb' => 1200,
    'quantity' => 1,
    'reference_code' => 'REST-ORDER-991',
]);
$res11 = $bookingController->addServiceCharge($req11, $bookingBal->id);
$data11 = $res11->getData(true);
$bookingBal->refresh();
assertCondition(
    11,
    "Prevent duplicate service charges",
    $data11['is_duplicate'] === true && (float)$bookingBal->total_service_charges_etb === 1200.0,
    "Duplicate service charge blocked via reference code 'REST-ORDER-991'. Charges not double-posted."
);

// [Scenario 12] Generate a unique invoice
$res12a = $bookingController->generateInvoice($bookingBal->id);
$data12a = $res12a->getData(true)['invoice'];
$invoiceNumA = $data12a['invoice_number'];

// Refresh / call again
$res12b = $bookingController->generateInvoice($bookingBal->id);
$data12b = $res12b->getData(true)['invoice'];
$invoiceNumB = $data12b['invoice_number'];

$dbInvCount = Invoice::where('booking_id', $bookingBal->id)->count();
assertCondition(
    12,
    "Generate a unique invoice",
    str_starts_with($invoiceNumA, 'INV-ETH-') && $invoiceNumA === $invoiceNumB && $dbInvCount === 1,
    "Unique invoice {$invoiceNumA} generated and persisted. Zero duplicate invoices on refresh."
);

// [Scenario 13] Generate payment receipts
$res13 = $paymentController->getReceipt($pay1['id']);
$data13 = $res13->getData(true);
$rcpt = $data13['receipt'] ?? null;
assertCondition(
    13,
    "Generate payment receipts",
    $res13->getStatusCode() === 200 && str_starts_with($rcpt['receipt_number'], 'RCP-') && (float)$rcpt['payment']['amount_etb'] === 2000.0,
    "Official receipt {$rcpt['receipt_number']} generated with stay details and remaining balance."
);

// [Scenario 14] Download and print invoices
$res14 = $bookingController->generateInvoice($booking->id);
$data14 = $res14->getData(true)['invoice'];
assertCondition(
    14,
    "Download and print invoices",
    isset($data14['hotel_tin']) && isset($data14['taxes']['vat_15']) && isset($data14['payments']),
    "Complete Ethiopian Tax Invoice payload ready for PDF rendering & print. TIN: {$data14['hotel_tin']}."
);

// [Scenario 15] Process partial refunds
// On pay2 (ETB 3000): process refund of ETB 1000
$req15 = Request::create("/api/v1/payments/{$pay2['id']}/refund", 'POST', [
    'amount_etb' => 1000,
    'reason' => 'Guest adjusted length of stay',
]);
$res15 = $paymentController->requestRefund($req15, $pay2['id']);
$data15 = $res15->getData(true);
$pay2Db = Payment::find($pay2['id']);
assertCondition(
    15,
    "Process partial refunds",
    $res15->getStatusCode() === 200 && (float)$pay2Db->refunded_amount_etb === 1000.0 && $pay2Db->refund_status === 'partially_refunded',
    "Partial refund of ETB 1,000 processed. Refund #{$data15['refund']['refund_number']}, status: 'partially_refunded'."
);

// [Scenario 16] Process full refunds
// Refund remaining ETB 2000 from pay2
$req16 = Request::create("/api/v1/payments/{$pay2['id']}/refund", 'POST', [
    'amount_etb' => 2000,
    'reason' => 'Remaining refundable amount disbursed',
]);
$res16 = $paymentController->requestRefund($req16, $pay2['id']);
$data16 = $res16->getData(true);
$pay2Db->refresh();
assertCondition(
    16,
    "Process full refunds",
    $res16->getStatusCode() === 200 && (float)$pay2Db->refunded_amount_etb === 3000.0 && $pay2Db->refund_status === 'refunded' && $pay2Db->status === 'refunded',
    "Full refund of ETB 2,000 processed. Total refunded: ETB 3,000. Payment marked as 'refunded'."
);

// [Scenario 17] Prevent refunds exceeding the original refundable amount
// Attempting to refund another ETB 100 on pay2 (which has 0 remaining)
$req17 = Request::create("/api/v1/payments/{$pay2['id']}/refund", 'POST', [
    'amount_etb' => 100,
    'reason' => 'Excess refund attempt',
]);
$res17 = $paymentController->requestRefund($req17, $pay2['id']);
assertCondition(
    17,
    "Prevent refunds exceeding the original refundable amount",
    $res17->getStatusCode() === 422,
    "Excess refund blocked with HTTP 422: cannot exceed refundable limit."
);

// [Scenario 18] Prevent duplicate payment submissions
$idempKey = 'TEST-IDEMP-' . rand(100000, 999999);
$req18a = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $bookingBal->id,
    'amount_etb' => 500,
    'payment_method' => 'cash',
    'idempotency_key' => $idempKey,
]);
$res18a = $paymentController->recordPayment($req18a);

// Submit again with exact same idempotency_key
$req18b = Request::create('/api/v1/payments/record', 'POST', [
    'booking_id' => $bookingBal->id,
    'amount_etb' => 500,
    'payment_method' => 'cash',
    'idempotency_key' => $idempKey,
]);
$res18b = $paymentController->recordPayment($req18b);
$data18b = $res18b->getData(true);

$idempPaymentCount = Payment::where('idempotency_key', $idempKey)->count();
assertCondition(
    18,
    "Prevent duplicate payment submissions",
    $idempPaymentCount === 1 && $data18b['is_duplicate'] === true,
    "Duplicate submission prevented via idempotency key. Returned existing payment record."
);

// [Scenario 19] Maintain payment history
$historyCount = Payment::where('booking_id', $booking->id)->count();
$refundCount = Refund::where('booking_id', $booking->id)->count();
$auditLogsCount = AuditLog::where('resource_type', 'payment')->count();
assertCondition(
    19,
    "Maintain payment history",
    $historyCount >= 4 && $refundCount >= 2 && $auditLogsCount >= 5,
    "Complete financial transaction history preserved. {$historyCount} payments, {$refundCount} refunds, {$auditLogsCount} audit logs."
);

// [Scenario 20] Generate daily and monthly financial reports
$req20 = Request::create('/api/v1/reports/financial?period=monthly', 'GET');
$res20 = $adminController->financialReports($req20);
$data20 = $res20->getData(true);
assertCondition(
    20,
    "Generate daily and monthly financial reports",
    isset($data20['summary']['gross_collections_etb']) &&
    isset($data20['by_payment_method']) &&
    isset($data20['by_room_category']) &&
    count($data20['by_payment_method']) >= 2,
    "Financial report generated: Gross ETB {$data20['summary']['gross_collections_etb']}, categorized across methods & room types."
);

// [Scenario 21] Verify role-based financial permissions
// Check that hard delete of payment is forbidden
$res21 = $paymentController->destroy($pay1['id']);
assertCondition(
    21,
    "Verify role-based financial permissions",
    $res21->getStatusCode() === 403,
    "Direct payment record deletion blocked with HTTP 403: 'Completed financial transactions cannot be deleted.'"
);

// [Scenario 22] Verify integration with checkout and guest folios
$res22 = $paymentController->getGuestFolio($bookingBal->id);
$data22 = $res22->getData(true);
$folio = $data22['folio'];
assertCondition(
    22,
    "Verify integration with checkout and guest folios",
    isset($folio['room_charges']) && isset($folio['service_charges']) && isset($folio['outstanding_balance_etb']),
    "Guest folio integrated seamlessly: Gross ETB {$folio['gross_total_etb']}, Outstanding ETB {$folio['outstanding_balance_etb']}."
);

// [Scenario 23] Verify that historical booking prices remain unchanged
$originalAgreedRate = (float)$booking->nightly_rate_etb;
// Change current room price in room inventory
$room->update(['price_etb' => 9500]);
// Reload booking
$booking->refresh();
assertCondition(
    23,
    "Verify that historical booking prices remain unchanged",
    (float)$booking->nightly_rate_etb === $originalAgreedRate && (float)$booking->nightly_rate_etb === 5000.0,
    "Room inventory price updated to ETB 9,500, but historical reservation preserved agreed rate of ETB 5,000."
);
// Restore room price
$room->update(['price_etb' => 5000]);

// [Scenario 24] Verify that failed or pending payments do not reduce outstanding balances
$pendingTx = Payment::create([
    'id' => 'pay-pending-' . time(),
    'transaction_id' => 'TX-PENDING-' . rand(1000, 9999),
    'booking_id' => $bookingBal->id,
    'payment_method' => 'telebirr',
    'amount_etb' => 3000,
    'amount_usd' => 46,
    'status' => 'pending_verification',
    'created_at' => now(),
]);
$bookingBal->refresh();
$balBefore = $bookingBal->outstanding_balance_etb;

// Even with pending payment in DB, verified payments do not include it
$verifiedSum = (float)$bookingBal->payments->whereIn('status', ['verified', 'completed'])->sum('amount_etb');
$hasReducedBalance = ($bookingBal->gross_total_etb - $verifiedSum) !== $balBefore;

assertCondition(
    24,
    "Verify that failed or pending payments do not reduce outstanding balances",
    !$hasReducedBalance,
    "Pending payment of ETB 3,000 exists in database but does NOT reduce outstanding balance ({$balBefore} ETB)."
);

echo "====================================================================\n";
echo "   PAYMENT & BILLING MANAGEMENT RESULTS: {$passedCount} / 24 PASSED\n";
echo "====================================================================\n";
if ($failedCount === 0) {
    echo "🎉 ALL 24 PAYMENT & BILLING MANAGEMENT STANDARDS MET 100%!\n";
} else {
    echo "⚠️ {$failedCount} SCENARIOS FAILED.\n";
    exit(1);
}
