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
     * Get patient session history and statistics
     *
     * @param int $patientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPatientSessionHistory($patientId): JsonResponse
    {
        try {
            // Get patient details
            $patient = Patient::findOrFail($patientId);
            
            // Get all sessions for the patient
            $sessions = PatientSession::where('patient_id', $patientId)
                ->with(['notes', 'medicines.images'])
                ->orderBy('started_at', 'desc')
                ->get();
            
            // Calculate statistics
            $totalSessions = $sessions->count();
            $totalDuration = $sessions->sum('expected_duration');
            $averageMood = $sessions->avg(function($session) {
                return $session->notes->avg('mood_rating');
            });
            
            // Format session history
            $sessionHistory = $sessions->map(function($session) {
                $notes = $session->notes->first();
                
                return [
                    'id' => $session->id,
                    'date' => $session->started_at->format('Y-m-d'),
                    'time' => $session->started_at->format('H:i'),
                    'duration' => $session->expected_duration . ' mins',
                    'type' => $session->session_type === 'in_person' ? 'In Person' : 'Remote',
                    'session_notes' => $notes ? $notes->general_notes : null,
                    'clinical_notes' => $notes ? $notes->clinical_notes : null,
                    'mood_rating' => $notes ? $notes->mood_rating : null,
                    'has_medicines' => $session->medicines->isNotEmpty(),
                    'has_voice_notes' => $notes && $notes->voice_notes_path
                ];
            });
            
            $response = [
                'status' => 'success',
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
                        'total_duration' => $totalDuration . ' mins',
                        'average_mood' => round($averageMood, 1) ?: 'N/A',
                    ],
                    'session_history' => $sessionHistory
                ]
            ];
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch patient session history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
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
     * Display the specified patient session with all related data.
     *
     * @param  int  $id  The ID of the patient session
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $patientSession = PatientSession::with(['patient', 'notes', 'medicines.images'])->findOrFail($id);
        
        try {
            // Eager load all related data
            $session = $patientSession->load([
                'patient',
                'notes',
                'medicines.images'
            ]);

            // Format the response data
            $formattedSession = [
                'id' => $session->id,
                'patient' => [
                    'id' => $session->patient->id,
                    'name' => $session->patient->full_name,
                    'age' => $session->patient->age,
                    'gender' => $session->patient->gender,
                    'phone' => $session->patient->phone,
                    'email' => $session->patient->email
                ],
                'session_details' => [
                    'type' => $session->session_type === 'in_person' ? 'In Person' : 'Remote',
                    'status' => $session->status,
                    'started_at' => $session->started_at ? $session->started_at->format('Y-m-d H:i:s') : null,
                    'ended_at' => $session->ended_at ? $session->ended_at->format('Y-m-d H:i:s') : null,
                    'expected_duration' => $session->expected_duration . ' mins',
                    'purpose' => $session->purpose,
                    'created_at' => $session->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $session->updated_at->format('Y-m-d H:i:s')
                ],
                'notes' => $session->notes->map(function($note) {
                    return [
                        'id' => $note->id,
                        'general_notes' => $note->general_notes,
                        'physical_health_notes' => $note->physical_health_notes,
                        'mental_health_notes' => $note->mental_health_notes,
                        'clinical_notes' => $note->clinical_notes,
                        'mood_rating' => $note->mood_rating,
                        'voice_notes_path' => $note->voice_notes_path ? url('storage/' . $note->voice_notes_path) : null,
                        'created_at' => $note->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $note->updated_at->format('Y-m-d H:i:s')
                    ];
                }),
                'medicines' => $session->medicines->map(function($medicine) {
                    return [
                        'id' => $medicine->id,
                        'medicine_notes' => $medicine->medicine_notes,
                        'images' => $medicine->images->map(function($image) {
                            return [
                                'id' => $image->id,
                                'image_path' => url('storage/' . $image->image_path),
                                'created_at' => $image->created_at->format('Y-m-d H:i:s')
                            ];
                        }),
                        'created_at' => $medicine->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $medicine->updated_at->format('Y-m-d H:i:s')
                    ];
                })
            ];

            return response()->json([
                'status' => 'success',
                'data' => $formattedSession
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch session details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified patient session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_type' => 'sometimes|required|in:in_person,remote',
            'expected_duration' => 'sometimes|required|integer|min:1',
            'purpose' => 'sometimes|required|string|max:1000',
            'status' => 'sometimes|required|in:scheduled,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $session = PatientSession::findOrFail($id);
            
            // Update only the fields that were actually passed
            $updateData = [];
            if ($request->has('session_type')) {
                $updateData['session_type'] = $request->session_type;
            }
            if ($request->has('expected_duration')) {
                $updateData['expected_duration'] = $request->expected_duration;
            }
            if ($request->has('purpose')) {
                $updateData['purpose'] = $request->purpose;
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                
                // If status is being set to completed and ended_at is not set, set it to now
                if ($request->status === 'completed' && !$session->ended_at) {
                    $updateData['ended_at'] = now();
                }
                // If status is being set to in_progress and started_at is not set, set it to now
                if ($request->status === 'in_progress' && !$session->started_at) {
                    $updateData['started_at'] = now();
                }
            }
            
            $session->update($updateData);
            
            // Reload the session with relationships
            $session->load(['patient', 'notes', 'medicines.images']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Session updated successfully',
                'data' => $session
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completed sessions with search and filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompletedSessions(Request $request): JsonResponse
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
            if ($request->has('date')) {
                $date = now();
                switch ($request->date) {
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
                }
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $sessions = $query->paginate($perPage);
            
            // Format the response
            $formattedSessions = $sessions->map(function($session) {
                $notes = $session->notes->first();
                
                return [
                    'id' => $session->id,
                    'patientName' => $session->patient ? $session->patient->first_name . ' ' . $session->patient->last_name : 'Unknown',
                    'date' => $session->started_at->format('Y-m-d'),
                    'time' => $session->started_at->format('h:i A'),
                    'duration' => $session->expected_duration,
                    'type' => $session->session_type === 'in_person' ? 'in-person' : 'remote',
                    'status' => $session->status,
                    'notes' => $notes ? $notes->general_notes : null,
                    'sessionNotes' => $notes ? $notes->clinical_notes : null
                ];
            });
            
            return response()->json([
                'status' => 'success',
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
                'status' => 'error',
                'message' => 'Failed to fetch completed sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified patient session from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $session = PatientSession::with(['notes', 'medicines.images'])->findOrFail($id);
            
            // Use a transaction to ensure data consistency
            DB::beginTransaction();
            
            try {
                // Delete related records first (due to foreign key constraints)
                if ($session->notes) {
                    $session->notes()->delete();
                }
                
                // Delete medicine images and then medicines
                if ($session->medicines->isNotEmpty()) {
                    foreach ($session->medicines as $medicine) {
                        // Delete the images from storage (if needed)
                        // Storage::delete($medicine->images->pluck('image_path')->toArray());
                        
                        // Delete the images from database
                        $medicine->images()->delete();
                    }
                    // Delete the medicines
                    $session->medicines()->delete();
                }
                
                // Finally, delete the session
                $session->delete();
                
                DB::commit();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Session deleted successfully',
                    'data' => [
                        'deleted_session_id' => $id,
                        'deleted_at' => now()->toDateTimeString()
                    ]
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Re-throw to be caught by the outer catch
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
