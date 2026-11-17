<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookSet;
use App\Models\FeeInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookSetController extends Controller
{
    public function index()
    {
        $schoolId = Auth::user()->school_id;
        $sets = BookSet::where('school_id', $schoolId)->orderBy('class_name')->get();

        $result = $sets->map(function ($s) {
            $arr = $s->toArray();
            $arr['price'] = (float) $s->amount;
            return $arr;
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $amount = $request->amount ?? $request->price;
        $request->merge(['amount' => $amount]);

        $request->validate([
            'class_name' => 'required|string',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:100',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_gstin' => 'nullable|string|max:50',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $stockQuantity = (int) ($request->stock_quantity ?? 0);
        $minStockAlert = (int) ($request->min_stock_alert ?? 10);

        $set = BookSet::create([
            'school_id' => Auth::user()->school_id,
            'class_name' => $request->class_name,
            'name' => $request->name,
            'supplier_name' => $request->supplier_name,
            'supplier_contact' => $request->supplier_contact,
            'supplier_address' => $request->supplier_address,
            'supplier_gstin' => $request->supplier_gstin,
            'amount' => $amount,
            'stock_quantity' => $stockQuantity,
            'min_stock_alert' => $minStockAlert,
            'description' => $request->description,
        ]);

        if ($stockQuantity > 0) {
            \App\Models\InventoryTransaction::create([
                'school_id' => Auth::user()->school_id,
                'item_type' => 'book',
                'item_id' => $set->id,
                'transaction_type' => 'restock',
                'quantity' => $stockQuantity,
                'balance_after' => $stockQuantity,
                'remarks' => 'Initial stock intake upon book set creation' . ($request->supplier_name ? " (Supplier: {$request->supplier_name})" : ''),
                'created_by' => Auth::user()->name ?? 'Administrator',
            ]);
        }

        $this->syncUnpaidInvoicesForBookSet(Auth::user()->school_id, $set);

        $res = $set->toArray();
        $res['price'] = (float) $set->amount;

        return response()->json($res, 201);
    }

    public function update($id, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $set = BookSet::where('school_id', $schoolId)->where('id', $id)->firstOrFail();

        $amount = $request->amount ?? $request->price ?? $set->amount;
        $request->merge(['amount' => $amount]);

        $request->validate([
            'class_name' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:100',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_gstin' => 'nullable|string|max:50',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $set->update($request->only([
            'class_name',
            'name',
            'supplier_name',
            'supplier_contact',
            'supplier_address',
            'supplier_gstin',
            'amount',
            'stock_quantity',
            'min_stock_alert',
            'description',
        ]));

        $this->syncUnpaidInvoicesForBookSet($schoolId, $set);

        $res = $set->toArray();
        $res['price'] = (float) $set->amount;

        return response()->json($res);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $set = BookSet::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $set->delete();

        return response()->json(['message' => 'Book set deleted successfully']);
    }

    public function assignBookSet($studentId, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'book_set_id' => 'nullable|exists:book_sets,id',
        ]);

        $bookSetId = $request->book_set_id;
        $student = DB::table('students')->where('school_id', $schoolId)->where('id', $studentId)->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $prev = $student->book_set_id;

        DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $studentId)
            ->update(['book_set_id' => $bookSetId]);

        // Resolve target book set (explicit or class default)
        $targetBook = null;
        if ($bookSetId) {
            $targetBook = BookSet::where('school_id', $schoolId)->where('id', $bookSetId)->first();
        } elseif ($student->class_name) {
            $targetBook = BookSet::where('school_id', $schoolId)->where('class_name', $student->class_name)->first();
        }

        if ($targetBook) {
            $alreadyDistributed = DB::table('inventory_transactions')
                ->where('school_id', $schoolId)
                ->where('item_type', 'book')
                ->where('item_id', $targetBook->id)
                ->where('student_id', $studentId)
                ->where('transaction_type', 'distributed')
                ->exists();

            if (!$alreadyDistributed) {
                $newStock = max(0, $targetBook->stock_quantity - 1);
                $targetBook->update(['stock_quantity' => $newStock]);

                \App\Models\InventoryTransaction::create([
                    'school_id' => $schoolId,
                    'item_type' => 'book',
                    'item_id' => $targetBook->id,
                    'transaction_type' => 'distributed',
                    'quantity' => -1,
                    'balance_after' => $newStock,
                    'student_id' => $studentId,
                    'remarks' => "Book set '{$targetBook->name}' allocated to {$student->name} (#$studentId)",
                    'created_by' => Auth::user()->name ?? 'Administrator',
                ]);
            }
        }

        // If previously a different book set was distributed, return it
        if ($prev && (!$targetBook || $prev != $targetBook->id)) {
            $prevBook = BookSet::where('school_id', $schoolId)->where('id', $prev)->first();
            if ($prevBook) {
                $prevDistributed = DB::table('inventory_transactions')
                    ->where('school_id', $schoolId)
                    ->where('item_type', 'book')
                    ->where('item_id', $prev)
                    ->where('student_id', $studentId)
                    ->where('transaction_type', 'distributed')
                    ->exists();

                if ($prevDistributed) {
                    $newStock = $prevBook->stock_quantity + 1;
                    $prevBook->update(['stock_quantity' => $newStock]);

                    \App\Models\InventoryTransaction::create([
                        'school_id' => $schoolId,
                        'item_type' => 'book',
                        'item_id' => $prev,
                        'transaction_type' => 'return',
                        'quantity' => 1,
                        'balance_after' => $newStock,
                        'student_id' => $studentId,
                        'remarks' => "Book set '{$prevBook->name}' returned upon re-assignment from student #$studentId",
                        'created_by' => Auth::user()->name ?? 'Administrator',
                    ]);
                }
            }
        }

        $bookCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Book%')
            ->value('id');

        if ($bookCatId) {
            $hasPaid = DB::table('fee_payments')
                ->where('school_id', $schoolId)
                ->where('fee_category_id', $bookCatId)
                ->where('student_id', $studentId)
                ->exists();

            if (!$hasPaid) {
                if ($targetBook && $targetBook->amount > 0) {
                    $currentSessionId = DB::table('academic_sessions')
                        ->where('school_id', $schoolId)
                        ->where('is_current', 1)
                        ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

                    DB::table('fee_invoices')->updateOrInsert(
                        [
                            'school_id' => $schoolId,
                            'student_id' => $studentId,
                            'academic_session_id' => $currentSessionId,
                            'fee_category_id' => $bookCatId,
                        ],
                        [
                            'title' => "Books & Stationery Fee ({$targetBook->name})",
                            'amount' => $targetBook->amount,
                            'due_date' => now()->addDays(30),
                            'updated_at' => now(),
                        ]
                    );
                } else {
                    DB::table('fee_invoices')
                        ->where('school_id', $schoolId)
                        ->where('student_id', $studentId)
                        ->where('fee_category_id', $bookCatId)
                        ->delete();
                }
            }
        }

        return response()->json(['message' => 'Book set assigned successfully']);
    }

    public function bulkAssignBookSet(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'book_set_id' => 'nullable|exists:book_sets,id',
        ]);

        $bookSetId = $request->book_set_id;
        $studentIds = $request->student_ids;

        DB::table('students')
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->update(['book_set_id' => $bookSetId]);

        $count = count($studentIds);
        $students = DB::table('students')
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->get();

        $explicitBook = $bookSetId ? BookSet::where('school_id', $schoolId)->where('id', $bookSetId)->first() : null;

        foreach ($students as $student) {
            $targetBook = $explicitBook;
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

                    \App\Models\InventoryTransaction::create([
                        'school_id' => $schoolId,
                        'item_type' => 'book',
                        'item_id' => $targetBook->id,
                        'transaction_type' => 'distributed',
                        'quantity' => -1,
                        'balance_after' => $newStock,
                        'student_id' => $student->id,
                        'remarks' => "Bulk book allocation '{$targetBook->name}' to {$student->name} (#{$student->id})",
                        'created_by' => Auth::user()->name ?? 'Administrator',
                    ]);
                }
            }
        }

        $bookCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Book%')
            ->value('id');

        if ($bookCatId && $count > 0) {
            $currentSessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

            $targetBook = $bookSetId ? BookSet::where('school_id', $schoolId)->where('id', $bookSetId)->first() : null;

            foreach ($studentIds as $stId) {
                $hasPaid = DB::table('fee_payments')
                    ->where('school_id', $schoolId)
                    ->where('fee_category_id', $bookCatId)
                    ->where('student_id', $stId)
                    ->exists();

                if (!$hasPaid) {
                    $stBook = $targetBook;
                    if (!$stBook) {
                        $std = DB::table('students')->where('id', $stId)->first();
                        if ($std) {
                            $stBook = BookSet::where('school_id', $schoolId)->where('class_name', $std->class_name)->first();
                        }
                    }

                    if ($stBook && $stBook->amount > 0) {
                        DB::table('fee_invoices')->updateOrInsert(
                            [
                                'school_id' => $schoolId,
                                'student_id' => $stId,
                                'academic_session_id' => $currentSessionId,
                                'fee_category_id' => $bookCatId,
                            ],
                            [
                                'title' => "Books & Stationery Fee ({$stBook->name})",
                                'amount' => $stBook->amount,
                                'due_date' => now()->addDays(30),
                                'updated_at' => now(),
                            ]
                        );
                    } else {
                        DB::table('fee_invoices')
                            ->where('school_id', $schoolId)
                            ->where('student_id', $stId)
                            ->where('fee_category_id', $bookCatId)
                            ->delete();
                    }
                }
            }
        }

        return response()->json(['message' => "Book set assigned successfully to $count students"]);
    }

    public function generateDues(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'class_name' => 'nullable|string',
        ]);

        $schoolId = Auth::user()->school_id ?? $request->school_id ?? 1;
        $sessionId = $request->academic_session_id;
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        $bookCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Book%')
            ->value('id');

        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            });
        if ($request->class_name && $request->class_name !== 'All Classes') {
            $query->where('class_name', $request->class_name);
        }
        $students = $query->get();

        $generatedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($students as $student) {
            $bookSet = null;
            if ($student->book_set_id) {
                $bookSet = DB::table('book_sets')->where('id', $student->book_set_id)->first();
            } else {
                $bookSet = DB::table('book_sets')
                    ->where('school_id', $schoolId)
                    ->where('class_name', $student->class_name)
                    ->first();
            }

            if ($bookSet && $bookSet->amount > 0) {
                $title = "Books & Stationery Fee ({$bookSet->name})";
                $existingInv = DB::table('fee_invoices')
                    ->where('student_id', $student->id)
                    ->where('academic_session_id', $sessionId)
                    ->where('fee_category_id', $bookCatId)
                    ->first();

                if ($existingInv) {
                    $hasPaid = DB::table('fee_payments')
                        ->where('student_id', $student->id)
                        ->where('academic_session_id', $sessionId)
                        ->where('fee_category_id', $bookCatId)
                        ->exists();

                    if (!$hasPaid && ((float)$existingInv->amount !== (float)$bookSet->amount || $existingInv->title !== $title)) {
                        DB::table('fee_invoices')->where('id', $existingInv->id)->update([
                            'amount' => $bookSet->amount,
                            'title' => $title,
                            'updated_at' => now(),
                        ]);
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                } else {
                    FeeInvoice::create([
                        'school_id' => $schoolId,
                        'student_id' => $student->id,
                        'academic_session_id' => $sessionId,
                        'fee_category_id' => $bookCatId,
                        'title' => $title,
                        'amount' => $bookSet->amount,
                        'due_date' => now()->addDays(30),
                    ]);
                    $generatedCount++;
                }
            } else {
                // If student has no book set and no class default book set, remove any unpaid dummy invoice
                $hasPaid = DB::table('fee_payments')
                    ->where('student_id', $student->id)
                    ->where('academic_session_id', $sessionId)
                    ->where('fee_category_id', $bookCatId)
                    ->exists();

                if (!$hasPaid) {
                    DB::table('fee_invoices')
                        ->where('student_id', $student->id)
                        ->where('academic_session_id', $sessionId)
                        ->where('fee_category_id', $bookCatId)
                        ->delete();
                }
            }
        }

        return response()->json([
            'message' => "Book dues processing complete: $generatedCount new dues generated, $updatedCount updated to active catalog prices. ($skippedCount already up to date)."
        ]);
    }

    private function syncUnpaidInvoicesForBookSet($schoolId, $bookSet)
    {
        $bookCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Book%')
            ->value('id');

        if (!$bookCatId) return;

        $studentIds = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) use ($bookSet) {
                $q->where('book_set_id', $bookSet->id)
                  ->orWhere(function($sub) use ($bookSet) {
                      $sub->where('class_name', $bookSet->class_name)
                          ->whereNull('book_set_id');
                  });
            })
            ->pluck('id');

        if ($studentIds->isEmpty()) return;

        $paidStudentIds = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('fee_category_id', $bookCatId)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->unique()
            ->toArray();

        $unpaidStudentIds = $studentIds->diff($paidStudentIds);
        if ($unpaidStudentIds->isNotEmpty()) {
            DB::table('fee_invoices')
                ->where('school_id', $schoolId)
                ->where('fee_category_id', $bookCatId)
                ->whereIn('student_id', $unpaidStudentIds)
                ->update([
                    'amount' => $bookSet->amount,
                    'title' => "Books & Stationery Fee ({$bookSet->name})",
                    'updated_at' => now(),
                ]);
        }
    }
}
