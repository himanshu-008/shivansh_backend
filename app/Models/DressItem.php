<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DressItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
    ];

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id')->where('item_type', 'dress');
    }
}
