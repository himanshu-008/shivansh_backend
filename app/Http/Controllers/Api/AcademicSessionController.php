<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\AcademicSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $sessions = AcademicSession::where('school_id', $user->school_id)
            ->orderBy('start_date', 'desc')
            ->get();

        if ($sessions->isEmpty()) {
            // Auto-create default session if none exists
            $defaultSession = AcademicSession::create([
                'school_id' => $user->school_id,
                'name' => '2026-2027',
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_current' => true,
            ]);
            $sessions->push($defaultSession);
        }

        return response()->json($sessions);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_current' => 'boolean',
        ]);

        $isCurrent = $request->boolean('is_current', false);
        if ($isCurrent) {
            AcademicSession::where('school_id', $user->school_id)->update(['is_current' => false]);
        }

        $session = AcademicSession::create([
            'school_id' => $user->school_id,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_current' => $isCurrent,
        ]);

        return response()->json([
            'message' => 'Academic session created successfully.',
            'session' => $session,
        ], 201);
    }

    public function setCurrent($id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $session = AcademicSession::where('school_id', $user->school_id)->findOrFail($id);

        AcademicSession::where('school_id', $user->school_id)->update(['is_current' => false]);
        $session->update(['is_current' => true]);

        // Commit promotions targeting this newly active session to the master students table
        $promotions = DB::table('student_promotions')
            ->where('school_id', $user->school_id)
            ->where('to_session_id', $id)
            ->get();

        foreach ($promotions as $p) {
            $updateData = [
                'academic_session_id' => $id,
                'updated_at' => now(),
            ];
            if ($p->action === 'promote' || $p->action === 'retain') {
                $updateData['class_name'] = $p->to_class;
                $updateData['section'] = $p->to_section;
                $updateData['status'] = 'active';
            } elseif ($p->action === 'passed_out') {
                $updateData['status'] = 'alumni';
            } elseif ($p->action === 'tc_issued') {
                $updateData['status'] = 'tc_issued';
            }
            DB::table('students')->where('id', $p->student_id)->update($updateData);
        }

        // If switching back to a previous session that was recorded as from_session_id, restore
        $previousPromotions = DB::table('student_promotions')
            ->where('school_id', $user->school_id)
            ->where('from_session_id', $id)
            ->get();

        foreach ($previousPromotions as $p) {
            DB::table('students')->where('id', $p->student_id)->update([
                'academic_session_id' => $id,
                'class_name' => $p->from_class,
                'section' => $p->from_section,
                'status' => 'active',
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => "Academic session '{$session->name}' is now active.",
            'session' => $session,
        ]);
    }
}
