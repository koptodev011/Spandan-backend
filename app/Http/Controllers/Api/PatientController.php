<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    /**
     * Display a listing of patients with optional pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // Get pagination limit from request or use default
            $perPage = $request->input('per_page', 15);
            
            // Get sorting parameters
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            // Validate sort order
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }
            
            // Query patients with sorting and pagination
            $patients = Patient::orderBy($sortBy, $sortOrder)
                ->paginate($perPage);
                
            // Return success response with paginated patients
            return response()->json([
                'status' => 'success',
                'message' => 'Patients retrieved successfully',
                'data' => $patients->items(),
                'pagination' => [
                    'total' => $patients->total(),
                    'per_page' => $patients->perPage(),
                    'current_page' => $patients->currentPage(),
                    'last_page' => $patients->lastPage(),
                    'from' => $patients->firstItem(),
                    'to' => $patients->lastItem(),
                ],
                'sort' => [
                    'by' => $sortBy,
                    'order' => $sortOrder,
                ]
            ]);
            
        } catch (\Exception $e) {
            // Return error response if something goes wrong
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve patients',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Store a newly created patient in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Search patients by name
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:100',
                'limit' => 'sometimes|integer|min:1|max:50',
            ]);

            $query = $request->input('query');
            $limit = $request->input('limit', 10);

            $patients = Patient::where(function($q) use ($query) {
                    $q->where('full_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhere('phone', 'like', "%{$query}%");
                })
                ->select('id', 'full_name', 'email', 'phone')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Patients found',
                'data' => $patients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search patients',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created patient in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            // Patient fields
            'full_name' => 'required|string|max:255',
            'age' => 'required|integer|min:0|max:120',
            'gender' => 'required|in:male,female,other',
            'marital_status' => 'nullable|string|max:50',
            'profession' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:patients,email',
            'address' => 'required|string',
            'emergency_contact' => 'required|string|max:255',
            'medical_history' => 'nullable|string',
            'current_medication' => 'nullable|string',
            'allergies' => 'nullable|string',
            
            // Appointment fields
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'appointment_type' => 'required|in:in_person,remote',
            'duration_minutes' => 'required|integer|min:1|max:240',
            'appointment_note' => 'nullable|string',
        ]);

        // If validation fails, return error response
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Start database transaction
            return \DB::transaction(function () use ($request) {
                // Create patient record
                $patientData = [
                    'full_name' => $request->full_name,
                    'age' => $request->age,
                    'gender' => $request->gender,
                    'marital_status' => $request->marital_status,
                    'profession' => $request->profession,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'address' => $request->address,
                    'emergency_contact' => $request->emergency_contact,
                    'medical_history' => $request->medical_history,
                    'current_medication' => $request->current_medication,
                    'allergies' => $request->allergies,
                ];
                
                $patient = Patient::create($patientData);

                // Create appointment record
                $appointment = new \App\Models\Appointment([
                    'patient_id' => $patient->id,
                    'date' => $request->appointment_date,
                    'time' => $request->appointment_time,
                    'appointment_type' => $request->appointment_type,
                    'duration_minutes' => $request->duration_minutes,
                    'note' => $request->appointment_note,
                ]);
                
                $patient->appointments()->save($appointment);

                // Load the appointment relationship for the response
                $patient->load('appointments');

                // Return success response with both patient and appointment data
                return response()->json([
                    'status' => 'success',
                    'message' => 'Patient and appointment created successfully',
                    'data' => [
                        'patient' => $patient,
                        'appointment' => $patient->appointments->first()
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            // Return error response if something goes wrong
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create patient',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
