<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    protected $fillable = [
        'session_id',
        'general_notes',
        'physical_health_notes',
        'mental_health_notes',
        'medicine_price',
        'voice_notes_path'
    ];

    /**
     * Get the session that owns the note.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PatientSession::class, 'session_id');
    }
}
