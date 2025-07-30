<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineImage extends Model
{
    protected $fillable = [
        'session_medicine_id',
        'image_path'
    ];

    /**
     * Get the session medicine that owns the image.
     */
    public function sessionMedicine(): BelongsTo
    {
        return $this->belongsTo(SessionMedicine::class, 'session_medicine_id');
    }
}
