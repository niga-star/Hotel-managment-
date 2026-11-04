<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\HousekeepingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\GuestController;

Route::prefix('v1')->group(function () {

    // --- Authentication ---
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-code', [AuthController::class, 'verifyCode']);
    Route::post('/auth/resend-code', [AuthController::class, 'resendCode']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendCode']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // --- Rooms & Categories ---
    Route::get('/amenities', [RoomController::class, 'amenities']);
    Route::get('/categories', [RoomController::class, 'categories']);
    Route::post('/categories', [RoomController::class, 'storeCategory']);
    Route::put('/categories/{id}', [RoomController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [RoomController::class, 'destroyCategory']);
    Route::get('/rooms/categories', [RoomController::class, 'categories']);
    Route::get('/rooms/floor-map', [RoomController::class, 'floorMap']);
    Route::get('/rooms/{id}/booked-dates', [RoomController::class, 'bookedDates']);
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);
    Route::patch('/rooms/{id}/status', [RoomController::class, 'updateStatus']);
    Route::patch('/rooms/{id}/toggle-active', [RoomController::class, 'toggleActive']);

    // --- Guest Favorites ---
    Route::get('/favorites', [RoomController::class, 'getFavorites']);
    Route::post('/favorites', [RoomController::class, 'addFavorite']);
    Route::post('/favorites/{roomId}', [RoomController::class, 'addFavorite']);
    Route::delete('/favorites/{roomId}', [RoomController::class, 'removeFavorite']);

    // --- Bookings & PMS Lifecycle ---
    Route::get('/rooms/search-availability', [BookingController::class, 'searchAvailability']);
    Route::post('/bookings/validate-promo', [BookingController::class, 'validatePromo']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'desk']);
    Route::get('/bookings/my-bookings', [BookingController::class, 'myBookings']);
    Route::get('/guest/portal-summary', [BookingController::class, 'guestPortalSummary']);
    Route::get('/bookings/desk', [BookingController::class, 'desk']);
    Route::get('/bookings/check-in-eligible', [BookingController::class, 'checkInEligible']);
    Route::get('/bookings/check-out-eligible', [BookingController::class, 'checkOutEligible']);
    Route::get('/desk/checkin-dashboard', [BookingController::class, 'checkInDashboard']);
    Route::get('/desk/operations-history', [BookingController::class, 'operationsHistory']);
    Route::post('/desk/walk-in-checkin', [BookingController::class, 'walkInCheckIn']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/no-show', [BookingController::class, 'markNoShow']);
    Route::get('/bookings/{id}/confirmation', [BookingController::class, 'confirmation']);
    Route::post('/bookings/{id}/check-in', [BookingController::class, 'checkIn']);
    Route::post('/bookings/{id}/check-out', [BookingController::class, 'checkOut']);
    Route::post('/bookings/{id}/reassign-room', [BookingController::class, 'reassignRoom']);
    Route::post('/bookings/{id}/service-charges', [BookingController::class, 'addServiceCharge']);
    Route::get('/bookings/{id}/service-charges', [BookingController::class, 'getServiceCharges']);
    Route::post('/bookings/{id}/settle-payment', [BookingController::class, 'settlePayment']);
    Route::post('/bookings/{id}/early-checkin', [BookingController::class, 'approveEarlyCheckIn']);
    Route::post('/bookings/{id}/late-checkout', [BookingController::class, 'approveLateCheckOut']);
    Route::get('/bookings/{id}/invoice', [BookingController::class, 'generateInvoice']);

    // --- Guest Directory & Profiles ---
    Route::get('/guests/dashboard-stats', [GuestController::class, 'dashboardStats']);
    Route::get('/guests/check-duplicate', [GuestController::class, 'checkDuplicate']);
    Route::get('/guests', [GuestController::class, 'index']);
    Route::post('/guests', [GuestController::class, 'store']);
    Route::get('/guests/{id}', [GuestController::class, 'show']);
    Route::put('/guests/{id}', [GuestController::class, 'update']);
    Route::delete('/guests/{id}', [GuestController::class, 'destroy']);
    Route::patch('/guests/{id}/toggle-active', [GuestController::class, 'toggleActive']);
    Route::get('/guests/{id}/bookings', [BookingController::class, 'guestBookings']);

    // --- Admin Front Desk Shortcuts ---
    Route::get('/admin/bookings/desk', [BookingController::class, 'desk']);
    Route::patch('/admin/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    Route::get('/admin/bookings/check-in-eligible', [BookingController::class, 'checkInEligible']);
    Route::get('/admin/bookings/check-out-eligible', [BookingController::class, 'checkOutEligible']);

    // --- Housekeeping Cleanliness Matrix ---
    Route::get('/housekeeping', [HousekeepingController::class, 'index']);
    Route::patch('/housekeeping/{id}', [HousekeepingController::class, 'update']);
    Route::put('/housekeeping/{id}', [HousekeepingController::class, 'update']);

    // --- Payments & Financial Transactions ---
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments/record', [PaymentController::class, 'recordPayment']);
    Route::post('/payments/{id}/refund', [PaymentController::class, 'requestRefund']);
    Route::get('/refunds', [PaymentController::class, 'listRefunds']);
    Route::get('/payments/receipts', [PaymentController::class, 'listReceipts']);
    Route::get('/receipts', [PaymentController::class, 'listReceipts']);
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'getReceipt']);
    Route::get('/invoices', [PaymentController::class, 'listInvoices']);
    Route::get('/invoices/{id}', [PaymentController::class, 'showInvoice']);
    Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
    Route::get('/billing/folio/{bookingId}', [PaymentController::class, 'getGuestFolio']);
    Route::post('/billing/folio/{bookingId}/charges', [BookingController::class, 'addServiceCharge']);
    Route::post('/payments/initialize', [PaymentController::class, 'initializeGateway']);
    Route::post('/payments/upload-receipt', [PaymentController::class, 'uploadReceipt']);
    Route::patch('/payments/{id}/review', [PaymentController::class, 'review']);
    Route::post('/payments/{id}/review', [PaymentController::class, 'review']);
    Route::patch('/payments/{id}/verify', [PaymentController::class, 'verify']);
    Route::post('/payments/simulate-webhook', [PaymentController::class, 'simulateWebhook']);
    Route::post('/payments/webhook/chapa', [PaymentController::class, 'chapaWebhook']);
    Route::post('/payments/webhook/telebirr', [PaymentController::class, 'telebirrWebhook']);
    Route::post('/payments/webhook/cbe-birr', [PaymentController::class, 'cbeBirrWebhook']);

    // --- Media & Dropzone Photo Uploads ---
    Route::post('/upload/room-image', [UploadController::class, 'uploadRoomImage']);
    Route::post('/upload/multiple', [UploadController::class, 'uploadMultiple']);

    // --- Admin Dashboard & Intelligence ---
    Route::get('/admin/dashboard-stats', [AdminController::class, 'dashboardStats']);
    Route::get('/admin/night-audit', [AdminController::class, 'nightAuditSummary']);
    Route::get('/admin/export-ledger-csv', [AdminController::class, 'exportLedgerCsv']);
    Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs']);
    Route::get('/reports/financial', [AdminController::class, 'financialReports']);

    // --- Guest Notifications & SMS Dispatch ---
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/booking/{bookingId}', [NotificationController::class, 'bookingNotifications']);
    Route::post('/notifications/resend', [NotificationController::class, 'resend']);
    Route::post('/notifications/sms/send', [NotificationController::class, 'sendSms']);
});
