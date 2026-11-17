<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'item_type',
        'item_id',
        'transaction_type',
        'quantity',
        'balance_after',
        'student_id',
        'reference_no',
        'unit_cost',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function bookSet()
    {
        return $this->belongsTo(BookSet::class, 'item_id');
    }

    public function dressItem()
    {
        return $this->belongsTo(DressItem::class, 'item_id');
    }
}
