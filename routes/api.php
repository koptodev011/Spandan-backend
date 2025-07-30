<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Patient routes
    Route::prefix('patients')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PatientController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PatientController::class, 'store']);
    });
    
    // Appointment routes
    Route::prefix('appointments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AppointmentController::class, 'index']);
        Route::get('/today', [\App\Http\Controllers\Api\AppointmentController::class, 'today']);
        Route::get('/patient/{id}', [\App\Http\Controllers\Api\AppointmentController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\AppointmentController::class, 'store']);
    });
    
    // Session routes
    Route::prefix('sessions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PatientSessionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PatientSessionController::class, 'store']);
        Route::get('/{patientSession}', [\App\Http\Controllers\Api\PatientSessionController::class, 'show']);
        Route::put('/{patientSession}', [\App\Http\Controllers\Api\PatientSessionController::class, 'update']);
        Route::post('/{session}/complete', [\App\Http\Controllers\Api\PatientSessionController::class, 'completeSession']);
        Route::delete('/{patientSession}', [\App\Http\Controllers\Api\PatientSessionController::class, 'destroy']);
        Route::post('/{patientSession}/start', [\App\Http\Controllers\Api\PatientSessionController::class, 'start']);
    });
});
