<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SchoolClassController extends Controller
{
    public function index()
    {
        $schoolId = Auth::user()->school_id;
        if (!$schoolId) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $classes = SchoolClass::where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get();

        if ($classes->isEmpty()) {
            // Auto-seed existing student classes + standard classes
            $existingStudentClasses = DB::table('students')
                ->where('school_id', $schoolId)
                ->whereNotNull('class_name')
                ->pluck('class_name')
                ->map(fn($c) => trim($c))
                ->filter(fn($c) => !empty($c))
                ->unique()
                ->values()
                ->toArray();

            $defaults = [
                'Nursery', 'LKG', 'UKG',
                '1st', '2nd', '3rd', '4th', '5th',
                '6th', '7th', '8th', '9th', '10th', '11th', '12th'
            ];

            $allClasses = array_unique(array_merge($existingStudentClasses, $defaults));

            $insertData = [];
            $order = 1;
            foreach ($allClasses as $cName) {
                $insertData[] = [
                    'school_id' => $schoolId,
                    'name' => $cName,
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            SchoolClass::insert($insertData);
            $classes = SchoolClass::where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get();
        }

        return response()->json($classes);
    }

    public function store(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $class = SchoolClass::firstOrCreate(
            ['school_id' => $schoolId, 'name' => trim($validated['name'])],
            ['sort_order' => $validated['sort_order'] ?? 99]
        );

        return response()->json($class, 201);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $class = SchoolClass::where('school_id', $schoolId)->findOrFail($id);
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }
}
