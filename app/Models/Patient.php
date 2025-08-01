<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'full_name',
        'age',
        'gender',
        'phone',
        'email',
        'address',
        'emergency_contact',
        'medical_history',
        'current_medication',
        'allergies'
    ];

    protected $casts = [
        // Add any necessary date casts here
    ];

    /**
     * Get the appointments for the patient.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
