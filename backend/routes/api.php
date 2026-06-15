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
use App\Http\Controllers\Api\VendorItemController;
use App\Http\Controllers\Api\AuditLogController;

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
Route::get('/news', [NewsPostController::class, 'publicIndex']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Community event RSVP — pessimistic locking prevents overbooking under concurrency.
    Route::post('/events/{carboot_event}/register', [EventRegistrationController::class, 'register']);

    Route::get('/bookings/{booking}/pdf', [BookingController::class, 'generatePdf']);

    Route::middleware('role:community')->group(function () {
        Route::get('/vendor/analytics/me', [VendorAnalyticsController::class, 'me']);
        Route::get('/vendor/analytics/report', [VendorAnalyticsController::class, 'report']);
        Route::get('/vendor/history-receipts', [VendorHistoryController::class, 'historyReceipts']);

        Route::get('/vendor/business-profile', [VendorBusinessProfileController::class, 'show']);
        Route::put('/vendor/business-profile', [VendorBusinessProfileController::class, 'update']);
        Route::post('/vendor/business-profile/logo', [VendorBusinessProfileController::class, 'uploadLogo']);

        Route::get('/vendor/items', [VendorItemController::class, 'index']);
        Route::post('/vendor/items', [VendorItemController::class, 'store']);
        Route::get('/vendor/items/{vendor_item}', [VendorItemController::class, 'show']);
        Route::put('/vendor/items/{vendor_item}', [VendorItemController::class, 'update']);
        Route::delete('/vendor/items/{vendor_item}', [VendorItemController::class, 'destroy']);
    });

    Route::middleware('vendor.approved')->group(function () {
        Route::get('/vendor/bookings', [BookingController::class, 'mine']);
        Route::get('/vendor/bookings/{booking}', [BookingController::class, 'vendorShow']);
        Route::patch('/vendor/bookings/{booking}', [BookingController::class, 'vendorUpdate']);
        Route::patch('/vendor/bookings/{booking}/resubmit', [BookingController::class, 'resubmit']);
        Route::post('/vendor/bookings/{booking}/cancel', [BookingController::class, 'vendorCancel']);
        Route::post('/vendor/bookings/{booking}/request-change', [BookingController::class, 'vendorRequestChange']);
        Route::post('/vendor/bookings/{booking}/request-cancellation', [BookingController::class, 'vendorRequestCancellation']);
        Route::post('/bookings', [BookingController::class, 'store']);
    });

    Route::middleware('role:cmart_staff,cmart_admin')->group(function () {
        Route::get('/staff/feedbacks', [FeedbackController::class, 'staffIndex']);
        Route::apiResource('bookings', BookingController::class)->except(['store']);
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);
        Route::apiResource('feedbacks', FeedbackController::class)->except(['store', 'index']);
        Route::apiResource('carboot-events', CarbootEventController::class);
        Route::apiResource('news-posts', NewsPostController::class);
    });

    Route::middleware('boss')->group(function () {
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
