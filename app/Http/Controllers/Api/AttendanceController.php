<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $date = $request->query('date') ?? date('Y-m-d');
        $className = $request->query('class_name');
        $section = $request->query('section');

        // Fetch target students (only active enrolled students)
        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        if ($className && $className !== 'All Classes') {
            $query->where('class_name', $className);
        }
        if ($section) {
            $query->where('section', $section);
        }

        $students = $query->orderBy('class_name')->orderBy('name')->get();

        // Fetch existing attendance records for date
        $existingRecords = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->where('date', $date)
            ->get()
            ->keyBy('student_id');

        $result = $students->map(function ($s) use ($existingRecords) {
            $att = $existingRecords[$s->id] ?? null;
            return [
                'student_id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->class_name,
                'section' => $s->section,
                'admission_number' => $s->admission_number,
                'father_name' => $s->father_name,
                'contact_number' => $s->contact_number,
                'status' => $att ? $att->status : 'present',
                'remarks' => $att ? $att->remarks : null,
                'marked' => $att !== null,
            ];
        });

        return response()->json([
            'date' => $date,
            'total_students' => $result->count(),
            'present_count' => $result->where('status', 'present')->count(),
            'absent_count' => $result->where('status', 'absent')->count(),
            'late_count' => $result->where('status', 'late')->count(),
            'half_day_count' => $result->where('status', 'half_day')->count(),
            'leave_count' => $result->where('status', 'leave')->count(),
            'students' => $result,
        ]);
    }

    public function bulkStore(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.status' => 'required|string|in:present,absent,late,half_day,leave',
            'records.*.remarks' => 'nullable|string',
        ]);

        $sessionId = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

        $date = $request->date;
        $studentIds = array_column($request->records, 'student_id');

        $students = DB::table('students')
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $now = now();
        $upsertRows = [];
        foreach ($request->records as $rec) {
            $stId = $rec['student_id'];
            if (isset($students[$stId])) {
                $st = $students[$stId];
                $upsertRows[] = [
                    'school_id' => $schoolId,
                    'student_id' => $stId,
                    'academic_session_id' => $sessionId,
                    'class_name' => $st->class_name,
                    'section' => $st->section,
                    'date' => $date,
                    'status' => $rec['status'],
                    'remarks' => $rec['remarks'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($upsertRows)) {
            DB::table('attendances')->upsert(
                $upsertRows,
                ['school_id', 'student_id', 'date'],
                ['academic_session_id', 'class_name', 'section', 'status', 'remarks', 'updated_at']
            );
        }

        $savedCount = count($upsertRows);
        return response()->json([
            'message' => "Attendance saved successfully for $savedCount student(s) on $date.",
        ]);
    }

    public function summary(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $className = $request->query('class_name');

        $query = DB::table('attendances')
            ->where('school_id', $schoolId);

        if ($className && $className !== 'All Classes') {
            $query->where('class_name', $className);
        }

        $stats = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'present' => $stats['present'] ?? 0,
            'absent' => $stats['absent'] ?? 0,
            'late' => $stats['late'] ?? 0,
            'half_day' => $stats['half_day'] ?? 0,
            'leave' => $stats['leave'] ?? 0,
        ]);
    }

    public function monthlyIndex(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $className = $request->query('class_name');
        $section = $request->query('section');
        $year = (int)($request->query('year') ?? date('Y'));
        $month = (int)($request->query('month') ?? date('n'));

        // Target students (only active enrolled students)
        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        if ($className && $className !== 'All Classes') {
            $query->where('class_name', $className);
        }
        if ($section) {
            $query->where('section', $section);
        }

        $students = $query->orderBy('class_name')->orderBy('name')->get();
        $studentIds = $students->pluck('id')->toArray();

        // Existing monthly records
        $monthlyRecords = DB::table('monthly_attendances')
            ->where('school_id', $schoolId)
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        // Pre-fill hints from daily records for this month
        $dailyRecords = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $dailyAttendedByStudent = [];
        $dailyAbsentByStudent = [];
        foreach ($dailyRecords as $dr) {
            if (in_array($dr->status, ['present', 'late', 'half_day'])) {
                $dailyAttendedByStudent[$dr->student_id] = ($dailyAttendedByStudent[$dr->student_id] ?? 0) + 1;
            } elseif ($dr->status === 'absent') {
                $dailyAbsentByStudent[$dr->student_id] = ($dailyAbsentByStudent[$dr->student_id] ?? 0) + 1;
            }
        }

        $dailyWorkingDays = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($className) {
                if ($className && $className !== 'All Classes') {
                    $q->where('class_name', $className);
                }
            })
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->distinct('date')
            ->count('date');

        $defaultWorkingDays = 24;
        if ($monthlyRecords->isNotEmpty()) {
            $firstWorking = $monthlyRecords->first()->total_working_days;
            if ($firstWorking > 0) {
                $defaultWorkingDays = $firstWorking;
            }
        } elseif ($dailyWorkingDays > 0) {
            $defaultWorkingDays = $dailyWorkingDays;
        }

        $monthDateObj = \DateTime::createFromFormat('!m', $month);
        $monthName = $monthDateObj ? $monthDateObj->format('F') : "Month $month";

        $result = $students->map(function ($s) use ($monthlyRecords, $dailyAttendedByStudent, $dailyAbsentByStudent, $defaultWorkingDays) {
            $mRec = $monthlyRecords[$s->id] ?? null;
            $marked = ($mRec !== null);

            $workingDays = $marked ? (int)$mRec->total_working_days : (int)$defaultWorkingDays;
            $attended = $marked ? (int)$mRec->attended_days : (int)($dailyAttendedByStudent[$s->id] ?? $workingDays);
            $absent = $marked ? (int)$mRec->absent_days : (int)($dailyAbsentByStudent[$s->id] ?? max(0, $workingDays - $attended));
            $halfDays = $marked ? (int)$mRec->half_days : 0;
            $leaveDays = $marked ? (int)$mRec->leave_days : 0;
            $remarks = $marked ? $mRec->remarks : null;

            $pct = $workingDays > 0 ? round(($attended / $workingDays) * 100, 1) : 0.0;

            return [
                'student_id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->class_name,
                'section' => $s->section,
                'admission_number' => $s->admission_number,
                'father_name' => $s->father_name,
                'contact_number' => $s->contact_number,
                'total_working_days' => $workingDays,
                'attended_days' => $attended,
                'absent_days' => $absent,
                'half_days' => $halfDays,
                'leave_days' => $leaveDays,
                'attendance_percentage' => $pct,
                'remarks' => $remarks,
                'marked' => $marked,
            ];
        });

        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_name' => "$monthName $year",
            'class_name' => $className ?? 'All Classes',
            'default_working_days' => $defaultWorkingDays,
            'total_students' => $result->count(),
            'students' => $result,
        ]);
    }

    public function monthlyBulkStore(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'records' => 'required|array',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.total_working_days' => 'required|integer|min:0',
            'records.*.attended_days' => 'required|integer|min:0',
            'records.*.absent_days' => 'nullable|integer|min:0',
            'records.*.half_days' => 'nullable|integer|min:0',
            'records.*.leave_days' => 'nullable|integer|min:0',
            'records.*.remarks' => 'nullable|string',
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;

        $sessionId = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

        $studentIds = array_column($request->records, 'student_id');
        $students = DB::table('students')
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $now = now();
        $upsertRows = [];
        foreach ($request->records as $rec) {
            $stId = $rec['student_id'];
            if (isset($students[$stId])) {
                $st = $students[$stId];
                $totalWorking = (int)$rec['total_working_days'];
                $attended = (int)$rec['attended_days'];

                if ($attended > $totalWorking) {
                    return response()->json([
                        'message' => "Validation Error: Attended days ($attended) cannot be more than working days ($totalWorking) for student '{$st->name}'."
                    ], 422);
                }

                $absent = isset($rec['absent_days']) ? (int)$rec['absent_days'] : max(0, $totalWorking - $attended);

                $upsertRows[] = [
                    'school_id' => $schoolId,
                    'student_id' => $stId,
                    'academic_session_id' => $sessionId,
                    'class_name' => $st->class_name,
                    'section' => $st->section,
                    'year' => $year,
                    'month' => $month,
                    'total_working_days' => $totalWorking,
                    'attended_days' => $attended,
                    'absent_days' => $absent,
                    'half_days' => (int)($rec['half_days'] ?? 0),
                    'leave_days' => (int)($rec['leave_days'] ?? 0),
                    'remarks' => $rec['remarks'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($upsertRows)) {
            DB::table('monthly_attendances')->upsert(
                $upsertRows,
                ['school_id', 'student_id', 'year', 'month'],
                [
                    'academic_session_id',
                    'class_name',
                    'section',
                    'total_working_days',
                    'attended_days',
                    'absent_days',
                    'half_days',
                    'leave_days',
                    'remarks',
                    'updated_at',
                ]
            );
        }

        $savedCount = count($upsertRows);
        $monthDateObj = \DateTime::createFromFormat('!m', $month);
        $monthName = $monthDateObj ? $monthDateObj->format('F') : "Month $month";

        return response()->json([
            'message' => "Monthly attendance saved successfully for $savedCount student(s) for $monthName $year.",
        ]);
    }
}
