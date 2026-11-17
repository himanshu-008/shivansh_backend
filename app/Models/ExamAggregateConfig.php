<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ExamAggregateConfig extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id',
        'class_name',
        'name',
        'included_term_ids',
        'term_weightages',
        'target_total_marks',
    ];

    protected $casts = [
        'included_term_ids' => 'array',
        'term_weightages' => 'array',
    ];
}
