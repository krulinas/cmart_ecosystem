<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importing your Waiters (Controllers)
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\FeedbackController;

/*
|--------------------------------------------------------------------------
| API Routes (The Menu)
|--------------------------------------------------------------------------
| Here is where Vue.js will send its HTTP requests!
*/

// We use 'apiResource' because it automatically generates all 5 standard URLs 
// (Create, Read All, Read One, Update, Delete) for each controller in one line!

Route::apiResource('spaces', SpaceController::class);
Route::apiResource('bookings', BookingController::class);
Route::apiResource('invoices', InvoiceController::class);
Route::apiResource('feedbacks', FeedbackController::class);