<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PatientSession;
use App\Models\Patient;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Get payment summary report
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentSummary(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        // Base query for expenses
        $expenseQuery = Payment::where('type', 'expense');
        // Base query for income
        $incomeQuery = Payment::where('type', 'income');

        // Apply date filters if provided
        if ($startDate) {
            $expenseQuery->whereDate('date', '>=', $startDate);
            $incomeQuery->whereDate('date', '>=', $startDate);
        }
        if ($endDate) {
            $expenseQuery->whereDate('date', '<=', $endDate);
            $incomeQuery->whereDate('date', '<=', $endDate);
        }

        // Get expense summary
        $expenses = $expenseQuery->sum('amount');
        $expensesByCategory = $expenseQuery->clone()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        // Get income summary
        $income = $incomeQuery->sum('amount');
        $incomeByCategory = $incomeQuery->clone()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        // Calculate net income (income - expenses)
        $netIncome = $income - $expenses;

        return response()->json([
            'success' => true,
            'data' => [
                'total_income' => $income,
                'total_expenses' => $expenses,
                'net_income' => $netIncome,
                'income_by_category' => $incomeByCategory,
                'expenses_by_category' => $expensesByCategory,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]
        ]);
    }

    /**
     * Get detailed payment report
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentDetailed(Request $request): JsonResponse
    {
        $query = Payment::query();

        // Filter by type (expense/income)
        if ($request->has('type') && in_array($request->type, ['expense', 'income'])) {
            $query->where('type', $request->type);
        }

        // Apply filters if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $payments = $query->with('patient')
                         ->orderBy('date', 'desc')
                         ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Get completed sessions report
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function completedSessions(Request $request): JsonResponse
    {
        try {
            $query = PatientSession::with(['patient', 'notes'])
                ->where('status', 'completed')
                ->orderBy('started_at', 'desc');
            
            // Search by patient name
            if ($request->has('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->whereHas('patient', function($q) use ($searchTerm) {
                    $q->where('first_name', 'like', $searchTerm)
                      ->orWhere('last_name', 'like', $searchTerm);
                });
            }
            
            // Filter by session type (in_person/remote)
            if ($request->has('type') && in_array($request->type, ['in_person', 'remote'])) {
                $query->where('session_type', $request->type);
            }
            
            // Filter by date range
            $dateFilter = $request->input('date', 'all');
            $date = now();
            
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('started_at', $date->toDateString());
                    break;
                case 'this_week':
                    $query->whereBetween('started_at', [
                        $date->startOfWeek()->toDateTimeString(),
                        $date->endOfWeek()->toDateTimeString()
                    ]);
                    break;
                case 'this_month':
                    $query->whereMonth('started_at', $date->month)
                          ->whereYear('started_at', $date->year);
                    break;
                case 'custom':
                    if ($request->has('start_date') && $request->has('end_date')) {
                        $query->whereBetween('started_at', [
                            $request->start_date,
                            $request->end_date
                        ]);
                    }
                    break;
            }
            
            // Pagination
            $perPage = $request->input('per_page', 15);
            $sessions = $query->paginate($perPage);
            
            // Format the response
            $formattedSessions = $sessions->map(function($session) {
                $notes = $session->notes->first();
                
                return [
                    'id' => $session->id,
                    'patient_id' => $session->patient_id,
                    'patient_name' => $session->patient ? $session->patient->full_name : 'Unknown',
                    'date' => $session->started_at->format('Y-m-d'),
                    'time' => $session->started_at->format('h:i A'),
                    'duration' => $session->expected_duration,
                    'type' => $session->session_type,
                    'status' => $session->status,
                    'notes' => $notes ? $notes->general_notes : null,
                    'clinical_notes' => $notes ? $notes->clinical_notes : null,
                    'mood_rating' => $notes ? $notes->mood_rating : null,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $formattedSessions,
                    'pagination' => [
                        'total' => $sessions->total(),
                        'per_page' => $sessions->perPage(),
                        'current_page' => $sessions->currentPage(),
                        'last_page' => $sessions->lastPage(),
                        'from' => $sessions->firstItem(),
                        'to' => $sessions->lastItem()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate completed sessions report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient session statistics
     * 
     * @param int $patientId
     * @param Request $request
     * @return JsonResponse
     */
    public function patientSessionStats($patientId, Request $request): JsonResponse
    {
        try {
            // Get patient details
            $patient = Patient::findOrFail($patientId);
            
            // Base query for sessions
            $query = PatientSession::where('patient_id', $patientId)
                ->with(['notes']);
            
            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->whereDate('started_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('started_at', '<=', $request->end_date);
            }
            
            // Get all matching sessions
            $sessions = $query->get();
            
            // Calculate statistics
            $totalSessions = $sessions->count();
            $totalDuration = $sessions->sum('expected_duration');
            
            $completedSessions = $sessions->where('status', 'completed');
            $completedCount = $completedSessions->count();
            
            $averageMood = $sessions->avg(function($session) {
                return $session->notes->avg('mood_rating');
            });
            
            // Group by session type
            $sessionsByType = $sessions->groupBy('session_type')
                ->map(function($sessions, $type) {
                    return [
                        'count' => $sessions->count(),
                        'total_duration' => $sessions->sum('expected_duration')
                    ];
                });
            
            // Get latest session
            $latestSession = $sessions->sortByDesc('started_at')->first();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'patient' => [
                        'id' => $patient->id,
                        'name' => $patient->full_name,
                        'age' => $patient->age,
                        'gender' => $patient->gender,
                        'phone' => $patient->phone,
                        'email' => $patient->email
                    ],
                    'statistics' => [
                        'total_sessions' => $totalSessions,
                        'completed_sessions' => $completedCount,
                        'total_duration' => $totalDuration,
                        'average_mood' => round($averageMood, 1) ?: null,
                        'sessions_by_type' => $sessionsByType
                    ],
                    'latest_session' => $latestSession ? [
                        'id' => $latestSession->id,
                        'date' => $latestSession->started_at->format('Y-m-d'),
                        'type' => $latestSession->session_type,
                        'status' => $latestSession->status,
                        'notes' => $latestSession->notes->first()->general_notes ?? null
                    ] : null
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate patient session statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment reports
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function appointmentReports(Request $request): JsonResponse
    {
        try {
            $query = Appointment::with(['patient']);
            
            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('date', '<=', $request->end_date);
            }
            
            // Filter by appointment type if provided
            if ($request->has('type')) {
                $query->where('appointment_type', $request->type);
            }
            
            // Get appointments with pagination
            $perPage = $request->input('per_page', 15);
            $appointments = $query->orderBy('date', 'desc')
                                ->orderBy('time', 'asc')
                                ->paginate($perPage);
            
            // Format response
            $formattedAppointments = $appointments->map(function($appt) {
                return [
                    'id' => $appt->id,
                    'patient_id' => $appt->patient_id,
                    'patient_name' => $appt->patient ? $appt->patient->full_name : 'Unknown',
                    'appointment_date' => $appt->date,
                    'start_time' => $appt->time,
                    'duration_minutes' => $appt->duration_minutes,
                    'type' => $appt->appointment_type,
                    'notes' => $appt->note
                ];
            });
            
            // Get summary statistics with the same filters
            $summaryQuery = Appointment::query();
            
            if ($request->has('start_date')) {
                $summaryQuery->whereDate('date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $summaryQuery->whereDate('date', '<=', $request->end_date);
            }
            
            if ($request->has('type')) {
                $summaryQuery->where('appointment_type', $request->type);
            }
            
            // Get total appointments count
            $totalAppointments = $summaryQuery->count();
            
            // Get appointment type distribution
            $typeDistribution = $summaryQuery->clone()
                ->selectRaw('appointment_type, COUNT(*) as count')
                ->groupBy('appointment_type')
                ->pluck('count', 'appointment_type');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'appointments' => $formattedAppointments,
                    'summary' => [
                        'total_appointments' => $totalAppointments,
                        'type_distribution' => $typeDistribution
                    ],
                    'pagination' => [
                        'total' => $appointments->total(),
                        'per_page' => $appointments->perPage(),
                        'current_page' => $appointments->currentPage(),
                        'last_page' => $appointments->lastPage(),
                        'from' => $appointments->firstItem(),
                        'to' => $appointments->lastItem()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate appointment report',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get patient reports and statistics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function patientReports(Request $request): JsonResponse
    {
        try {
            $query = Patient::query();
            
            // Filter by registration date
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            // Search by name or contact info
            if ($request->has('search')) {
                $search = '%' . $request->search . '%';
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', $search)
                      ->orWhere('last_name', 'like', $search)
                      ->orWhere('email', 'like', $search)
                      ->orWhere('phone', 'like', $search);
                });
            }
            
            // Filter by gender if provided
            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }
            
            // Get paginated results
            $perPage = $request->input('per_page', 15);
            $patients = $query->orderBy('created_at', 'desc')
                            ->paginate($perPage);
            
            // Format response
            $formattedPatients = $patients->map(function($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->full_name,
                    'email' => $patient->email,
                    'phone' => $patient->phone,
                    'gender' => $patient->gender,
                    'age' => $patient->age ?? 'N/A', // Fallback to 'N/A' if age is not available
                    'registration_date' => $patient->created_at->format('Y-m-d'),
                    'last_visit' => $patient->last_visit_date?->format('Y-m-d'),
                    'total_sessions' => $patient->sessions_count ?? 0,
                    'total_appointments' => $patient->appointments_count ?? 0
                ];
            });
            
            // Get summary statistics
            $summaryQuery = Patient::query();
            
            if ($request->has('start_date')) {
                $summaryQuery->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $summaryQuery->whereDate('created_at', '<=', $request->end_date);
            }
            
            $totalPatients = $summaryQuery->count();
            
            $genderDistribution = $summaryQuery->clone()
                ->selectRaw('gender, COUNT(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender');
            
            // Since we don't have date_of_birth, we'll use created_at as a fallback
            // This is a temporary solution - consider adding date_of_birth to the patients table
            $ageDistribution = [
                '0-1 year' => $summaryQuery->clone()->where('created_at', '>=', now()->subYear())->count(),
                '1-2 years' => $summaryQuery->clone()->whereBetween('created_at', [now()->subYears(2), now()->subYear()])->count(),
                '2-5 years' => $summaryQuery->clone()->whereBetween('created_at', [now()->subYears(5), now()->subYears(2)])->count(),
                '5+ years' => $summaryQuery->clone()->where('created_at', '<', now()->subYears(5))->count(),
                'unknown' => 0 // For any records that might not fit the above
            ];
            
            // Get new patients per month for the last 6 months
            $newPatientsByMonth = Patient::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('count', 'month');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'patients' => $formattedPatients,
                    'summary' => [
                        'total_patients' => $totalPatients,
                        'gender_distribution' => $genderDistribution,
                        'age_distribution' => $ageDistribution,
                        'new_patients_last_6_months' => $newPatientsByMonth
                    ],
                    'pagination' => [
                        'total' => $patients->total(),
                        'per_page' => $patients->perPage(),
                        'current_page' => $patients->currentPage(),
                        'last_page' => $patients->lastPage(),
                        'from' => $patients->firstItem(),
                        'to' => $patients->lastItem()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate patient report',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * Get general statistics report
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generalStatistics(Request $request): JsonResponse
    {
        try {
            // Date range for filtering
            $startDate = $request->input('start_date', now()->subMonth()->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());
            
            // Total patients
            $totalPatients = Patient::count();
            
            // New patients in date range
            $newPatients = Patient::whereBetween('created_at', [$startDate, $endDate])->count();
            
            // Total sessions in date range
            $totalSessions = PatientSession::whereBetween('started_at', [$startDate, $endDate])->count();
            
            // Completed sessions
            $completedSessions = PatientSession::where('status', 'completed')
                ->whereBetween('started_at', [$startDate, $endDate])
                ->count();
            
            // Total payments
            $totalIncome = Payment::where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');
                
            $totalExpenses = Payment::where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');
            
            // Appointments
            $totalAppointments = Appointment::whereBetween('appointment_date', [$startDate, $endDate])->count();
            $appointmentsByStatus = Appointment::selectRaw('status, COUNT(*) as count')
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->groupBy('status')
                ->pluck('count', 'status');
            
            // Session types
            $sessionsByType = PatientSession::selectRaw('session_type, COUNT(*) as count')
                ->whereBetween('started_at', [$startDate, $endDate])
                ->groupBy('session_type')
                ->pluck('count', 'session_type');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'date_range' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'patients' => [
                        'total' => $totalPatients,
                        'new_this_period' => $newPatients
                    ],
                    'sessions' => [
                        'total' => $totalSessions,
                        'completed' => $completedSessions,
                        'by_type' => $sessionsByType
                    ],
                    'payments' => [
                        'total_income' => $totalIncome,
                        'total_expenses' => $totalExpenses,
                        'net_income' => $totalIncome - $totalExpenses
                    ],
                    'appointments' => [
                        'total' => $totalAppointments,
                        'by_status' => $appointmentsByStatus
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate general statistics report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
