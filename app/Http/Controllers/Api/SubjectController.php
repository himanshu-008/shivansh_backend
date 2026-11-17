<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Subject;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $className = $request->query('class_name');

        $query = Subject::where('school_id', $schoolId);
        if ($className && $className !== 'All Classes') {
            $query->where(function($q) use ($className) {
                $q->where('class_name', $className)->orWhere('class_name', 'All Classes');
            });
        }
        $subjects = $query->orderBy('class_name')->orderBy('name')->get();

        // Auto-seed default subjects if empty
        if ($subjects->isEmpty() && (!$className || $className === 'All Classes')) {
            $defaults = [
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Mathematics', 'code' => 'MATH', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'English Language & Lit.', 'code' => 'ENG', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Science & EVS', 'code' => 'SCI', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Social Studies / SST', 'code' => 'SST', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Hindi', 'code' => 'HIN', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'class_name' => 'All Classes', 'name' => 'Computer Science & IT', 'code' => 'CS', 'created_at' => now(), 'updated_at' => now()],
            ];
            Subject::insert($defaults);
            $subjects = Subject::where('school_id', $schoolId)->orderBy('class_name')->orderBy('name')->get();
        }

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $validated = $request->validate([
            'class_name' => 'required',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $classes = is_array($request->class_name) ? $request->class_name : [$request->class_name];
        $created = [];

        foreach ($classes as $cls) {
            $created[] = Subject::create([
                'school_id' => $schoolId,
                'class_name' => $cls,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
            ]);
        }

        return response()->json(count($created) === 1 ? $created[0] : $created, 201);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $subject = Subject::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $subject->delete();

        return response()->json(['message' => 'Subject deleted successfully']);
    }
}
