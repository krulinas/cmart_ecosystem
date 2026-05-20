<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarbootEventController;
use App\Http\Controllers\Api\NewsPostController;

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

    Route::get('/bookings/{booking}/pdf', [BookingController::class, 'generatePdf']);

    Route::middleware('vendor.approved')->group(function () {
        Route::get('/vendor/bookings', [BookingController::class, 'mine']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::patch('/vendor/bookings/{booking}/resubmit', [BookingController::class, 'resubmit']);
    });

    Route::middleware('role:cmart_staff,cmart_admin')->group(function () {
        Route::get('/staff/feedbacks', [FeedbackController::class, 'staffIndex']);
        Route::apiResource('bookings', BookingController::class)->except(['store']);
        Route::post('/profitability', [BookingController::class, 'checkProfitability']);
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);
        Route::apiResource('feedbacks', FeedbackController::class)->except(['store', 'index']);
        Route::apiResource('carboot-events', CarbootEventController::class);
        Route::apiResource('news-posts', NewsPostController::class);
    });

    Route::middleware('role:cmart_admin')->group(function () {
        Route::post('/spaces', [SpaceController::class, 'store']);
        Route::put('/spaces/{space}', [SpaceController::class, 'update']);
        Route::patch('/spaces/{space}', [SpaceController::class, 'update']);
        Route::delete('/spaces/{space}', [SpaceController::class, 'destroy']);
    });

    Route::post('/feedback/submit', [FeedbackController::class, 'store'])->middleware('throttle:10,1');
});
