<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\FeeCategory;
use Illuminate\Support\Facades\Auth;

class FeeCategoryController extends Controller
{
    public function index()
    {
        $schoolId = Auth::user()->school_id;
        $categories = FeeCategory::where('school_id', $schoolId)->get();
        
        $defaults = [
            ['name' => 'Tuition Fee', 'type' => 'recurring'],
            ['name' => 'Admission Fee', 'type' => 'one_time'],
            ['name' => 'Registration Fee', 'type' => 'one_time'],
            ['name' => 'Transport Fee', 'type' => 'recurring'],
            ['name' => 'Books & Stationery Fee', 'type' => 'one_time'],
            ['name' => 'Dress & Uniform Fee', 'type' => 'one_time'],
        ];

        foreach ($defaults as $def) {
            $exists = $categories->firstWhere('name', $def['name']);
            if (!$exists) {
                FeeCategory::create([
                    'school_id' => $schoolId,
                    'name' => $def['name'],
                    'type' => $def['type'],
                ]);
            }
        }

        return response()->json(FeeCategory::where('school_id', $schoolId)->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:recurring,one_time',
        ]);

        $category = FeeCategory::create([
            'school_id' => Auth::user()->school_id,
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
        ]);

        return response()->json($category, 201);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $category = FeeCategory::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $category->delete();

        return response()->json(['message' => 'Fee category deleted successfully']);
    }
}
