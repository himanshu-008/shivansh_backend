<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $request->header('X-School-ID') ?? $request->query('school_id');

        if (empty($schoolId)) {
            return response()->json(['message' => 'Unauthorized. Missing Tenant ID.'], 401);
        }

        // Optional: Validate if the school exists
        if (!\App\Models\School::where('id', $schoolId)->exists()) {
            return response()->json(['message' => 'Unauthorized. Invalid Tenant ID.'], 401);
        }

        $request->headers->set('X-School-ID', $schoolId);

        return $next($request);
    }
}
