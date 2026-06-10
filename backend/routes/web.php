<?php

use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index']);
});

Route::prefix('api/proxy/analytics')->group(function () {
    Route::get('/summary', [AnalyticsController::class, 'getStatusSummary']);
    Route::get('/feedback', [AnalyticsController::class, 'getFeedbackCloud']);
    Route::get('/products', [AnalyticsController::class, 'getProductCloud']);
});
