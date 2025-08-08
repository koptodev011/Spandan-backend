<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceRecording extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'recording_path',
        'original_name',
        'file_size',
        'mime_type'
    ];
}
