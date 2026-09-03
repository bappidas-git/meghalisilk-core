<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Change password route
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::middleware('auth:sanctum')->group(function () {
	Route::post('/logout', [AuthController::class, 'logout']);
	// Place protected API routes here
	// Example: Route::get('/user', fn(Request $request) => $request->user());

	Route::post('/payments/create-order', [PaymentController::class, 'createOrder']);
	Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);
	Route::get('/users/{id}/payments', [PaymentController::class, 'userPayments']);
	Route::apiResource('appointments', AppointmentController::class);
	Route::get('/appointments/email/{email}', [AppointmentController::class, 'getByEmail']);
});
