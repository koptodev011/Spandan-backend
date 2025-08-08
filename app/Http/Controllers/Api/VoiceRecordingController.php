<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VoiceRecordingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $recordings = VoiceRecording::latest()->get();
        return response()->json($recordings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Log the incoming request details
        \Log::info('Received file upload', [
            'hasFile' => $request->hasFile('recording'),
            'file' => $request->file('recording') ? [
                'originalName' => $request->file('recording')->getClientOriginalName(),
                'mimeType' => $request->file('recording')->getMimeType(),
                'extension' => $request->file('recording')->getClientOriginalExtension(),
                'size' => $request->file('recording')->getSize(),
            ] : null,
            'allFiles' => $request->allFiles(),
            'contentType' => $request->header('Content-Type')
        ]);

        try {
            $request->validate([
                'recording' => [
                    'required',
                    'file',
                    'max:10240', // 10MB max
                    function ($attribute, $value, $fail) {
                        $extension = strtolower($value->getClientOriginalExtension());
                        $allowed = ['m4a', 'mp3', 'wav', 'aac', 'ogg', 'mp4a'];
                        
                        if (!in_array($extension, $allowed)) {
                            $fail("The $attribute must be a file of type: " . implode(', ', $allowed));
                        }
                    },
                ],
            ]);

            // Store the uploaded file
            $file = $request->file('recording');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . Str::random(10) . '.' . $extension;
            
            $path = $file->storeAs('public/recordings', $fileName);
            
            if (!$path) {
                throw new \Exception('Failed to store the recording');
            }
            
            // Create a URL to access the file
            $url = Storage::url($path);

            // Save to database
            $recording = VoiceRecording::create([
                'recording_path' => $url,
                'original_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording uploaded successfully',
                'data' => [
                    'id' => $recording->id,
                    'url' => $url,
                    'original_name' => $originalName,
                    'file_size' => $file->getSize(),
                    'created_at' => $recording->created_at,
                ]
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error during file upload', [
                'error' => $e->errors(),
                'file' => $request->file('recording') ? $request->file('recording')->getClientOriginalName() : 'No file'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
            
        } catch (\Exception $e) {
            \Log::error('Error uploading file: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $request->file('recording') ? $request->file('recording')->getClientOriginalName() : 'No file'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload recording',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $recording = VoiceRecording::findOrFail($id);
        return response()->json($recording);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $recording = VoiceRecording::findOrFail($id);
        
        // Delete the file from storage
        $path = str_replace('/storage', 'public', $recording->recording_path);
        Storage::delete($path);
        
        // Delete from database
        $recording->delete();
        
        return response()->json([
            'message' => 'Recording deleted successfully'
        ]);
    }
}
