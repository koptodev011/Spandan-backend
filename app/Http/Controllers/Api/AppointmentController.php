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
