<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class ExpenseController extends Controller
{
    /**
     * Get all expenses with optional filters
     */
    public function index(Request $request)
    {
        $query = Transaction::expenses();

        // Apply filters if provided
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('patient_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $expenses = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Get expense summary (total, by category, etc.)
     */
    public function summary(Request $request)
    {
        $query = Transaction::expenses();

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $total = $query->sum('amount');
        
        $byCategory = Transaction::expenses()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (float) $total,
                'by_category' => $byCategory
            ]
        ]);
    }

    /**
     * Store a new expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'patient_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense = Transaction::create([
            'type' => 'expense',
            'amount' => $request->amount,
            'description' => $request->description,
            'category' => $request->category,
            'date' => $request->date,
            'patient_name' => $request->patient_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense added successfully',
            'data' => $expense
        ], 201);
    }

    /**
     * Update an existing expense
     */
    public function update(Request $request, $id)
    {
        $expense = Transaction::expenses()->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'date' => 'required|date',
            'patient_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $expense->update([
            'amount' => $request->amount,
            'description' => $request->description,
            'category' => $request->category,
            'date' => $request->date,
            'patient_name' => $request->patient_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense
        ]);
    }

    /**
     * Delete an expense
     */
    public function destroy($id)
    {
        $expense = Transaction::expenses()->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * Get expense categories
     */
    public function categories()
    {
        $categories = Transaction::expenses()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
