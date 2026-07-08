<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarbootEventController;
use App\Http\Controllers\Api\EventRegistrationController;
use App\Http\Controllers\Api\NewsPostController;
use App\Http\Controllers\Api\BossAnalyticsController;
use App\Http\Controllers\Api\VendorAnalyticsController;
use App\Http\Controllers\Api\VendorHistoryController;
use App\Http\Controllers\Api\VendorBusinessProfileController;
use App\Http\Controllers\Api\VendorProfileController;
use App\Http\Controllers\Api\VendorEventPassController;
use App\Http\Controllers\Api\BookingPassVerificationController;
use App\Http\Controllers\Api\VendorItemController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\UserBookingPreferenceController;
use App\Http\Controllers\Api\StaffOperationsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API endpoints consumed by the Vue frontend and protected by Sanctum/RBAC.
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ==========================================
// --- NEW ROUTE: GOOGLE SIGN-IN ---
// ==========================================
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::get('/spaces', [SpaceController::class, 'index']);
Route::get('/spaces/{space}', [SpaceController::class, 'show']);

Route::get('/feedbacks', [FeedbackController::class, 'index']);
Route::post('/feedback/{id}/helpful', [FeedbackController::class, 'markHelpful']);

Route::get('/events', [CarbootEventController::class, 'publicIndex']);
Route::get('/events/{carboot_event}', [CarbootEventController::class, 'publicShow']);
Route::get('/news', [NewsPostController::class, 'publicIndex']);

Route::get('/marketplace/items', [MarketplaceController::class, 'index']);
Route::get('/marketplace/items/{vendor_item}', [MarketplaceController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Community event RSVP — pessimistic locking prevents overbooking under concurrency.
    Route::post('/events/{carboot_event}/register', [EventRegistrationController::class, 'register']);

    Route::get('/bookings/{booking}/pdf', [BookingController::class, 'generatePdf']);

    Route::get('/booking-preferences/me', [UserBookingPreferenceController::class, 'show']);
    Route::put('/booking-preferences/me', [UserBookingPreferenceController::class, 'update']);
    Route::delete('/booking-preferences/me', [UserBookingPreferenceController::class, 'destroy']);

    Route::middleware('role:community')->group(function () {
        Route::get('/vendor/analytics/me', [VendorAnalyticsController::class, 'me']);
        Route::get('/vendor/analytics/report', [VendorAnalyticsController::class, 'report']);
        Route::get('/vendor/history-receipts', [VendorHistoryController::class, 'historyReceipts']);

        Route::get('/vendor/profile', [VendorProfileController::class, 'show']);
        Route::patch('/vendor/profile', [VendorProfileController::class, 'update']);

        Route::get('/vendor/business-profile', [VendorBusinessProfileController::class, 'show']);
        Route::put('/vendor/business-profile', [VendorBusinessProfileController::class, 'update']);
        Route::post('/vendor/business-profile/logo', [VendorBusinessProfileController::class, 'uploadLogo']);

        Route::get('/vendor/event-passes', [VendorEventPassController::class, 'index']);
        Route::get('/vendor/event-passes/{booking}', [VendorEventPassController::class, 'show']);

        Route::get('/vendor/items', [VendorItemController::class, 'index']);
        Route::post('/vendor/items', [VendorItemController::class, 'store']);
        Route::get('/vendor/items/{vendor_item}', [VendorItemController::class, 'show']);
        Route::put('/vendor/items/{vendor_item}', [VendorItemController::class, 'update']);
        Route::delete('/vendor/items/{vendor_item}', [VendorItemController::class, 'destroy']);

        Route::get('/vendor/bookings', [BookingController::class, 'mine']);
        Route::get('/vendor/bookings/{booking}', [BookingController::class, 'vendorShow']);
        Route::patch('/vendor/bookings/{booking}', [BookingController::class, 'vendorUpdate']);
        Route::patch('/vendor/bookings/{booking}/resubmit', [BookingController::class, 'resubmit']);
        Route::post('/vendor/bookings/{booking}/cancel', [BookingController::class, 'vendorCancel']);
        Route::patch('/bookings/{booking}/withdraw', [BookingController::class, 'withdraw']);
        Route::post('/vendor/bookings/{booking}/request-change', [BookingController::class, 'vendorRequestChange']);
        Route::post('/vendor/bookings/{booking}/request-cancellation', [BookingController::class, 'vendorRequestCancellation']);
        Route::post('/vendor/bookings/{booking}/submit-payment', [BookingController::class, 'vendorSubmitPayment']);
        Route::post('/vendor/bookings/{booking}/demo-payment', [BookingController::class, 'vendorDemoPayment']);
        Route::post('/bookings', [BookingController::class, 'store']);
    });

    Route::middleware('role:staff,manager,super_admin,cmart_staff,cmart_admin,boss')->group(function () {
        Route::get('/staff/feedbacks', [FeedbackController::class, 'staffIndex']);
        Route::get('/staff/bookings', [BookingController::class, 'staffRegistry']);
        Route::get('/staff/operations-summary', [StaffOperationsController::class, 'operationsSummary']);

        Route::get('/staff/bookings/{booking}/verify', [BookingPassVerificationController::class, 'verify']);
        Route::post('/staff/bookings/{booking}/check-in', [BookingPassVerificationController::class, 'checkIn']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}/verify-payment', [BookingController::class, 'verifyBookingPayment']);

        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);

        Route::get('/feedbacks/{feedback}', [FeedbackController::class, 'show']);
        Route::put('/feedbacks/{feedback}', [FeedbackController::class, 'update']);
        Route::patch('/feedbacks/{feedback}', [FeedbackController::class, 'update']);
        Route::post('/feedbacks/{feedback}/reviewed', [FeedbackController::class, 'markReviewed']);
        Route::put('/feedbacks/{feedback}/official-reply', [FeedbackController::class, 'updateOfficialReply']);

        Route::middleware('role:manager,super_admin,cmart_admin,boss')->group(function () {
            Route::post('/feedbacks/{feedback}/official-reply/publish', [FeedbackController::class, 'publishOfficialReply']);
            Route::delete('/feedbacks/{feedback}', [FeedbackController::class, 'destroy']);
        });

        Route::apiResource('carboot-events', CarbootEventController::class);
        Route::apiResource('news-posts', NewsPostController::class);
    });

    Route::middleware('boss')->group(function () {
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
        Route::post('/profitability', [BookingController::class, 'checkProfitability']);
        Route::get('/boss/analytics/revenue', [BossAnalyticsController::class, 'revenue']);
        Route::get('/boss/analytics/wordcloud/{source}', [BossAnalyticsController::class, 'wordcloud']);
        Route::get('/boss/audit-logs', [AuditLogController::class, 'index']);
        Route::post('/spaces', [SpaceController::class, 'store']);
        Route::put('/spaces/{space}', [SpaceController::class, 'update']);
        Route::patch('/spaces/{space}', [SpaceController::class, 'update']);
        Route::delete('/spaces/{space}', [SpaceController::class, 'destroy']);
    });

    Route::post('/feedback/submit', [FeedbackController::class, 'store'])->middleware('throttle:10,1');
});
