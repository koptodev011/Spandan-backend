<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionMedicine extends Model
{
    protected $fillable = [
        'session_id',
        'medicine_notes'
    ];

    /**
     * Get the session that owns the medicine note.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PatientSession::class, 'session_id');
    }

    /**
     * Get the images for the medicine note.
     */
    public function images(): HasMany
    {
        return $this->hasMany(MedicineImage::class, 'session_medicine_id');
    }
}
