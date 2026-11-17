<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DressItem;
use App\Models\FeeInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DressItemController extends Controller
{
    public function index()
    {
        $schoolId = Auth::user()->school_id;
        $items = DressItem::where('school_id', $schoolId)->get();

        // Auto-seed default dress items if empty
        if ($items->isEmpty()) {
            DressItem::insert([
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Regular Uniform Full Set', 'gender_category' => 'All', 'size' => 'Full Kit', 'amount' => 1500.00, 'description' => 'Includes Shirt, Trousers/Skirt, Belt, Socks & Tie', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'School Shirt (Half/Full Sleeve)', 'gender_category' => 'All', 'size' => 'Size 30', 'amount' => 350.00, 'description' => 'White School Cotton Shirt', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Uniform Trousers / Pant', 'gender_category' => 'Boys', 'size' => 'Size 30', 'amount' => 450.00, 'description' => 'Navy Blue Trousers', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Uniform Skirt / Tunic', 'gender_category' => 'Girls', 'size' => 'Size 28', 'amount' => 420.00, 'description' => 'Navy Blue Pleated Skirt', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Sports T-Shirt / House Shirt', 'gender_category' => 'All', 'size' => 'M', 'amount' => 280.00, 'description' => 'House Activity T-Shirt', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'School Tie', 'gender_category' => 'All', 'size' => 'Free Size', 'amount' => 80.00, 'description' => 'Standard Woven School Crest Tie', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Uniform Socks (Pair)', 'gender_category' => 'All', 'size' => 'Free Size', 'amount' => 50.00, 'description' => 'Cotton Ribbed Socks with Stripes', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Leather Belt with Logo Buckle', 'gender_category' => 'All', 'size' => 'Free Size', 'amount' => 90.00, 'description' => 'Adjustable School Belt', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Winter Blazer / Cardigan', 'gender_category' => 'All', 'size' => 'Size 32', 'amount' => 1200.00, 'description' => 'Woolen School Blazer with Crest', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $items = DressItem::where('school_id', $schoolId)->get();
        }

        $result = $items->map(function ($i) {
            $arr = $i->toArray();
            $arr['price'] = (float) $i->amount;
            return $arr;
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $amount = $request->amount ?? $request->price;
        $request->merge(['amount' => $amount]);

        $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'class_name' => 'nullable|string',
            'gender_category' => 'nullable|string',
            'size' => 'nullable|string',
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

        $item = DressItem::create([
            'school_id' => Auth::user()->school_id,
            'class_name' => $request->class_name,
            'name' => $request->name,
            'gender_category' => $request->gender_category ?? 'All',
            'size' => $request->size ?? 'Free Size',
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
                'item_type' => 'dress',
                'item_id' => $item->id,
                'transaction_type' => 'restock',
                'quantity' => $stockQuantity,
                'balance_after' => $stockQuantity,
                'remarks' => 'Initial stock intake upon uniform item creation' . ($request->supplier_name ? " (Supplier: {$request->supplier_name})" : ''),
                'created_by' => Auth::user()->name ?? 'Administrator',
            ]);
        }

        $res = $item->toArray();
        $res['price'] = (float) $item->amount;

        return response()->json($res, 201);
    }

    public function update($id, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $item = DressItem::where('school_id', $schoolId)->where('id', $id)->firstOrFail();

        $amount = $request->amount ?? $request->price ?? $item->amount;
        $request->merge(['amount' => $amount]);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'class_name' => 'nullable|string',
            'gender_category' => 'nullable|string',
            'size' => 'nullable|string',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_contact' => 'nullable|string|max:100',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_gstin' => 'nullable|string|max:50',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $item->update($request->only([
            'class_name',
            'name',
            'gender_category',
            'size',
            'supplier_name',
            'supplier_contact',
            'supplier_address',
            'supplier_gstin',
            'amount',
            'stock_quantity',
            'min_stock_alert',
            'description',
        ]));

        $res = $item->toArray();
        $res['price'] = (float) $item->amount;

        return response()->json($res);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $item = DressItem::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $item->delete();

        return response()->json(['message' => 'Dress item deleted successfully']);
    }

    public function assignDress($studentId, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'dress_item_id' => 'nullable|exists:dress_items,id',
        ]);

        $dressItemId = $request->dress_item_id;
        $prev = DB::table('students')->where('school_id', $schoolId)->where('id', $studentId)->value('dress_item_id');

        DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $studentId)
            ->update(['dress_item_id' => $dressItemId]);

        if ($dressItemId && $dressItemId != $prev) {
            $dress = DressItem::where('school_id', $schoolId)->where('id', $dressItemId)->first();
            if ($dress) {
                $newStock = max(0, $dress->stock_quantity - 1);
                $dress->update(['stock_quantity' => $newStock]);

                \App\Models\InventoryTransaction::create([
                    'school_id' => $schoolId,
                    'item_type' => 'dress',
                    'item_id' => $dressItemId,
                    'transaction_type' => 'distributed',
                    'quantity' => -1,
                    'balance_after' => $newStock,
                    'student_id' => $studentId,
                    'remarks' => "Uniform item assigned to student #$studentId",
                    'created_by' => Auth::user()->name ?? 'Administrator',
                ]);
            }
        }

        return response()->json(['message' => 'Uniform / dress item assigned successfully']);
    }

    public function bulkAssignDress(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'dress_item_id' => 'nullable|exists:dress_items,id',
        ]);

        $dressItemId = $request->dress_item_id;
        $studentIds = $request->student_ids;

        DB::table('students')
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->update(['dress_item_id' => $dressItemId]);

        $count = count($studentIds);

        if ($dressItemId && $count > 0) {
            $dress = DressItem::where('school_id', $schoolId)->where('id', $dressItemId)->first();
            if ($dress) {
                $newStock = max(0, $dress->stock_quantity - $count);
                $dress->update(['stock_quantity' => $newStock]);

                \App\Models\InventoryTransaction::create([
                    'school_id' => $schoolId,
                    'item_type' => 'dress',
                    'item_id' => $dressItemId,
                    'transaction_type' => 'distributed',
                    'quantity' => -$count,
                    'balance_after' => $newStock,
                    'remarks' => "Bulk uniform distribution to $count students",
                    'created_by' => Auth::user()->name ?? 'Administrator',
                ]);
            }
        }

        return response()->json(['message' => "Uniform kit assigned successfully to $count students"]);
    }

    public function issueCustomItems(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'items' => 'required|array',
            'items.*.title' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        $sessionId = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

        $dressCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->where('name', 'LIKE', '%Dress%')
                  ->orWhere('name', 'LIKE', '%Uniform%');
            })
            ->value('id');

        $invoicesCreated = 0;
        foreach ($request->student_ids as $studentId) {
            foreach ($request->items as $item) {
                FeeInvoice::create([
                    'school_id' => $schoolId,
                    'student_id' => $studentId,
                    'academic_session_id' => $sessionId,
                    'fee_category_id' => $dressCatId,
                    'title' => $item['title'],
                    'amount' => $item['amount'],
                    'due_date' => now()->addDays(30),
                ]);
                $invoicesCreated++;

                // Decrement stock if item matches catalog
                $dressId = $item['dress_item_id'] ?? null;
                $qty = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
                $dress = null;
                if ($dressId) {
                    $dress = DressItem::where('school_id', $schoolId)->where('id', $dressId)->first();
                }
                if (!$dress) {
                    $cleanTitle = preg_replace('/\s*\(.*\)$/', '', $item['title']);
                    $dress = DressItem::where('school_id', $schoolId)
                        ->where(function($q) use ($item, $cleanTitle) {
                            $q->where('name', $item['title'])
                              ->orWhere('name', $cleanTitle);
                        })->first();
                }
                if ($dress) {
                    $newStock = max(0, $dress->stock_quantity - $qty);
                    $dress->update(['stock_quantity' => $newStock]);
                    \App\Models\InventoryTransaction::create([
                        'school_id' => $schoolId,
                        'item_type' => 'dress',
                        'item_id' => $dress->id,
                        'transaction_type' => 'distributed',
                        'quantity' => -$qty,
                        'balance_after' => $newStock,
                        'student_id' => $studentId,
                        'remarks' => "Uniform item '{$item['title']}' issued to student #$studentId",
                        'created_by' => Auth::user()->name ?? 'Administrator',
                    ]);
                }
            }
        }

        $studentCount = count($request->student_ids);
        return response()->json([
            'message' => "Successfully issued uniform items to $studentCount student(s) ($invoicesCreated item dues added)."
        ]);
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

        $dressCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Dress%')
            ->orWhere('name', 'LIKE', '%Uniform%')
            ->value('id');

        $query = DB::table('students')->where('school_id', $schoolId);
        if ($request->class_name && $request->class_name !== 'All Classes') {
            $query->where('class_name', $request->class_name);
        }
        $students = $query->get();

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($students as $student) {
            if ($student->dress_item_id) {
                $dressItem = DB::table('dress_items')->where('id', $student->dress_item_id)->first();
                if ($dressItem && $dressItem->amount > 0) {
                    $title = "Dress & Uniform Fee ({$dressItem->name})";
                    $exists = DB::table('fee_invoices')
                        ->where('student_id', $student->id)
                        ->where('academic_session_id', $sessionId)
                        ->where(function($q) use ($dressCatId, $title) {
                            $q->where('fee_category_id', $dressCatId)
                              ->orWhere('title', $title);
                        })
                        ->exists();

                    if (!$exists) {
                        FeeInvoice::create([
                            'school_id' => $schoolId,
                            'student_id' => $student->id,
                            'academic_session_id' => $sessionId,
                            'fee_category_id' => $dressCatId,
                            'title' => $title,
                            'amount' => $dressItem->amount,
                            'due_date' => now()->addDays(30),
                        ]);
                        $generatedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }

        if ($generatedCount === 0 && $skippedCount > 0) {
            return response()->json([
                'message' => "All $skippedCount student uniform dues were already generated. No duplicate dues created."
            ]);
        }

        return response()->json([
            'message' => "Uniform dues processing complete: $generatedCount new dues generated. ($skippedCount already existed and kept intact)."
        ]);
    }
}
