<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // 1. Resolve from session
        if (session()->has('school_id')) {
            $builder->where($model->getTable() . '.school_id', session('school_id'));
            return;
        }

        // 2. Resolve from stateless HTTP request header
        if (request()->hasHeader('X-School-ID')) {
            $builder->where($model->getTable() . '.school_id', request()->header('X-School-ID'));
            return;
        }

        // 3. Resolve from authenticated user context
        if (auth()->check() && auth()->user()->school_id) {
            $builder->where($model->getTable() . '.school_id', auth()->user()->school_id);
            return;
        }

        // 4. Fallback: Prevent cross-tenant leaks when no tenant is resolved
        if (!app()->runningInConsole()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
