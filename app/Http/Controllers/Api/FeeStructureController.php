<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\FeeStructure;
use Illuminate\Support\Facades\Auth;

class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $sessionId = $request->query('session_id');

        $query = FeeStructure::with(['feeCategory', 'academicSession'])->where('school_id', $schoolId);
        
        if ($sessionId && $sessionId !== 'all') {
            $query->where('academic_session_id', $sessionId);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'fee_category_id' => 'required|exists:fee_categories,id',
            'class_name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,quarterly,yearly,one_time',
        ]);

        $structure = FeeStructure::create(array_merge($request->all(), ['school_id' => Auth::user()->school_id]));
        $structure->load('feeCategory');

        return response()->json($structure, 201);
    }

    public function destroy($id)
    {
        $structure = FeeStructure::where('school_id', Auth::user()->school_id)->findOrFail($id);
        $structure->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
