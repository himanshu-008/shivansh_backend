<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\FeePayment;
use App\Models\FeeInvoice;
use App\Models\FeeStructure;
use App\Models\BookSet;
use App\Models\DressItem;
use App\Models\InventoryTransaction;

class FeePaymentController extends Controller
{
    private function getNonAcademicCategoryIds($schoolId)
    {
        return DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->where('name', 'LIKE', '%Transport%')
                  ->orWhere('name', 'LIKE', '%Book%')
                  ->orWhere('name', 'LIKE', '%Stationery%')
                  ->orWhere('name', 'LIKE', '%Dress%')
                  ->orWhere('name', 'LIKE', '%Uniform%');
            })
            ->pluck('id')
            ->toArray();
    }

    public function index(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $sessionId = $request->query('session_id');
        $categoryId = $request->query('fee_category_id');
        $academicOnly = $request->boolean('academic_only') || $request->query('academic_only') == '1';
        $nonAcademicCatIds = $this->getNonAcademicCategoryIds($schoolId);

        $query = DB::table('fee_payments')
            ->join('students', 'fee_payments.student_id', '=', 'students.id')
            ->leftJoin('fee_categories', 'fee_payments.fee_category_id', '=', 'fee_categories.id')
            ->leftJoin('book_sets', 'students.book_set_id', '=', 'book_sets.id')
            ->leftJoin('dress_items', 'students.dress_item_id', '=', 'dress_items.id')
            ->where('fee_payments.school_id', $schoolId)
            ->select(
                'fee_payments.*',
                'fee_categories.name as fee_category_name',
                'students.name as student_name',
                'students.class_name',
                'students.section',
                'students.father_name',
                'students.admission_number',
                'students.contact_number',
                'book_sets.supplier_name as book_supplier_name',
                'book_sets.supplier_contact as book_supplier_contact',
                'book_sets.supplier_address as book_supplier_address',
                'book_sets.supplier_gstin as book_supplier_gstin',
                'dress_items.supplier_name as dress_supplier_name',
                'dress_items.supplier_contact as dress_supplier_contact',
                'dress_items.supplier_address as dress_supplier_address',
                'dress_items.supplier_gstin as dress_supplier_gstin'
            );

        if ($sessionId) {
            $query->where('fee_payments.academic_session_id', $sessionId);
        }
        if ($categoryId) {
            $query->where('fee_payments.fee_category_id', $categoryId);
        } elseif ($academicOnly && !empty($nonAcademicCatIds)) {
            $query->where(function($q) use ($nonAcademicCatIds) {
                $q->whereNull('fee_payments.fee_category_id')
                  ->orWhereNotIn('fee_payments.fee_category_id', $nonAcademicCatIds);
            });
        }

        if ($request->has('date') && !empty($request->date)) {
            $query->whereDate('fee_payments.payment_date', $request->date);
        }
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('fee_payments.payment_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('fee_payments.payment_date', '<=', $request->to_date);
        }

        $payments = $query->orderBy('fee_payments.payment_date', 'desc')
            ->orderBy('fee_payments.created_at', 'desc')
            ->get();

        // Attach student ledger statistics using bulk aggregations
        $studentIds = $payments->pluck('student_id')->unique()->toArray();
        if (!empty($studentIds)) {
            $invoicesGrouped = DB::table('fee_invoices')
                ->select('student_id', DB::raw('SUM(amount) as total_billed'))
                ->whereIn('student_id', $studentIds)
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->when($categoryId, fn($q) => $q->where('fee_category_id', $categoryId))
                ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                    $q->where(function($sub) use ($nonAcademicCatIds) {
                        $sub->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
                    });
                })
                ->groupBy('student_id')
                ->pluck('total_billed', 'student_id');

            $paymentsGrouped = DB::table('fee_payments')
                ->select('student_id', 'is_discount', DB::raw('SUM(amount) as total'))
                ->whereIn('student_id', $studentIds)
                ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
                ->when($categoryId, fn($q) => $q->where('fee_category_id', $categoryId))
                ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                    $q->where(function($sub) use ($nonAcademicCatIds) {
                        $sub->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
                    });
                })
                ->groupBy('student_id', 'is_discount')
                ->get();

            $paidMap = [];
            $discMap = [];
            foreach ($paymentsGrouped as $row) {
                if ($row->is_discount) {
                    $discMap[$row->student_id] = (float)$row->total;
                } else {
                    $paidMap[$row->student_id] = (float)$row->total;
                }
            }

            foreach ($payments as $payment) {
                $stId = $payment->student_id;
                $totalBilled = (float)($invoicesGrouped[$stId] ?? 0);
                $totalPaid = (float)($paidMap[$stId] ?? 0);
                $totalDiscount = (float)($discMap[$stId] ?? 0);
                $payment->total_billed = $totalBilled;
                $payment->total_paid = $totalPaid;
                $payment->total_discount = $totalDiscount;
                $payment->balance_due = $totalBilled - $totalDiscount - $totalPaid;
            }
        }

        return response()->json($payments);
    }

    public function ledger(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $sessionId = $request->query('session_id');
        $categoryId = $request->query('fee_category_id');
        $academicOnly = $request->boolean('academic_only') || $request->query('academic_only') == '1';
        $nonAcademicCatIds = $this->getNonAcademicCategoryIds($schoolId);

        // Fetch students with transport slab, book set, and dress item info
        $query = DB::table('students')
            ->leftJoin('transport_slabs', 'students.transport_slab_id', '=', 'transport_slabs.id')
            ->leftJoin('book_sets', 'students.book_set_id', '=', 'book_sets.id')
            ->leftJoin('dress_items', 'students.dress_item_id', '=', 'dress_items.id')
            ->where('students.school_id', $schoolId)
            ->select(
                'students.*',
                'transport_slabs.name as transport_slab_name',
                'transport_slabs.monthly_fee as transport_monthly_fee',
                'book_sets.name as book_set_name',
                'book_sets.amount as book_set_price',
                'book_sets.supplier_name as book_set_supplier_name',
                'book_sets.supplier_contact as book_set_supplier_contact',
                'book_sets.supplier_address as book_set_supplier_address',
                'book_sets.supplier_gstin as book_set_supplier_gstin',
                'dress_items.name as dress_item_name',
                'dress_items.amount as dress_item_price',
                'dress_items.supplier_name as dress_item_supplier_name',
                'dress_items.supplier_contact as dress_item_supplier_contact',
                'dress_items.supplier_address as dress_item_supplier_address',
                'dress_items.supplier_gstin as dress_item_supplier_gstin'
            );

        if ($request->has('class_name') && !empty($request->class_name)) {
            $query->where('students.class_name', $request->class_name);
        }

        if ($sessionId && $sessionId !== 'all') {
            $query->where(function($q) use ($sessionId) {
                $q->where('students.academic_session_id', $sessionId)
                  ->orWhereIn('students.id', function($sub) use ($sessionId) {
                      $sub->select('student_id')->from('fee_invoices')->where('academic_session_id', $sessionId);
                  })
                  ->orWhereIn('students.id', function($sub) use ($sessionId) {
                      $sub->select('student_id')->from('fee_payments')->where('academic_session_id', $sessionId);
                  });
            });
        }
        $students = $query->orderBy('students.class_name')->orderBy('students.name')->get();

        $categories = DB::table('fee_categories')->where('school_id', $schoolId)->get();
        if ($categoryId) {
            $categories = $categories->where('id', (int)$categoryId);
        } elseif ($academicOnly && !empty($nonAcademicCatIds)) {
            $categories = $categories->whereNotIn('id', $nonAcademicCatIds);
        }

        $tuitionCat = $categories->first(function($c) {
            return stripos($c->name, 'Tuition') !== false;
        }) ?? $categories->first();
        $tuitionCatId = $tuitionCat ? $tuitionCat->id : null;

        $classBookSets = DB::table('book_sets')->where('school_id', $schoolId)->get()->keyBy('class_name');

        // 1. Bulk aggregate all invoices grouped by student_id and fee_category_id
        $invoicesRaw = DB::table('fee_invoices')
            ->select('student_id', 'fee_category_id', DB::raw('SUM(amount) as total_amount'))
            ->where('school_id', $schoolId)
            ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
            ->when($categoryId, fn($q) => $q->where('fee_category_id', $categoryId))
            ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                $q->where(function($sub) use ($nonAcademicCatIds) {
                    $sub->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
                });
            })
            ->groupBy('student_id', 'fee_category_id')
            ->get();

        $invoicesMap = [];
        foreach ($invoicesRaw as $row) {
            $cId = $row->fee_category_id ?? $tuitionCatId;
            if (!isset($invoicesMap[$row->student_id][$cId])) {
                $invoicesMap[$row->student_id][$cId] = 0.0;
            }
            $invoicesMap[$row->student_id][$cId] += (float)$row->total_amount;
        }

        // 2. Bulk aggregate all payments & discounts grouped by student_id, fee_category_id, is_discount
        $paymentsRaw = DB::table('fee_payments')
            ->select('student_id', 'fee_category_id', 'is_discount', DB::raw('SUM(amount) as total_amount'))
            ->where('school_id', $schoolId)
            ->when($sessionId, fn($q) => $q->where('academic_session_id', $sessionId))
            ->when($categoryId, fn($q) => $q->where('fee_category_id', $categoryId))
            ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                $q->where(function($sub) use ($nonAcademicCatIds) {
                    $sub->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
                });
            })
            ->groupBy('student_id', 'fee_category_id', 'is_discount')
            ->get();

        $paymentsMap = [];
        foreach ($paymentsRaw as $row) {
            $discKey = $row->is_discount ? 1 : 0;
            $cId = $row->fee_category_id ?? $tuitionCatId;
            if (!isset($paymentsMap[$row->student_id][$cId][$discKey])) {
                $paymentsMap[$row->student_id][$cId][$discKey] = 0.0;
            }
            $paymentsMap[$row->student_id][$cId][$discKey] += (float)$row->total_amount;
        }

        // Build student ledger response in-memory (O(N) CPU operations)
        $ledgerData = [];
        foreach ($students as $student) {
            $stId = $student->id;
            $stInvoices = $invoicesMap[$stId] ?? [];
            $stPayments = $paymentsMap[$stId] ?? [];

            $totalBilled = 0.0;
            $totalPaid = 0.0;
            $totalDiscount = 0.0;

            $headsBreakdown = [];
            foreach ($categories as $cat) {
                $cId = $cat->id;
                $catBilled = $stInvoices[$cId] ?? 0.0;
                $catPaid = $stPayments[$cId][0] ?? 0.0;
                $catDiscount = $stPayments[$cId][1] ?? 0.0;
                $catBalance = $catBilled - $catDiscount - $catPaid;

                $totalBilled += $catBilled;
                $totalPaid += $catPaid;
                $totalDiscount += $catDiscount;

                $headsBreakdown[$cId] = [
                    'id' => $cId,
                    'name' => $cat->name,
                    'type' => $cat->type,
                    'billed' => $catBilled,
                    'paid' => $catPaid,
                    'discount' => $catDiscount,
                    'balance' => $catBalance,
                ];
            }

            $balanceDue = $totalBilled - $totalDiscount - $totalPaid;

            $bookSetName = $student->book_set_name;
            $bookSetPrice = $student->book_set_price != null ? (float)$student->book_set_price : null;
            $bookSetSupplierName = $student->book_set_supplier_name;
            $bookSetSupplierContact = $student->book_set_supplier_contact;
            $bookSetSupplierAddress = $student->book_set_supplier_address;
            $bookSetSupplierGstin = $student->book_set_supplier_gstin;

            if (!$bookSetName && isset($classBookSets[$student->class_name])) {
                $clsBook = $classBookSets[$student->class_name];
                $bookSetName = $clsBook->name . ' (Class Default)';
                $bookSetPrice = (float) $clsBook->amount;
                $bookSetSupplierName = $clsBook->supplier_name;
                $bookSetSupplierContact = $clsBook->supplier_contact;
                $bookSetSupplierAddress = $clsBook->supplier_address;
                $bookSetSupplierGstin = $clsBook->supplier_gstin;
            }

            $ledgerData[] = [
                'id' => $student->id,
                'student_id' => $student->id,
                'name' => $student->name,
                'class_name' => $student->class_name,
                'section' => $student->section,
                'admission_number' => $student->admission_number,
                'father_name' => $student->father_name,
                'contact_number' => $student->contact_number,
                'transport_slab_id' => $student->transport_slab_id,
                'transport_slab_name' => $student->transport_slab_name,
                'transport_monthly_fee' => $student->transport_monthly_fee,
                'book_set_id' => $student->book_set_id,
                'book_set_name' => $bookSetName,
                'book_set_price' => $bookSetPrice,
                'book_set_supplier_name' => $bookSetSupplierName,
                'book_set_supplier_contact' => $bookSetSupplierContact,
                'book_set_supplier_address' => $bookSetSupplierAddress,
                'book_set_supplier_gstin' => $bookSetSupplierGstin,
                'dress_item_id' => $student->dress_item_id,
                'dress_item_name' => $student->dress_item_name,
                'dress_item_price' => $student->dress_item_price,
                'dress_item_supplier_name' => $student->dress_item_supplier_name,
                'dress_item_supplier_contact' => $student->dress_item_supplier_contact,
                'dress_item_supplier_address' => $student->dress_item_supplier_address,
                'dress_item_supplier_gstin' => $student->dress_item_supplier_gstin,
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_discount' => $totalDiscount,
                'balance_due' => $balanceDue,
                'heads' => $headsBreakdown,
            ];
        }

        return response()->json($ledgerData);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'fee_category_id' => 'nullable|exists:fee_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'is_discount' => 'boolean',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $schoolId = Auth::user()->school_id;
        $sessionId = $request->academic_session_id;
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        $feeCategoryId = $request->fee_category_id;
        if (!$feeCategoryId) {
            $feeCategoryId = DB::table('fee_categories')
                ->where('school_id', $schoolId)
                ->where('name', 'LIKE', '%Tuition%')
                ->value('id') ?? DB::table('fee_categories')->where('school_id', $schoolId)->value('id');
        }

        $supplierName = $request->supplier_name;
        $supplierContact = $request->supplier_contact;
        if (!$supplierName && $feeCategoryId) {
            $catName = DB::table('fee_categories')->where('id', $feeCategoryId)->value('name');
            $student = DB::table('students')->where('id', $request->student_id)->first();
            if ($student && $catName) {
                if (stripos($catName, 'Book') !== false) {
                    $book = $student->book_set_id
                        ? DB::table('book_sets')->where('id', $student->book_set_id)->first()
                        : DB::table('book_sets')->where('school_id', $schoolId)->where('class_name', $student->class_name)->first();
                    if ($book && $book->supplier_name) {
                        $supplierName = $book->supplier_name;
                        $supplierContact = $book->supplier_contact;
                    }
                } elseif (stripos($catName, 'Dress') !== false || stripos($catName, 'Uniform') !== false) {
                    $dress = $student->dress_item_id
                        ? DB::table('dress_items')->where('id', $student->dress_item_id)->first()
                        : null;
                    if ($dress && $dress->supplier_name) {
                        $supplierName = $dress->supplier_name;
                        $supplierContact = $dress->supplier_contact;
                    }
                }
            }
        }

        $payment = FeePayment::create([
            'school_id' => $schoolId,
            'student_id' => $request->student_id,
            'academic_session_id' => $sessionId,
            'fee_category_id' => $feeCategoryId,
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'is_discount' => $request->is_discount ?? false,
            'transaction_id' => $request->transaction_id,
            'remarks' => $request->remarks,
            'supplier_name' => $supplierName,
            'supplier_contact' => $supplierContact,
        ]);

        // Auto-decrement inventory stock if this payment is for Books or Uniforms and not yet distributed
        if (!$payment->is_discount && $student) {
            $cat = DB::table('fee_categories')->where('id', $feeCategoryId)->first();
            $catName = $cat ? $cat->name : '';

            if (stripos($catName, 'Book') !== false || stripos($catName, 'Stationery') !== false) {
                $targetBook = null;
                if ($student->book_set_id) {
                    $targetBook = BookSet::where('school_id', $schoolId)->where('id', $student->book_set_id)->first();
                }
                if (!$targetBook && $student->class_name) {
                    $targetBook = BookSet::where('school_id', $schoolId)->where('class_name', $student->class_name)->first();
                }

                if ($targetBook) {
                    $alreadyDistributed = DB::table('inventory_transactions')
                        ->where('school_id', $schoolId)
                        ->where('item_type', 'book')
                        ->where('item_id', $targetBook->id)
                        ->where('student_id', $student->id)
                        ->where('transaction_type', 'distributed')
                        ->exists();

                    if (!$alreadyDistributed) {
                        $newStock = max(0, $targetBook->stock_quantity - 1);
                        $targetBook->update(['stock_quantity' => $newStock]);

                        InventoryTransaction::create([
                            'school_id' => $schoolId,
                            'item_type' => 'book',
                            'item_id' => $targetBook->id,
                            'transaction_type' => 'distributed',
                            'quantity' => -1,
                            'balance_after' => $newStock,
                            'student_id' => $student->id,
                            'reference_no' => 'PAY-' . $payment->id,
                            'remarks' => "Book set '{$targetBook->name}' distributed upon fee collection #{$payment->id}",
                            'created_by' => Auth::user()->name ?? 'Administrator',
                        ]);
                    }
                }
            } elseif (stripos($catName, 'Dress') !== false || stripos($catName, 'Uniform') !== false) {
                if ($student->dress_item_id) {
                    $targetDress = DressItem::where('school_id', $schoolId)->where('id', $student->dress_item_id)->first();
                    if ($targetDress) {
                        $alreadyDistributed = DB::table('inventory_transactions')
                            ->where('school_id', $schoolId)
                            ->where('item_type', 'dress')
                            ->where('item_id', $targetDress->id)
                            ->where('student_id', $student->id)
                            ->where('transaction_type', 'distributed')
                            ->exists();

                        if (!$alreadyDistributed) {
                            $newStock = max(0, $targetDress->stock_quantity - 1);
                            $targetDress->update(['stock_quantity' => $newStock]);

                            InventoryTransaction::create([
                                'school_id' => $schoolId,
                                'item_type' => 'dress',
                                'item_id' => $targetDress->id,
                                'transaction_type' => 'distributed',
                                'quantity' => -1,
                                'balance_after' => $newStock,
                                'student_id' => $student->id,
                                'reference_no' => 'PAY-' . $payment->id,
                                'remarks' => "Uniform '{$targetDress->name}' distributed upon fee collection #{$payment->id}",
                                'created_by' => Auth::user()->name ?? 'Administrator',
                            ]);
                        }
                    }
                }
            }
        }

        $academicOnly = $request->boolean('academic_only') || $request->query('academic_only') == '1';
        $nonAcademicCatIds = $this->getNonAcademicCategoryIds($schoolId);

        $invQuery = DB::table('fee_invoices')->where('student_id', $request->student_id);
        if ($sessionId) {
            $invQuery->where('academic_session_id', $sessionId);
        }
        if ($feeCategoryId) {
            $invQuery->where('fee_category_id', $feeCategoryId);
        } elseif ($academicOnly && !empty($nonAcademicCatIds)) {
            $invQuery->where(function($q) use ($nonAcademicCatIds) {
                $q->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
            });
        }
        $totalBilled = (float) $invQuery->sum('amount');

        $payQuery = DB::table('fee_payments')->where('student_id', $request->student_id);
        if ($sessionId) {
            $payQuery->where('academic_session_id', $sessionId);
        }
        if ($feeCategoryId) {
            $payQuery->where('fee_category_id', $feeCategoryId);
        } elseif ($academicOnly && !empty($nonAcademicCatIds)) {
            $payQuery->where(function($q) use ($nonAcademicCatIds) {
                $q->whereNull('fee_category_id')->orWhereNotIn('fee_category_id', $nonAcademicCatIds);
            });
        }

        $totalPaid = (float) (clone $payQuery)->where('is_discount', 0)->sum('amount');
        $totalDiscount = (float) (clone $payQuery)->where('is_discount', 1)->sum('amount');

        // If payment exceeds billed amount in this session and category (e.g. advance fee payment without prior invoice),
        // automatically generate a matching invoice so ledger balance remains consistent
        if ($totalPaid > $totalBilled) {
            $diff = $totalPaid - $totalBilled;
            $catId = $request->fee_category_id ?? DB::table('fee_categories')->where('school_id', $schoolId)->where('name', 'LIKE', '%Tuition%')->value('id') ?? DB::table('fee_categories')->where('school_id', $schoolId)->value('id');
            if ($catId) {
                FeeInvoice::create([
                    'school_id' => $schoolId,
                    'student_id' => $request->student_id,
                    'academic_session_id' => $sessionId,
                    'fee_category_id' => $catId,
                    'title' => 'Advance Tuition / Academic Fee',
                    'amount' => $diff,
                    'due_date' => $request->payment_date,
                    'status' => 'paid',
                ]);
                $totalBilled += $diff;
            }
        }

        $balanceDue = max(0.0, $totalBilled - $totalDiscount - $totalPaid);

        $paymentArr = $payment->toArray();
        $paymentArr['total_billed'] = $totalBilled;
        $paymentArr['total_paid'] = $totalPaid;
        $paymentArr['total_discount'] = $totalDiscount;
        $paymentArr['balance_due'] = $balanceDue;

        return response()->json($paymentArr, 201);
    }

    public function generateDues(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'class_name' => 'nullable|string',
            'month' => 'nullable|string',
        ]);

        $schoolId = Auth::user()->school_id ?? $request->school_id ?? 1;
        $sessionId = $request->academic_session_id;
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        // Fetch structures
        $structQuery = FeeStructure::where('school_id', $schoolId)->where('academic_session_id', $sessionId);
        if ($request->class_name) {
            $structQuery->where('class_name', $request->class_name);
        }
        $structures = $structQuery->get();

        // Fetch active students belonging to target session (or all if not partitioned)
        $stdQuery = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            });
        if ($sessionId) {
            $stdQuery->where(function($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)->orWhereNull('academic_session_id');
            });
        }
        if ($request->class_name) {
            $stdQuery->where('class_name', $request->class_name);
        }
        $students = $stdQuery->get();

        $generatedCount = 0;
        $skippedCount = 0;

        // 1. Generate Class Fee Structure Invoices
        foreach ($students as $student) {
            $classStructs = $structures->where('class_name', $student->class_name);
            foreach ($classStructs as $struct) {
                $title = $struct->feeCategory->name ?? 'Fee';
                $exists = DB::table('fee_invoices')
                    ->where('student_id', $student->id)
                    ->where('academic_session_id', $sessionId)
                    ->where('fee_category_id', $struct->fee_category_id)
                    ->exists();

                if (!$exists) {
                    FeeInvoice::create([
                        'school_id' => $schoolId,
                        'student_id' => $student->id,
                        'academic_session_id' => $sessionId,
                        'fee_category_id' => $struct->fee_category_id,
                        'title' => $title,
                        'amount' => $struct->amount,
                        'due_date' => now()->addDays(30),
                    ]);
                    $generatedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }

        if ($generatedCount === 0 && $skippedCount > 0) {
            return response()->json([
                'message' => "All $skippedCount student tuition dues were already generated. No duplicate dues created."
            ]);
        }

        return response()->json([
            'message' => "Tuition fee dues processing complete: $generatedCount new dues generated. ($skippedCount already existed and kept intact)."
        ]);
    }

    public function studentHistory($studentId, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $sessionId = $request->query('session_id');
        $categoryId = $request->query('fee_category_id');
        $academicOnly = $request->boolean('academic_only') || $request->query('academic_only') == '1';
        $nonAcademicCatIds = $this->getNonAcademicCategoryIds($schoolId);

        // If no session_id is provided, default to current running session
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id');
        }

        $invoices = DB::table('fee_invoices')
            ->leftJoin('academic_sessions', 'fee_invoices.academic_session_id', '=', 'academic_sessions.id')
            ->where('fee_invoices.school_id', $schoolId)
            ->where('fee_invoices.student_id', $studentId)
            ->when($sessionId && $sessionId !== 'all', fn($q) => $q->where('fee_invoices.academic_session_id', $sessionId))
            ->when($categoryId, fn($q) => $q->where('fee_invoices.fee_category_id', $categoryId))
            ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                $q->where(function($sub) use ($nonAcademicCatIds) {
                    $sub->whereNull('fee_invoices.fee_category_id')->orWhereNotIn('fee_invoices.fee_category_id', $nonAcademicCatIds);
                });
            })
            ->select('fee_invoices.*', 'academic_sessions.name as session_name')
            ->orderBy('fee_invoices.created_at', 'desc')
            ->get();

        $payments = DB::table('fee_payments')
            ->leftJoin('academic_sessions', 'fee_payments.academic_session_id', '=', 'academic_sessions.id')
            ->where('fee_payments.school_id', $schoolId)
            ->where('fee_payments.student_id', $studentId)
            ->when($sessionId && $sessionId !== 'all', fn($q) => $q->where('fee_payments.academic_session_id', $sessionId))
            ->when($categoryId, fn($q) => $q->where('fee_payments.fee_category_id', $categoryId))
            ->when($academicOnly && !empty($nonAcademicCatIds), function($q) use ($nonAcademicCatIds) {
                $q->where(function($sub) use ($nonAcademicCatIds) {
                    $sub->whereNull('fee_payments.fee_category_id')->orWhereNotIn('fee_payments.fee_category_id', $nonAcademicCatIds);
                });
            })
            ->select('fee_payments.*', 'academic_sessions.name as session_name')
            ->orderBy('fee_payments.payment_date', 'desc')
            ->get();

        return response()->json([
            'session_id' => $sessionId,
            'invoices' => $invoices,
            'payments' => $payments,
        ]);
    }
}
