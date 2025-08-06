<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;

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

// Debug route - remove this in production
Route::get('/debug/appointments', function() {
    try {
        $appointments = \App\Models\Appointment::all();
        return response()->json([
            'status' => 'success',
            'count' => $appointments->count(),
            'appointments' => $appointments->toArray()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Report routes
    Route::prefix('reports')->group(function () {
        // Payment reports
        Route::get('/payments/summary', [ReportController::class, 'paymentSummary']);
        Route::get('/payments/detailed', [ReportController::class, 'paymentDetailed']);
        
        // Session reports
        Route::get('/sessions/completed', [ReportController::class, 'completedSessions']);
        Route::get('/patients/{patientId}/session-stats', [ReportController::class, 'patientSessionStats']);
        
        // Patient reports
        Route::get('/patients', [ReportController::class, 'patientReports']);
        Route::get('/patients/summary', [ReportController::class, 'patientSessionStats']);
        
        // Appointment reports
        Route::get('/appointments', [ReportController::class, 'appointmentReports']);
        
        // General statistics
        Route::get('/statistics', [ReportController::class, 'generalStatistics']);
    });
    
    // Payment management routes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/summary', [PaymentController::class, 'summary']);
        Route::get('/categories', [PaymentController::class, 'categories']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/{id}', [PaymentController::class, 'show']);
        Route::put('/{id}', [PaymentController::class, 'update']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
    });
    
    // Patient routes
    Route::prefix('patients')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PatientController::class, 'index']);
        Route::get('/search', [\App\Http\Controllers\Api\PatientController::class, 'search']);
        Route::post('/', [\App\Http\Controllers\Api\PatientController::class, 'store']);
        
        // Get patient sessions
        Route::get('/{patient}/sessions', [\App\Http\Controllers\Api\PatientSessionController::class, 'getSessionsByPatient']);
    });
    
    // Appointment routes
    Route::prefix('appointments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AppointmentController::class, 'index']);
        Route::get('/by-date', [\App\Http\Controllers\Api\AppointmentController::class, 'getByDate']);
        Route::get('/today', [\App\Http\Controllers\Api\AppointmentController::class, 'today']);
        Route::get('/current', [\App\Http\Controllers\Api\AppointmentController::class, 'getCurrentAppointment']);
        Route::get('/upcoming', [\App\Http\Controllers\Api\AppointmentController::class, 'upcoming']);
        Route::get('/todays-upcoming', [\App\Http\Controllers\Api\AppointmentController::class, 'getTodaysUpcomingAppointment']);
        Route::get('/patient/{id}', [\App\Http\Controllers\Api\AppointmentController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\AppointmentController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\AppointmentController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\AppointmentController::class, 'destroy']);
    });
    
    // Session routes
    Route::prefix('sessions')->group(function () {
        // List all sessions
        Route::get('/', [\App\Http\Controllers\Api\PatientSessionController::class, 'index']);
        
        // Create new session
        Route::post('/', [\App\Http\Controllers\Api\PatientSessionController::class, 'store']);
        
        // Get session by ID - must come before other routes with dynamic parameters
        Route::get('/{id}', [\App\Http\Controllers\Api\PatientSessionController::class, 'show']);
        
        // Get completed sessions with filters
        Route::get('/completed/list', [\App\Http\Controllers\Api\PatientSessionController::class, 'getCompletedSessions']);
        
        // Other session routes
        Route::put('/{patientSession}', [\App\Http\Controllers\Api\PatientSessionController::class, 'update']);
        Route::post('/{session}/complete', [\App\Http\Controllers\Api\PatientSessionController::class, 'completeSession']);
        Route::delete('/{patientSession}', [\App\Http\Controllers\Api\PatientSessionController::class, 'destroy']);
        Route::post('/{patientSession}/start', [\App\Http\Controllers\Api\PatientSessionController::class, 'start']);
        Route::get('/patient/{patientId}/history', [\App\Http\Controllers\Api\PatientSessionController::class, 'getPatientSessionHistory']);
    });
});
