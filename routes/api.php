<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Tenant\LeadController;
use App\Http\Controllers\Tenant\TicketController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\TaskController;
use App\Http\Controllers\Tenant\NoteController;
use App\Http\Controllers\Tenant\PaymentController;

// Product Owner creates a tenant company
Route::post('/owner/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/owner/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/create-customer-company', [AuthController::class, 'createCustomerCompany']);

// Tenant admin login
Route::post('/login', [AuthController::class, 'login']);

// All authenticated tenant routes
Route::middleware('tenant.auth')->group(function () {

    // Auth / User
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout',   [AuthController::class, 'logout']);
    Route::get('/user', function (Illuminate\Http\Request $request) {
        return response()->json($request->attributes->get('tenant_user'));
    });
    Route::post('/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/users', [AuthController::class, 'listUsers']);

    // ── Business Modules ──────────────────────────────────────────

    // Leads
    Route::apiResource('leads', LeadController::class);

    // Tickets
    Route::apiResource('tickets', TicketController::class);

    // Contacts
    Route::apiResource('contacts', ContactController::class);

    // Tasks
    Route::apiResource('tasks', TaskController::class);

    // Notes
    Route::apiResource('notes', NoteController::class);

    // Payments (Razorpay Payment Links)
    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
    Route::post('payments/{id}/sync', [PaymentController::class, 'sync']);
});
