<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PatientSession extends Model
{
    protected $fillable = [
        'patient_id',
        'session_type',
        'expected_duration',
        'purpose',
        'status',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expected_duration' => 'integer',
    ];

    /**
     * Get the patient that owns the session.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get all of the session's notes.
     */
    public function notes()
    {
        return $this->hasMany(SessionNote::class, 'session_id');
    }

    /**
     * Get the session's medicines.
     */
    /**
     * Get all of the session's medicines.
     */
    public function medicines(): HasMany
    {
        return $this->hasMany(SessionMedicine::class, 'session_id');
    }

    /**
     * Get all of the session's images.
     */
    public function images()
    {
        return $this->morphMany(MedicineImage::class, 'imageable');
    }
}

