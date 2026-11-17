<?php

namespace App\Traits;

use App\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (session()->has('school_id')) {
                $model->school_id = session('school_id');
            } elseif (request()->hasHeader('X-School-ID')) {
                $model->school_id = request()->header('X-School-ID');
            } elseif (auth()->check() && auth()->user()->school_id) {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }
}
