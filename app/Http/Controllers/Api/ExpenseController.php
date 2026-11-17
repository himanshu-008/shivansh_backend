<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with filtering and summary metrics.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized or missing school.'], 403);
        }
        $schoolId = $user->school_id;

        $query = Expense::where('school_id', $schoolId);

        // Filter by Academic Session
        if ($request->filled('session_id') && $request->session_id !== 'all') {
            $sessId = (int)$request->session_id;
            $query->where(function ($q) use ($sessId) {
                $q->where('academic_session_id', $sessId)
                  ->orWhereNull('academic_session_id');
            });
        }

        // Filter by Category
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by Payment Method
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by Date Range
        if ($request->filled('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        // Search in title, vendor name, invoice number, or description
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('vendor_name', 'like', $search)
                  ->orWhere('invoice_number', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        // Clone query for metrics calculations before sorting/pagination
        $metricsQuery = clone $query;
        $totalExpenses = (float)($metricsQuery->sum('amount') ?? 0);

        // Today's expenses for this school
        $todayStr = Carbon::today()->toDateString();
        $todayExpenses = (float)(Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $todayStr)
            ->sum('amount') ?? 0);

        // This month's expenses for this school
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        $thisMonthExpenses = (float)(Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', '>=', $startOfMonth)
            ->whereDate('expense_date', '<=', $endOfMonth)
            ->sum('amount') ?? 0);

        // Category breakdown
        $categoryBreakdown = (clone $query)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Payment method breakdown
        $methodBreakdown = (clone $query)
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Fetch ordered items
        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $expenses,
            'summary' => [
                'total_filtered_expenses' => $totalExpenses,
                'today_expenses' => $todayExpenses,
                'this_month_expenses' => $thisMonthExpenses,
                'total_count' => $expenses->count(),
                'category_breakdown' => $categoryBreakdown,
                'payment_method_breakdown' => $methodBreakdown,
            ],
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|string|max:100',
            'expense_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'vendor_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'receipt_url' => 'nullable|string',
            'description' => 'nullable|string',
            'academic_session_id' => 'nullable|integer',
        ]);

        $validated['school_id'] = $user->school_id;
        $validated['created_by'] = $user->id;
        if (empty($validated['payment_method'])) {
            $validated['payment_method'] = 'cash';
        }

        if (empty($validated['academic_session_id'])) {
            $currentSessionId = DB::table('academic_sessions')
                ->where('school_id', $user->school_id)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $user->school_id)->value('id');
            $validated['academic_session_id'] = $currentSessionId;
        }

        $expense = Expense::create($validated);

        return response()->json([
            'message' => 'Expense recorded successfully.',
            'data' => $expense,
        ], 201);
    }

    /**
     * Update an existing expense.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $expense = Expense::where('school_id', $user->school_id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'category' => 'sometimes|required|string|max:100',
            'expense_date' => 'sometimes|required|date',
            'payment_method' => 'nullable|string|max:50',
            'vendor_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'receipt_url' => 'nullable|string',
            'description' => 'nullable|string',
            'academic_session_id' => 'nullable|integer',
        ]);

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => $expense,
        ]);
    }

    /**
     * Delete an expense.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $expense = Expense::where('school_id', $user->school_id)->findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    /**
     * Return available expense categories.
     */
    public function categories()
    {
        $defaultCategories = [
            'Salaries & Staff',
            'Maintenance & Repairs',
            'Utilities & Bills',
            'Uniform & Dress Procurement',
            'Books & Printing',
            'Transport & Fuel',
            'Events & Sports',
            'Lab & IT Equipment',
            'Office & Stationery',
            'Taxes & Compliance',
            'Miscellaneous',
        ];

        $user = Auth::user();
        $existing = [];
        if ($user && $user->school_id) {
            $existing = Expense::where('school_id', $user->school_id)
                ->distinct()
                ->pluck('category')
                ->toArray();
        }

        $allCategories = array_values(array_unique(array_merge($defaultCategories, $existing)));

        return response()->json([
            'categories' => $allCategories,
        ]);
    }
}
