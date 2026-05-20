<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API endpoints consumed by the Vue frontend and protected by Sanctum/RBAC.
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/feedbacks', [FeedbackController::class, 'store']);

// ==========================================
// --- LALUAN BARU: GOOGLE SIGN-IN ---
// ==========================================
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::get('/spaces', [SpaceController::class, 'index']);
Route::get('/spaces/{space}', [SpaceController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // PDF document download. Controller enforces ownership/role.
    Route::get('/bookings/{booking}/pdf', [BookingController::class, 'generatePdf']);

    Route::middleware('vendor.approved')->group(function () {
        Route::get('/vendor/bookings', [BookingController::class, 'mine']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::patch('/vendor/bookings/{booking}/resubmit', [BookingController::class, 'resubmit']);
    });

    Route::middleware('role:cmart_staff,cmart_admin')->group(function () {
        Route::apiResource('bookings', BookingController::class)->except(['store']);
        Route::post('/profitability', [BookingController::class, 'checkProfitability']);
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('feedbacks', FeedbackController::class)->except(['store']);
    });

    Route::middleware('role:cmart_admin')->group(function () {
        Route::post('/spaces', [SpaceController::class, 'store']);
        Route::put('/spaces/{space}', [SpaceController::class, 'update']);
        Route::patch('/spaces/{space}', [SpaceController::class, 'update']);
        Route::delete('/spaces/{space}', [SpaceController::class, 'destroy']);
    });
});

// Fetch all reviews for the community feed
Route::get('/feedbacks', [App\Http\Controllers\Api\FeedbackController::class, 'index']);

// Our existing submission routes
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/feedback/submit', [App\Http\Controllers\Api\FeedbackController::class, 'store']);
    Route::patch('/feedback/{id}/helpful', [App\Http\Controllers\Api\FeedbackController::class, 'markHelpful']);
});