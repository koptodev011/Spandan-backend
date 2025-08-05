<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Get all payments with optional filters
     */
    public function index(Request $request)
    {
        $query = Payment::query();

        // Filter by type (expense/income) - defaults to all if not specified
        if ($request->has('type') && in_array($request->type, ['expense', 'income'])) {
            $query->where('type', $request->type);
        }

        // Apply filters if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $payments = $query->with('patient')
                         ->orderBy('date', 'desc')
                         ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Get payment summary
     */
    public function summary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        // Base query for expenses
        $expenseQuery = Payment::expenses();
        // Base query for income
        $incomeQuery = Payment::income();

        // Apply date filters if provided
        if ($startDate) {
            $expenseQuery->whereDate('date', '>=', $startDate);
            $incomeQuery->whereDate('date', '>=', $startDate);
        }
        if ($endDate) {
            $expenseQuery->whereDate('date', '<=', $endDate);
            $incomeQuery->whereDate('date', '<=', $endDate);
        }

        // Get expense summary
        $expenses = $expenseQuery->sum('amount');
        $expensesByCategory = $expenseQuery->clone()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        // Get income summary
        $income = $incomeQuery->sum('amount');
        $incomeByCategory = $incomeQuery->clone()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        // Calculate net income (income - expenses)
        $netIncome = $income - $expenses;

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => [
                    'total' => (float) $expenses,
                    'by_category' => $expensesByCategory
                ],
                'income' => [ 
                    'total' => (float) $income,
                    'by_category' => $incomeByCategory
                ],
                'net_income' => (float) $netIncome
            ]
        ]);
    }

    /**
     * Get payment categories
     */
    public function categories()
    {
        $expenseCategories = Payment::expenses()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
            
        $incomeCategories = Payment::income()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => [
                'expense_categories' => $expenseCategories,
                'income_categories' => $incomeCategories
            ]
        ]);
    }

    /**
     * Store a new payment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'type' => 'required|in:expense,income',
            'payment_method' => 'sometimes|string|in:cash,card,bank_transfer,upi,other',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'sometimes|string|in:pending,completed,failed,refunded',
            'patient_id' => 'nullable|exists:patients,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = Payment::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => $payment->load('patient')
        ], 201);
    }

    /**
     * Get a specific payment
     */
    public function show($id)
    {
        $payment = Payment::with('patient')->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }

    /**
     * Update a payment
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:100',
            'date' => 'sometimes|date',
            'payment_method' => 'sometimes|string|in:cash,card,bank_transfer,upi,other',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'sometimes|string|in:pending,completed,failed,refunded',
            'patient_id' => 'nullable|exists:patients,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment->load('patient')
        ]);
    }

    /**
     * Delete a payment (soft delete)
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
