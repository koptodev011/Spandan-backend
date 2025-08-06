<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Get all appointments with optional filtering and pagination
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Get appointments by date
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Get today's appointments for a patient where time is current or later
     *
     * @param  int  $patientId
     * @return \Illuminate\Http\Response
     */
   
   
   
     public function getTodaysUpcomingAppointment(Request $request)
    {
        $appointment = Appointment::where('patient_id', $request->patient_id)
            ->where('date', '>=', now()->toDateString())
            ->first();
    
        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'No appointment found'
            ]);
        }
        $appointmentTime = \Carbon\Carbon::parse($appointment->time);
        $now = now();
        if (!$now->lessThan($appointmentTime)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not allowed'
            ]);
        }
        return response()->json([
            'status' => 'success',
            'data' => $appointment
        ]);
    }



    
    

    /**
     * Get appointments by date
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getByDate(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = $request->input('date');
            
            // Query appointments for the given date with patient relationship
            $appointments = Appointment::with(['patient' => function($query) {
                    $query->select('id', 'full_name', 'phone');
                }])
                ->where('date', $date)
                ->get();
                        

            return response()->json([
                'status' => 'success',
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all appointments with optional filtering and pagination
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = Appointment::with('patient')
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc');

            // Filter by patient_id if provided
            if ($request->has('patient_id')) {
                $query->where('patient_id', $request->patient_id);
            }

            // Filter by date if provided
            if ($request->has('date')) {
                $query->where('date', $request->date);
            }

            // Paginate results (default 15 per page)
            $perPage = $request->input('per_page', 15);
            $appointments = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's appointments
     *
     * @return \Illuminate\Http\Response
     */
    public function today()
    {
        try {
            $today = now()->toDateString();
            
            $appointments = Appointment::with('patient')
                ->where('date', $today)
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch today\'s appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current appointment for a patient (for current date and time)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getCurrentAppointment(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'patient_id' => 'required|exists:patients,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentDate = now()->toDateString();
            $currentTime = now()->format('H:i:s');
            $patientId = $request->input('patient_id');
            
            // Find the appointment for the current patient, date, and time
            $appointment = Appointment::with('patient')
                ->where('patient_id', $patientId)
                ->where('date', $currentDate)
                ->where('time', '<=', $currentTime)
                ->orderBy('time', 'desc')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $appointment
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch current appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all upcoming appointments
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * Get all upcoming appointments with proper time validation
     * Returns only future appointments (including today's future time slots)
     *
     * @return \Illuminate\Http\Response
     */
    public function upcoming()
    {
        try {
            $now = now();
            $currentDate = $now->toDateString();
            $currentTime = $now->format('H:i:s');
            
            $appointments = Appointment::with('patient')
                ->where(function($query) use ($currentDate, $currentTime) {
                    // Get future dates
                    $query->where('date', '>', $currentDate)
                        // OR today's date with future times
                        ->orWhere(function($q) use ($currentDate, $currentTime) {
                            $q->where('date', $currentDate)
                              ->where('time', '>=', $currentTime);
                        });
                })
                // Additional validation to ensure we don't get past appointments
                ->where('date', '>=', $currentDate)
                ->where(function($query) use ($currentDate, $currentTime) {
                    $query->where('date', '>', $currentDate)
                          ->orWhere('time', '>=', $currentTime);
                })
                // Exclude cancelled appointments
                ->where('status', '!=', 'cancelled')
                // Order by soonest first
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'current_datetime' => $now->toDateTimeString(),
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch upcoming appointments',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Store a newly created appointment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'appointment_type' => 'required|string|max:100',
            'duration_minutes' => 'required|integer|min:1|max:480', // Max 8 hours
            'note' => 'nullable|string|max:1000',
        ]);

        // If validation fails, return error response
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if the patient exists
        $patient = Patient::find($request->patient_id);
        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Patient not found',
            ], 404);
        }

        // Check for appointment conflicts
        $conflictingAppointment = Appointment::where('date', $request->date)
            ->where('time', '>=', $request->time)
            ->where('time', '<', date('H:i', strtotime($request->time) + ($request->duration_minutes * 60)))
            ->exists();

        if ($conflictingAppointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'There is already an appointment scheduled at this time',
            ], 409);
        }

        try {
            // Create a new appointment
            $appointment = new Appointment($request->all());
            $appointment->save();

            // Return success response with the created appointment data
            return response()->json([
                'status' => 'success',
                'message' => 'Appointment created successfully',
                'data' => $appointment->load('patient'),
            ], 201);
        } catch (\Exception $e) {
            // Return error response if something goes wrong
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create appointment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get appointments for a specific patient
     *
     * @param  int  $id  Patient ID
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $appointments = Appointment::with('patient')
                ->where('patient_id', $id)
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->get();

            if ($appointments->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No appointments found for this patient',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch patient appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified appointment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id  Appointment ID
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        // Find the appointment
        $appointment = Appointment::find($id);
        
        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment not found',
            ], 404);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'patient_id' => 'sometimes|required|exists:patients,id',
            'date' => 'sometimes|required|date',
            'time' => 'sometimes|required|date_format:H:i',
            'appointment_type' => 'sometimes|required|string|max:100',
            'duration_minutes' => 'sometimes|required|integer|min:1|max:480',
            'note' => 'nullable|string|max:1000',
        ]);

        // If validation fails, return error response
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for appointment conflicts (excluding the current appointment)
        if ($request->has('date') && $request->has('time') && $request->has('duration_minutes')) {
            $conflictingAppointment = Appointment::where('id', '!=', $id)
                ->where('date', $request->date)
                ->where(function($query) use ($request) {
                    $query->whereBetween('time', [
                        $request->time,
                        date('H:i', strtotime($request->time) + ($request->duration_minutes * 60) - 1)
                    ]);
                })
                ->exists();

            if ($conflictingAppointment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'There is already another appointment scheduled at this time',
                ], 409);
            }
        }

        try {
            // Update the appointment
            $appointment->fill($request->all());
            $appointment->save();

            // Load the patient relationship for the response
            $appointment->load('patient');

            return response()->json([
                'status' => 'success',
                'message' => 'Appointment updated successfully',
                'data' => $appointment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update appointment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified appointment from storage.
     *
     * @param  string  $id  Appointment ID
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id)
    {
        try {
            // Find the appointment
            $appointment = Appointment::find($id);
            
            if (!$appointment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Appointment not found',
                ], 404);
            }
            
            // Delete the appointment
            $appointment->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Appointment deleted successfully',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete appointment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
