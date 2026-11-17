<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LookupController extends Controller
{
    public function index()
    {
        $lookups = DB::table('system_lookups')->get();
        return response()->json($lookups);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin only.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|string',
            'value' => 'required|string',
        ]);

        $exists = DB::table('system_lookups')
            ->where('type', $validated['type'])
            ->where('value', $validated['value'])
            ->exists();
            
        if ($exists) {
            return response()->json(['message' => 'Lookup already exists.'], 400);
        }

        $id = DB::table('system_lookups')->insertGetId([
            'type' => $validated['type'],
            'value' => $validated['value'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lookup = DB::table('system_lookups')->where('id', $id)->first();
        return response()->json($lookup, 201);
    }
}
