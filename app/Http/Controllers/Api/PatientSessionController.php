<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientSession;
use App\Models\Patient;
use App\Models\SessionNote;
use App\Models\SessionMedicine;
use App\Models\MedicineImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PatientSessionController extends Controller
{
    /**
     * Display a listing of patient sessions with optional filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = PatientSession::with('patient');
        
        // Filter by patient_id if provided
        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        
        $sessions = $query->latest()->paginate(10);
        
        return response()->json([
            'status' => 'success',
            'data' => $sessions
        ]);
    }

    /**
     * Store a newly created patient session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Store a newly created patient session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'session_type' => 'required|in:in_person,remote',
            'expected_duration' => 'required|integer|min:1',
            'purpose' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $session = PatientSession::create([
            'patient_id' => $request->patient_id,
            'session_type' => $request->session_type,
            'expected_duration' => $request->expected_duration,
            'purpose' => $request->purpose,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session created successfully',
            'data' => [
                'session_id' => $session->id
            ]
        ], 201);
    }

    /**
     * Display the specified patient session.
     *
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Complete a session with notes and files
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
   
   
   
   
     // public function completeSession(Request $request, $id): JsonResponse
    // {
    //     return DB::transaction(function () use ($request, $id) {
    //         // Find and update the session
    //         $session = PatientSession::findOrFail($id);
    //         $session->status = 'completed';
    //         $session->ended_at = now();
    //         $session->save(); // Save the session first

    //         // Create and save session notes
    //         $sessionNotes = SessionNote::create([
    //             'session_id' => $session->id,
    //             'general_notes' => $request->input('general_notes'),
    //             'physical_health_notes' => $request->input('physical_health_notes'),
    //             'mental_health_notes' => $request->input('mental_health_notes'),
    //             'voice_notes_path' => $request->input('voice_notes_path')
    //         ]);

    //         // Save medicine details
    //         $sessionMedicine = SessionMedicine::create([
    //             'session_id' => $session->id,
    //             'medicine_notes' => $request->input('medicine_notes', '')
    //         ]);

    //         // Handle multiple image uploads
    //         if ($request->hasFile('medicine_images')) {
    //             $imagePaths = [];
    //             foreach ($request->file('medicine_images') as $image) {
    //                 if ($image->isValid()) {
    //                     $path = $image->store('medicine_images', 'public');
    //                     $imagePaths[] = $path;
    //                 }
    //             }
                
    //             // Update medicine notes with image paths if any images were uploaded
    //             if (!empty($imagePaths)) {
    //                 $sessionMedicine->update([
    //                     'medicine_notes' => trim($sessionMedicine->medicine_notes . "\n\nImages: " . implode("\n", $imagePaths))
    //                 ]);
    //             }
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Session completed successfully',
    //             'data' => [
    //                 'session' => $session->only(['id', 'patient_id', 'status', 'started_at', 'ended_at']),
    //                 'notes' => $sessionNotes,
    //                 'medicine' => $sessionMedicine->fresh()
    //             ]
    //         ]);
    //     });
    // }


    public function completeSession(Request $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            // Find and update the session
            $session = PatientSession::findOrFail($id);
            $session->status = 'completed';
            $session->ended_at = now();
            $session->save();
    
            // Create and save session notes
            $sessionNotes = SessionNote::create([
                'session_id' => $session->id,
                'general_notes' => $request->input('general_notes'),
                'physical_health_notes' => $request->input('physical_health_notes'),
                'mental_health_notes' => $request->input('mental_health_notes'),
                'voice_notes_path' => $request->input('voice_notes_path')
            ]);
    
            // Save medicine details first
            $sessionMedicine = SessionMedicine::create([
                'session_id' => $session->id,
                'medicine_notes' => $request->input('medicine_notes', '')
            ]);
    
            // Handle multiple image uploads
            $uploadedImages = [];
            if ($request->hasFile('medicine_images')) {
                foreach ($request->file('medicine_images') as $image) {
                    if ($image->isValid()) {
                        $path = $image->store('medicine_images', 'public');
                        
                        // Store image path in medicine_images table
                        $imageId = DB::table('medicine_images')->insertGetId([
                            'session_medicine_id' => $sessionMedicine->id,
                            'image_path' => $path,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        
                        $uploadedImages[] = [
                            'id' => $imageId,
                            'path' => $path,
                            'url' => asset('storage/' . $path)
                        ];
                    }
                }
            }
    
            // Get the uploaded images for the response
            $images = DB::table('medicine_images')
                ->where('session_medicine_id', $sessionMedicine->id)
                ->get();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Session completed successfully',
                'data' => [
                    'session' => $session->only(['id', 'patient_id', 'status', 'started_at', 'ended_at']),
                    'notes' => $sessionNotes,
                    'medicine' => array_merge($sessionMedicine->toArray(), ['images' => $images]),
                    'uploaded_images' => $uploadedImages
                ]
            ]);
        });
    }
   



    /**
     * Display the specified patient session.
     *
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(PatientSession $patientSession): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $patientSession->load('patient')
        ]);
    }

    /**
     * Update the specified patient session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, PatientSession $patientSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_type' => 'sometimes|in:in_person,remote',
            'expected_duration' => 'sometimes|integer|min:1',
            'purpose' => 'sometimes|string|max:1000',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update only the fields that are present in the request
        $patientSession->update($request->only([
            'session_type', 
            'expected_duration', 
            'purpose', 
            'status'
        ]));

        // If status is updated to in_progress and started_at is not set
        if ($request->has('status') && $request->status === 'in_progress' && !$patientSession->started_at) {
            $patientSession->update(['started_at' => now()]);
        }

        // If status is updated to completed and ended_at is not set
        if ($request->has('status') && $request->status === 'completed' && !$patientSession->ended_at) {
            $patientSession->update(['ended_at' => now()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Session updated successfully',
            'data' => $patientSession->refresh()->load('patient')
        ]);
    }

    /**
     * Remove the specified patient session from storage.
     *
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PatientSession $patientSession): JsonResponse
    {
        if ($patientSession->status === 'in_progress') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete a session that is in progress'
            ], 422);
        }

        $patientSession->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session deleted successfully'
        ]);
    }
    
    /**
     * Start a patient session.
     *
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(PatientSession $patientSession): JsonResponse
    {
        if ($patientSession->status !== 'scheduled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only scheduled sessions can be started'
            ], 422);
        }
        
        $patientSession->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Session started successfully',
            'data' => $patientSession->load('patient')
        ]);
    }
    
    /**
     * Complete a patient session.
     *
     * @param  \App\Models\PatientSession  $patientSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete(PatientSession $patientSession): JsonResponse
    {
        if ($patientSession->status !== 'in_progress') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only sessions in progress can be completed'
            ], 422);
        }
        
        $patientSession->update([
            'status' => 'completed',
            'ended_at' => now()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Session completed successfully',
            'data' => $patientSession->load('patient')
        ]);
    }
}
