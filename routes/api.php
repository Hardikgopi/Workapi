<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Product Owner creates a tenant company
Route::post('/create-customer-company', [AuthController::class,'createCustomerCompany']);

// Tenant admin login
Route::post('/login', [AuthController::class,'login']);

// Tenant admin adds employees & logout
Route::middleware('auth:sanctum')->group(function(){
    Route::post('/register', [AuthController::class,'register']);
    Route::post('/logout', [AuthController::class,'logout']);
    Route::get('/user', function($request){ return $request->user(); });
});