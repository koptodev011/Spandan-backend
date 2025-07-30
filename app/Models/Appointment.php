<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'date',
        'time',
        'appointment_type',
        'duration_minutes',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    /**
     * Get the patient that owns the appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
