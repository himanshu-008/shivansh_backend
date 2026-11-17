<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookSet;
use App\Models\DressItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get inventory overview metrics and low stock alerts.
     */
    public function overview(Request $request)
    {
        $schoolId = Auth::user()->school_id;

        $bookSets = BookSet::where('school_id', $schoolId)->get();
        $dressItems = DressItem::where('school_id', $schoolId)->get();

        $totalBooksStock = $bookSets->sum('stock_quantity');
        $totalDressStock = $dressItems->sum('stock_quantity');

        $booksLowStock = $bookSets->filter(fn($b) => $b->stock_quantity <= $b->min_stock_alert && $b->stock_quantity > 0)->count();
        $dressLowStock = $dressItems->filter(fn($d) => $d->stock_quantity <= $d->min_stock_alert && $d->stock_quantity > 0)->count();

        $booksOutOfStock = $bookSets->filter(fn($b) => $b->stock_quantity <= 0)->count();
        $dressOutOfStock = $dressItems->filter(fn($d) => $d->stock_quantity <= 0)->count();

        $booksValuation = $bookSets->sum(fn($b) => $b->stock_quantity * (float)$b->amount);
        $dressValuation = $dressItems->sum(fn($d) => $d->stock_quantity * (float)$d->amount);

        // Recent 15 transactions
        $transactions = InventoryTransaction::where('school_id', $schoolId)
            ->with(['student:id,admission_number,name,class_name,section'])
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($tx) use ($schoolId) {
                $itemName = 'Unknown Item';
                if ($tx->item_type === 'book') {
                    $book = BookSet::find($tx->item_id);
                    $itemName = $book ? "{$book->name} ({$book->class_name})" : "Book #{$tx->item_id}";
                } elseif ($tx->item_type === 'dress') {
                    $dress = DressItem::find($tx->item_id);
                    $itemName = $dress ? "{$dress->name} [{$dress->size}]" : "Dress #{$tx->item_id}";
                }

                $arr = $tx->toArray();
                $arr['item_name'] = $itemName;
                return $arr;
            });

        // Alerts list (items that are low or out of stock)
        $alerts = [];
        foreach ($bookSets as $b) {
            if ($b->stock_quantity <= $b->min_stock_alert) {
                $alerts[] = [
                    'item_type' => 'book',
                    'item_id' => $b->id,
                    'name' => $b->name,
                    'sub_title' => "Class: {$b->class_name}",
                    'stock_quantity' => $b->stock_quantity,
                    'min_stock_alert' => $b->min_stock_alert,
                    'status' => $b->stock_quantity <= 0 ? 'out_of_stock' : 'low_stock',
                ];
            }
        }
        foreach ($dressItems as $d) {
            if ($d->stock_quantity <= $d->min_stock_alert) {
                $alerts[] = [
                    'item_type' => 'dress',
                    'item_id' => $d->id,
                    'name' => $d->name,
                    'sub_title' => "Size: {$d->size} | {$d->gender_category}",
                    'stock_quantity' => $d->stock_quantity,
                    'min_stock_alert' => $d->min_stock_alert,
                    'status' => $d->stock_quantity <= 0 ? 'out_of_stock' : 'low_stock',
                ];
            }
        }

        $supplierReturnsCount = abs((int)InventoryTransaction::where('school_id', $schoolId)
            ->where('transaction_type', 'supplier_return')
            ->sum('quantity'));

        $supplierReturnsValuation = (float)(InventoryTransaction::where('school_id', $schoolId)
            ->where('transaction_type', 'supplier_return')
            ->selectRaw('SUM(ABS(quantity) * COALESCE(unit_cost, 0)) as total_val')
            ->value('total_val') ?? 0);

        return response()->json([
            'summary' => [
                'total_books_stock' => $totalBooksStock,
                'total_dress_stock' => $totalDressStock,
                'total_units_stock' => $totalBooksStock + $totalDressStock,
                'total_items_count' => $bookSets->count() + $dressItems->count(),
                'books_items_count' => $bookSets->count(),
                'dress_items_count' => $dressItems->count(),
                'low_stock_count' => $booksLowStock + $dressLowStock,
                'out_of_stock_count' => $booksOutOfStock + $dressOutOfStock,
                'total_valuation' => $booksValuation + $dressValuation,
                'books_valuation' => $booksValuation,
                'dress_valuation' => $dressValuation,
                'total_supplier_returns_count' => $supplierReturnsCount,
                'total_supplier_returns_valuation' => $supplierReturnsValuation,
            ],
            'alerts' => $alerts,
            'recent_transactions' => $transactions,
        ]);
    }

    /**
     * Get list of inventory items with full stock tracking details.
     */
    public function items(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $type = $request->query('type', 'all'); // 'all', 'book', 'dress'
        $status = $request->query('status', 'all'); // 'all', 'in_stock', 'low_stock', 'out_of_stock'
        $search = strtolower($request->query('search', ''));

        $items = collect();

        if ($type === 'all' || $type === 'book') {
            $books = BookSet::where('school_id', $schoolId)->get();
            foreach ($books as $b) {
                $items->push([
                    'id' => $b->id,
                    'item_type' => 'book',
                    'name' => $b->name,
                    'category' => $b->class_name,
                    'sub_detail' => "Class: {$b->class_name}",
                    'price' => (float)$b->amount,
                    'stock_quantity' => (int)$b->stock_quantity,
                    'min_stock_alert' => (int)$b->min_stock_alert,
                    'supplier_name' => $b->supplier_name,
                    'supplier_contact' => $b->supplier_contact,
                    'supplier_address' => $b->supplier_address,
                    'supplier_gstin' => $b->supplier_gstin,
                    'description' => $b->description,
                    'updated_at' => $b->updated_at ? $b->updated_at->toIso8601String() : null,
                ]);
            }
        }

        if ($type === 'all' || $type === 'dress') {
            $dresses = DressItem::where('school_id', $schoolId)->get();
            foreach ($dresses as $d) {
                $items->push([
                    'id' => $d->id,
                    'item_type' => 'dress',
                    'name' => $d->name,
                    'category' => $d->class_name ?? 'All Classes',
                    'sub_detail' => "Size: {$d->size} • {$d->gender_category}",
                    'size' => $d->size,
                    'gender_category' => $d->gender_category,
                    'price' => (float)$d->amount,
                    'stock_quantity' => (int)$d->stock_quantity,
                    'min_stock_alert' => (int)$d->min_stock_alert,
                    'supplier_name' => $d->supplier_name,
                    'supplier_contact' => $d->supplier_contact,
                    'supplier_address' => $d->supplier_address,
                    'supplier_gstin' => $d->supplier_gstin,
                    'description' => $d->description,
                    'updated_at' => $d->updated_at ? $d->updated_at->toIso8601String() : null,
                ]);
            }
        }

        // Apply filters
        if (!empty($search)) {
            $items = $items->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['name']), $search) ||
                       str_contains(strtolower($item['category']), $search) ||
                       str_contains(strtolower($item['sub_detail']), $search);
            });
        }

        if ($status === 'out_of_stock') {
            $items = $items->filter(fn($i) => $i['stock_quantity'] <= 0);
        } elseif ($status === 'low_stock') {
            $items = $items->filter(fn($i) => $i['stock_quantity'] > 0 && $i['stock_quantity'] <= $i['min_stock_alert']);
        } elseif ($status === 'in_stock') {
            $items = $items->filter(fn($i) => $i['stock_quantity'] > $i['min_stock_alert']);
        }

        return response()->json($items->values());
    }

    /**
     * Adjust or restock an inventory item.
     */
    public function adjust(Request $request)
    {
        $schoolId = Auth::user()->school_id;

        $request->validate([
            'item_type' => 'required|in:book,dress',
            'item_id' => 'required|integer',
            'transaction_type' => 'required|in:restock,adjustment,damaged,return,distributed,supplier_return',
            'quantity' => 'required|integer',
            'unit_cost' => 'nullable|numeric|min:0',
            'reference_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $schoolId) {
            $itemType = $request->item_type;
            $itemId = $request->item_id;
            $txType = $request->transaction_type;
            $qty = abs((int)$request->quantity);

            if ($itemType === 'book') {
                $item = BookSet::where('school_id', $schoolId)->where('id', $itemId)->lockForUpdate()->firstOrFail();
            } else {
                $item = DressItem::where('school_id', $schoolId)->where('id', $itemId)->lockForUpdate()->firstOrFail();
            }

            // Guard against reducing more stock than is on-hand
            if (in_array($txType, ['damaged', 'distributed', 'supplier_return'])) {
                if ($qty > $item->stock_quantity) {
                    $actionName = match ($txType) {
                        'supplier_return' => 'return to supplier',
                        'damaged' => 'write off as damaged',
                        default => 'distribute',
                    };
                    return response()->json([
                        'message' => "Cannot {$actionName} {$qty} units. Only {$item->stock_quantity} units are currently in stock."
                    ], 422);
                }
            }

            // Determine stock delta
            $delta = match ($txType) {
                'restock', 'return' => $qty,
                'damaged', 'distributed', 'supplier_return' => -$qty,
                'adjustment' => (int)$request->quantity, // can be positive or negative
                default => $qty,
            };

            $newBalance = max(0, $item->stock_quantity + $delta);
            $item->stock_quantity = $newBalance;
            $item->save();

            $operator = Auth::user()->name ?? 'Administrator';

            $defaultRemark = match ($txType) {
                'supplier_return' => 'Returned unsold stock to supplier/manufacturer',
                'restock' => 'Inward stock received from supplier',
                'damaged' => 'Damaged/expired stock write-off',
                'return' => 'Student return to inventory',
                'distributed' => 'Manual issue/distribution',
                default => "Manual {$txType} adjustment",
            };

            $tx = InventoryTransaction::create([
                'school_id' => $schoolId,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'transaction_type' => $txType,
                'quantity' => $delta,
                'balance_after' => $newBalance,
                'reference_no' => $request->reference_no,
                'unit_cost' => $request->unit_cost,
                'remarks' => $request->remarks ?: $defaultRemark,
                'created_by' => $operator,
            ]);

            return response()->json([
                'message' => 'Inventory updated successfully',
                'item' => [
                    'id' => $item->id,
                    'item_type' => $itemType,
                    'stock_quantity' => $item->stock_quantity,
                    'min_stock_alert' => $item->min_stock_alert,
                ],
                'transaction' => $tx,
            ]);
        });
    }

    /**
     * Get stock movement audit trail.
     */
    public function transactions(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $type = $request->query('type'); // 'book', 'dress'
        $itemId = $request->query('item_id');
        $txType = $request->query('transaction_type');
        $limit = (int)$request->query('limit', 50);

        $query = InventoryTransaction::where('school_id', $schoolId)
            ->with(['student:id,admission_number,name,class_name,section']);

        if ($type) {
            $query->where('item_type', $type);
        }
        if ($itemId) {
            $query->where('item_id', $itemId);
        }
        if ($txType) {
            $query->where('transaction_type', $txType);
        }

        $transactions = $query->orderBy('id', 'desc')->limit($limit)->get();

        $books = BookSet::where('school_id', $schoolId)->get()->keyBy('id');
        $dresses = DressItem::where('school_id', $schoolId)->get()->keyBy('id');

        $result = $transactions->map(function ($tx) use ($books, $dresses) {
            $arr = $tx->toArray();
            if ($tx->item_type === 'book') {
                $b = $books->get($tx->item_id);
                $arr['item_name'] = $b ? "{$b->name} ({$b->class_name})" : "Book Set #{$tx->item_id}";
            } else {
                $d = $dresses->get($tx->item_id);
                $arr['item_name'] = $d ? "{$d->name} [{$d->size}]" : "Dress Item #{$tx->item_id}";
            }
            return $arr;
        });

        return response()->json($result);
    }
}
