<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Comprehensive Fees & Collections Report
     */
    public function feesReport(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $schoolId = $user->school_id;

        // Resolve Session ID
        $rawSession = $request->query('session_id');
        if ($rawSession === 'all') {
            $sessionId = null;
        } elseif ($rawSession) {
            $sessionId = (int)$rawSession;
        } else {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        $className = $request->query('class_name');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $paymentMethod = $request->query('payment_method');

        // 1. BASE INVOICES & PAYMENTS (Academic Fees)
        $invoiceQuery = DB::table('fee_invoices')->where('school_id', $schoolId);
        if ($sessionId) {
            $invoiceQuery->where('academic_session_id', $sessionId);
        }
        if ($className && $className !== 'All Classes') {
            $invoiceQuery->whereIn('student_id', function ($q) use ($schoolId, $className, $sessionId) {
                $q->select('id')->from('students')
                  ->where('school_id', $schoolId)
                  ->where('class_name', $className)
                  ->when($sessionId, fn($sq) => $sq->where('academic_session_id', $sessionId));
            });
        }
        $totalInvoiced = (float)($invoiceQuery->sum('amount') ?? 0);

        // Academic fee payments query
        $paymentQuery = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            });
        if ($sessionId) {
            $paymentQuery->where('academic_session_id', $sessionId);
        }
        if ($fromDate) {
            $paymentQuery->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentQuery->whereDate('payment_date', '<=', $toDate);
        }
        if ($paymentMethod && $paymentMethod !== 'all') {
            $paymentQuery->where('payment_method', $paymentMethod);
        }
        if ($className && $className !== 'All Classes') {
            $paymentQuery->whereIn('student_id', function ($q) use ($schoolId, $className, $sessionId) {
                $q->select('id')->from('students')
                  ->where('school_id', $schoolId)
                  ->where('class_name', $className)
                  ->when($sessionId, fn($sq) => $sq->where('academic_session_id', $sessionId));
            });
        }
        $totalCollected = (float)($paymentQuery->sum('amount') ?? 0);

        // Discounts
        $discountQuery = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('is_discount', 1);
        if ($sessionId) {
            $discountQuery->where('academic_session_id', $sessionId);
        }
        if ($className && $className !== 'All Classes') {
            $discountQuery->whereIn('student_id', function ($q) use ($schoolId, $className, $sessionId) {
                $q->select('id')->from('students')
                  ->where('school_id', $schoolId)
                  ->where('class_name', $className)
                  ->when($sessionId, fn($sq) => $sq->where('academic_session_id', $sessionId));
            });
        }
        $totalDiscount = (float)($discountQuery->sum('amount') ?? 0);
        $totalDue = max(0, $totalInvoiced - $totalDiscount - $totalCollected);
        $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;

        // Today & This Month
        $todayStr = Carbon::today()->toDateString();
        $todayCollected = (float)(DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->whereDate('payment_date', $todayStr)
            ->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            })
            ->sum('amount') ?? 0);

        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        $thisMonthCollected = (float)(DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->whereDate('payment_date', '>=', $startOfMonth)
            ->whereDate('payment_date', '<=', $endOfMonth)
            ->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            })
            ->sum('amount') ?? 0);

        // Payment Mode Breakdown
        $modeBreakdown = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            })
            ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->orderBy('total', 'desc')
            ->get();

        // 2. CLASS-WISE BREAKDOWN
        $classes = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
            ->select('class_name', DB::raw('COUNT(*) as student_count'))
            ->groupBy('class_name')
            ->get();

        $standardOrder = [
            'Play Group', 'Nursery', 'LKG', 'UKG',
            '1st', '2nd', '3rd', '4th', '5th',
            '6th', '7th', '8th', '9th', '10th',
            '11th', '12th'
        ];

        $classWiseData = [];
        foreach ($classes as $cls) {
            $cn = $cls->class_name;
            // Student IDs in this class
            $studentIds = DB::table('students')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->where('class_name', $cn)
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->pluck('id');

            $invoiced = (float)(DB::table('fee_invoices')
                ->where('school_id', $schoolId)
                ->whereIn('student_id', $studentIds)
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->sum('amount') ?? 0);

            $paid = (float)(DB::table('fee_payments')
                ->where('school_id', $schoolId)
                ->whereIn('student_id', $studentIds)
                ->where(function ($q) {
                    $q->where('is_discount', 0)->orWhereNull('is_discount');
                })
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->sum('amount') ?? 0);

            $discount = (float)(DB::table('fee_payments')
                ->where('school_id', $schoolId)
                ->whereIn('student_id', $studentIds)
                ->where('is_discount', 1)
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->sum('amount') ?? 0);

            $due = max(0, $invoiced - $discount - $paid);
            $rate = $invoiced > 0 ? round(($paid / $invoiced) * 100, 1) : 0;

            $classWiseData[$cn] = [
                'class_name' => $cn,
                'student_count' => $cls->student_count,
                'invoiced' => $invoiced,
                'collected' => $paid,
                'discount' => $discount,
                'due' => $due,
                'collection_rate' => $rate,
            ];
        }

        // Sort standard classes
        $sortedClassWise = [];
        foreach ($standardOrder as $so) {
            if (isset($classWiseData[$so])) {
                $sortedClassWise[] = $classWiseData[$so];
                unset($classWiseData[$so]);
            }
        }
        foreach ($classWiseData as $extra) {
            $sortedClassWise[] = $extra;
        }

        // 3. TOP DEFAULTERS LIST (Students with highest dues)
        $students = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
            ->when($className && $className !== 'All Classes', fn($q) => $q->where('class_name', $className))
            ->select('id', 'name', 'father_name', 'contact_number', 'class_name', 'section', 'roll_number', 'admission_number')
            ->get();

        $defaulters = [];
        foreach ($students as $st) {
            $inv = (float)(DB::table('fee_invoices')->where('student_id', $st->id)->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))->sum('amount') ?? 0);
            $pd = (float)(DB::table('fee_payments')->where('student_id', $st->id)->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            })->sum('amount') ?? 0);
            $disc = (float)(DB::table('fee_payments')->where('student_id', $st->id)->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))->where('is_discount', 1)->sum('amount') ?? 0);

            $pending = max(0, $inv - $disc - $pd);
            if ($pending > 0) {
                $defaulters[] = [
                    'student_id' => $st->id,
                    'name' => $st->name,
                    'father_name' => $st->father_name ?? '-',
                    'contact_number' => $st->contact_number ?? '-',
                    'class_name' => $st->class_name,
                    'section' => $st->section,
                    'roll_number' => $st->roll_number ?? '-',
                    'admission_number' => $st->admission_number ?? '-',
                    'invoiced' => $inv,
                    'paid' => $pd,
                    'due' => $pending,
                ];
            }
        }

        // Sort defaulters by highest due
        usort($defaulters, fn($a, $b) => $b['due'] <=> $a['due']);

        return response()->json([
            'summary' => [
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'total_discount' => $totalDiscount,
                'total_due' => $totalDue,
                'collection_rate' => $collectionRate,
                'today_collected' => $todayCollected,
                'this_month_collected' => $thisMonthCollected,
                'defaulters_count' => count($defaulters),
            ],
            'mode_breakdown' => $modeBreakdown,
            'class_wise' => $sortedClassWise,
            'defaulters' => array_slice($defaulters, 0, 50), // Top 50 defaulters
        ]);
    }

    /**
     * Comprehensive Uniform & Dress Module Report
     */
    public function dressReport(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $schoolId = $user->school_id;

        $rawSession = $request->query('session_id');
        if ($rawSession === 'all') {
            $sessionId = null;
        } elseif ($rawSession) {
            $sessionId = (int)$rawSession;
        } else {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }
        $className = $request->query('class_name');

        // 1. Dress Fee Category ID
        $dressCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where(function ($q) {
                $q->where('name', 'like', '%dress%')
                  ->orWhere('name', 'like', '%uniform%');
            })
            ->value('id');

        // All active students with dress items
        $studentsQuery = DB::table('students')
            ->leftJoin('dress_items', 'students.dress_item_id', '=', 'dress_items.id')
            ->where('students.school_id', $schoolId)
            ->where('students.status', 'active')
            ->when($sessionId, fn($q) => $q->where('students.academic_session_id', $sessionId))
            ->when($className && $className !== 'All Classes', fn($q) => $q->where('students.class_name', $className));

        $students = $studentsQuery->select(
            'students.id',
            'students.name',
            'students.father_name',
            'students.contact_number',
            'students.class_name',
            'students.section',
            'students.roll_number',
            'students.dress_item_id',
            'dress_items.name as dress_name',
            'dress_items.amount as dress_amount',
            'dress_items.gender_category as dress_category',
            'dress_items.size as dress_size'
        )->get();

        $totalAllocatedStudents = 0;
        $totalInvoiced = 0.0;
        $totalCollected = 0.0;
        $studentLedgers = [];
        $classBreakdown = [];

        foreach ($students as $st) {
            $isAllocated = !empty($st->dress_item_id);
            if ($isAllocated) {
                $totalAllocatedStudents++;
            }

            // Invoiced amount for dress in this session
            $invQuery = DB::table('fee_invoices')->where('student_id', $st->id);
            if ($sessionId) {
                $invQuery->where('academic_session_id', $sessionId);
            }
            if ($dressCatId) {
                $invQuery->where('fee_category_id', $dressCatId);
            } else {
                $invQuery->where('description', 'like', '%dress%');
            }
            $inv = (float)($invQuery->sum('amount') ?? ($st->dress_amount ?? 0));

            // Payments for dress in this session
            $payQuery = DB::table('fee_payments')->where('student_id', $st->id)->where(function ($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            });
            if ($sessionId) {
                $payQuery->where('academic_session_id', $sessionId);
            }
            if ($dressCatId) {
                $payQuery->where('fee_category_id', $dressCatId);
            } else {
                $payQuery->where('remarks', 'like', '%dress%');
            }
            $paid = (float)($payQuery->sum('amount') ?? 0);

            $due = max(0, $inv - $paid);
            $totalInvoiced += $inv;
            $totalCollected += $paid;

            // Class breakdown accumulation
            $c = $st->class_name;
            if (!isset($classBreakdown[$c])) {
                $classBreakdown[$c] = [
                    'class_name' => $c,
                    'total_students' => 0,
                    'allocated_students' => 0,
                    'invoiced' => 0.0,
                    'collected' => 0.0,
                    'due' => 0.0,
                ];
            }
            $classBreakdown[$c]['total_students']++;
            if ($isAllocated) $classBreakdown[$c]['allocated_students']++;
            $classBreakdown[$c]['invoiced'] += $inv;
            $classBreakdown[$c]['collected'] += $paid;
            $classBreakdown[$c]['due'] += $due;

            if ($isAllocated || $inv > 0) {
                $studentLedgers[] = [
                    'student_id' => $st->id,
                    'name' => $st->name,
                    'father_name' => $st->father_name ?? '-',
                    'contact_number' => $st->contact_number ?? '-',
                    'class_name' => $st->class_name,
                    'section' => $st->section,
                    'roll_number' => $st->roll_number ?? '-',
                    'dress_name' => $st->dress_name ?? 'Custom Uniform',
                    'dress_size' => $st->dress_size ?? '-',
                    'invoiced' => $inv,
                    'paid' => $paid,
                    'due' => $due,
                    'status' => $due <= 0 ? 'Fully Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
                ];
            }
        }

        $totalDue = max(0, $totalInvoiced - $totalCollected);

        // 2. Dress Catalog Items & Stock Value
        $catalogItems = DB::table('dress_items')
            ->where('school_id', $schoolId)
            ->get();

        $itemsSummary = [];
        $totalStockValue = 0.0;
        foreach ($catalogItems as $item) {
            $allocatedCount = DB::table('students')
                ->where('school_id', $schoolId)
                ->where('dress_item_id', $item->id)
                ->where('status', 'active')
                ->count();

            $price = (float)($item->amount ?? 0);
            $stock = (int)($item->stock_quantity ?? 0);
            $stockVal = $price * $stock;
            $totalStockValue += $stockVal;

            $itemsSummary[] = [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->gender_category ?? 'Uniform',
                'size' => $item->size ?? '-',
                'price' => $price,
                'stock_quantity' => $stock,
                'allocated_students_count' => $allocatedCount,
                'total_revenue_potential' => $price * $allocatedCount,
                'stock_value' => $stockVal,
                'supplier_name' => $item->supplier_name ?? '-',
            ];
        }

        return response()->json([
            'summary' => [
                'total_students_count' => $students->count(),
                'total_allocated_students' => $totalAllocatedStudents,
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'total_due' => $totalDue,
                'collection_rate' => $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0,
                'catalog_items_count' => $catalogItems->count(),
                'total_stock_value' => $totalStockValue,
            ],
            'class_wise' => array_values($classBreakdown),
            'catalog_items' => $itemsSummary,
            'student_ledgers' => $studentLedgers,
        ]);
    }

    /**
     * Consolidated Financial Overview / Profit & Loss Cashflow
     * Collections (Fees, Uniform, Books, Transport) vs School Expenses
     */
    public function financialOverview(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $schoolId = $user->school_id;

        $rawSession = $request->query('session_id');
        if ($rawSession === 'all') {
            $sessionId = null;
        } elseif ($rawSession) {
            $sessionId = (int)$rawSession;
        } else {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // 1. COLLECTIONS / INCOMES BY SOURCE
        // Fetch all fee payments grouped by category
        $paymentsQuery = DB::table('fee_payments')
            ->leftJoin('fee_categories', 'fee_payments.fee_category_id', '=', 'fee_categories.id')
            ->where('fee_payments.school_id', $schoolId)
            ->where(function ($q) {
                $q->where('fee_payments.is_discount', 0)->orWhereNull('fee_payments.is_discount');
            });

        if ($sessionId) {
            $paymentsQuery->where('fee_payments.academic_session_id', $sessionId);
        }
        if ($fromDate) {
            $paymentsQuery->whereDate('fee_payments.payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentsQuery->whereDate('fee_payments.payment_date', '<=', $toDate);
        }

        $allPayments = $paymentsQuery->select(
            'fee_payments.amount',
            'fee_payments.payment_method',
            'fee_payments.payment_date',
            'fee_categories.name as category_name'
        )->get();

        $tuitionIncome = 0.0;
        $transportIncome = 0.0;
        $booksIncome = 0.0;
        $uniformIncome = 0.0;
        $otherIncome = 0.0;

        foreach ($allPayments as $p) {
            $amt = (float)$p->amount;
            $cat = strtolower($p->category_name ?? '');
            if (str_contains($cat, 'transport') || str_contains($cat, 'bus')) {
                $transportIncome += $amt;
            } elseif (str_contains($cat, 'book')) {
                $booksIncome += $amt;
            } elseif (str_contains($cat, 'dress') || str_contains($cat, 'uniform')) {
                $uniformIncome += $amt;
            } elseif (str_contains($cat, 'tuition') || str_contains($cat, 'academic') || empty($cat)) {
                $tuitionIncome += $amt;
            } else {
                $otherIncome += $amt;
            }
        }

        $totalIncome = $tuitionIncome + $transportIncome + $booksIncome + $uniformIncome + $otherIncome;

        // 2. EXPENSES
        $expensesQuery = Expense::where('school_id', $schoolId);
        if ($sessionId) {
            $expensesQuery->where(function ($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)
                  ->orWhereNull('academic_session_id');
            });
        }
        if ($fromDate) {
            $expensesQuery->whereDate('expense_date', '>=', $fromDate);
        }
        if ($toDate) {
            $expensesQuery->whereDate('expense_date', '<=', $toDate);
        }

        $allExpenses = $expensesQuery->get();
        $totalExpense = (float)($allExpenses->sum('amount') ?? 0);

        // Group expenses by category
        $expenseCategories = [];
        foreach ($allExpenses as $exp) {
            $cat = $exp->category ?: 'Miscellaneous';
            $amt = (float)$exp->amount;
            if (!isset($expenseCategories[$cat])) {
                $expenseCategories[$cat] = ['category' => $cat, 'total' => 0.0, 'count' => 0];
            }
            $expenseCategories[$cat]['total'] += $amt;
            $expenseCategories[$cat]['count']++;
        }
        usort($expenseCategories, fn($a, $b) => $b['total'] <=> $a['total']);

        // 3. NET CASHFLOW
        $netBalance = $totalIncome - $totalExpense;
        $operatingMargin = $totalIncome > 0 ? round(($netBalance / $totalIncome) * 100, 1) : 0;

        return response()->json([
            'income' => [
                'total_income' => $totalIncome,
                'tuition_fees' => $tuitionIncome,
                'uniform_dress' => $uniformIncome,
                'books_stationery' => $booksIncome,
                'transport_fees' => $transportIncome,
                'other_collections' => $otherIncome,
                'transactions_count' => $allPayments->count(),
            ],
            'expense' => [
                'total_expense' => $totalExpense,
                'records_count' => $allExpenses->count(),
                'category_breakdown' => array_values($expenseCategories),
            ],
            'net_summary' => [
                'net_balance' => $netBalance,
                'is_surplus' => $netBalance >= 0,
                'operating_margin_percentage' => $operatingMargin,
            ],
        ]);
    }
}
