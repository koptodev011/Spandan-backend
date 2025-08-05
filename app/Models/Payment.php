<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'amount',
        'description',
        'category',
        'date',
        'payment_method',
        'reference_number',
        'status',
        'patient_id',
        'notes',
        'type' // 'expense' or 'income'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    protected $attributes = [
        'status' => 'completed',
        'payment_method' => 'cash',
        'type' => 'expense',
    ];

    /**
     * Get the patient that owns the payment.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Scope a query to only include completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include expenses.
     */
    public function scopeExpenses($query)
    {
        return $query->where('type', 'expense');
    }

    /**
     * Scope a query to only include income/earnings.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }
}
