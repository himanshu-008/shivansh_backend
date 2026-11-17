<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ExamTerm;
use App\Models\ExamTermSubject;

class ExamController extends Controller
{
    public function indexTerms(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $terms = ExamTerm::where('school_id', $schoolId)->get();

        // Auto-seed default exam terms if empty
        if ($terms->isEmpty()) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

            ExamTerm::insert([
                ['school_id' => $schoolId, 'academic_session_id' => $sessionId, 'name' => 'Unit Test 1 (UT-1)', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'academic_session_id' => $sessionId, 'name' => 'Half Yearly Examination', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'academic_session_id' => $sessionId, 'name' => 'Unit Test 2 (UT-2)', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'academic_session_id' => $sessionId, 'name' => 'Annual Examination', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $terms = ExamTerm::where('school_id', $schoolId)->get();
        }

        return response()->json($terms);
    }

    public function storeTerm(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $sessionId = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');

        $term = ExamTerm::create([
            'school_id' => $schoolId,
            'academic_session_id' => $sessionId,
            'name' => $request->name,
        ]);

        return response()->json($term, 201);
    }

    public function destroyTerm($id)
    {
        $schoolId = Auth::user()->school_id;
        $term = ExamTerm::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $term->delete();

        return response()->json(['message' => 'Exam term deleted successfully']);
    }

    /**
     * Get or list max marks configured for subjects in a specific exam term
     */
    public function indexTermSubjects(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $examTermId = $request->query('exam_term_id');
        $className = $request->query('class_name');

        if (!$examTermId) {
            return response()->json(['message' => 'exam_term_id is required'], 400);
        }

        $subjectsQuery = DB::table('subjects')->where('school_id', $schoolId);
        if ($className && $className !== 'All Classes') {
            $subjectsQuery->where(function($q) use ($className) {
                $q->where('class_name', $className)->orWhere('class_name', 'All Classes');
            });
        }
        $subjects = $subjectsQuery->get();

        $configured = DB::table('exam_term_subjects')
            ->where('school_id', $schoolId)
            ->where('exam_term_id', $examTermId)
            ->pluck('max_marks', 'subject_id')
            ->toArray();

        $result = [];
        foreach ($subjects as $sub) {
            $maxMarks = isset($configured[$sub->id]) ? (float)$configured[$sub->id] : 100.0;
            $result[] = [
                'subject_id' => $sub->id,
                'subject_name' => $sub->name,
                'class_name' => $sub->class_name,
                'max_marks' => $maxMarks,
            ];
        }

        return response()->json($result);
    }

    /**
     * Configure subject max marks for an exam term
     */
    public function storeTermSubjects(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'exam_term_id' => 'required|exists:exam_terms,id',
            'subjects_config' => 'required|array',
            'subjects_config.*.subject_id' => 'required|exists:subjects,id',
            'subjects_config.*.max_marks' => 'required|numeric|min:1',
        ]);

        $examTermId = $request->exam_term_id;
        $now = now();
        $upsertRows = [];

        foreach ($request->subjects_config as $item) {
            $upsertRows[] = [
                'school_id' => $schoolId,
                'exam_term_id' => $examTermId,
                'subject_id' => $item['subject_id'],
                'max_marks' => $item['max_marks'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Synchronize existing marks in exam_marks table for this term and subject
            DB::table('exam_marks')
                ->where('school_id', $schoolId)
                ->where('exam_term_id', $examTermId)
                ->where('subject_id', $item['subject_id'])
                ->update(['max_marks' => $item['max_marks'], 'updated_at' => $now]);
        }

        if (!empty($upsertRows)) {
            DB::table('exam_term_subjects')->upsert(
                $upsertRows,
                ['school_id', 'exam_term_id', 'subject_id'],
                ['max_marks', 'updated_at']
            );
        }

        return response()->json(['message' => 'Subject max marks configured successfully!']);
    }

    public function getMarks(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $examTermId = $request->query('exam_term_id');
        $className = $request->query('class_name');

        if (!$examTermId) {
            return response()->json(['message' => 'Exam term ID is required.'], 400);
        }

        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        if ($className && $className !== 'All Classes') {
            $query->where('class_name', $className);
        }
        $students = $query->orderBy('class_name')->orderBy('name')->get();

        $subjectsQuery = DB::table('subjects')->where('school_id', $schoolId);
        if ($className && $className !== 'All Classes') {
            $subjectsQuery->where(function($q) use ($className) {
                $q->where('class_name', $className)->orWhere('class_name', 'All Classes');
            });
        }
        $subjects = $subjectsQuery->get();

        // Fetch configured max marks for this term
        $configuredMaxMarks = DB::table('exam_term_subjects')
            ->where('school_id', $schoolId)
            ->where('exam_term_id', $examTermId)
            ->pluck('max_marks', 'subject_id')
            ->toArray();

        $existingMarks = DB::table('exam_marks')
            ->where('school_id', $schoolId)
            ->where('exam_term_id', $examTermId)
            ->get();

        $marksMap = []; // [student_id][subject_id] = {marks_obtained, max_marks}
        foreach ($existingMarks as $m) {
            $marksMap[$m->student_id][$m->subject_id] = [
                'marks_obtained' => (float)$m->marks_obtained,
                'max_marks' => (float)$m->max_marks,
            ];
        }

        // Compute attendance statistics per student and overall school working days
        $studentIds = $students->pluck('id')->toArray();
        $attendanceStats = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->select(
                'student_id',
                DB::raw('count(*) as total_days'),
                DB::raw("SUM(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as attended_days")
            )
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $monthlyStats = DB::table('monthly_attendances')
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->select(
                'student_id',
                DB::raw('SUM(attended_days) as m_attended'),
                DB::raw('SUM(total_working_days) as m_working')
            )
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        // Total unique working days recorded for the school
        $schoolTotalWorkingDays = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->distinct('date')
            ->count('date');

        $reportData = [];
        foreach ($students as $s) {
            $stMarks = [];
            $totalObtained = 0;
            $totalMax = 0;
            $stClass = $s->className ?? $s->class_name;

            foreach ($subjects as $sub) {
                // Class subject isolation check
                if ($sub->class_name !== 'All Classes' && $sub->class_name !== $stClass) {
                    continue;
                }

                $defaultMax = isset($configuredMaxMarks[$sub->id]) ? (float)$configuredMaxMarks[$sub->id] : 100.0;
                $m = $marksMap[$s->id][$sub->id] ?? null;
                $obtained = $m ? $m['marks_obtained'] : 0.0;
                $max = $m ? $m['max_marks'] : $defaultMax;

                $totalObtained += $obtained;
                $totalMax += $max;

                $stMarks[$sub->id] = [
                    'subject_id' => $sub->id,
                    'subject_name' => $sub->name,
                    'marks_obtained' => $obtained,
                    'max_marks' => $max,
                ];
            }

            $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
            $grade = 'F';
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 80) $grade = 'A';
            elseif ($percentage >= 70) $grade = 'B+';
            elseif ($percentage >= 60) $grade = 'B';
            elseif ($percentage >= 50) $grade = 'C';
            elseif ($percentage >= 33) $grade = 'D';

            $attStat = $attendanceStats[$s->id] ?? null;
            $mStat = $monthlyStats[$s->id] ?? null;
            $dAttended = $attStat ? (int)$attStat->attended_days : 0;
            $dWorking = $attStat ? (int)$attStat->total_days : 0;
            $mAttended = $mStat ? (int)$mStat->m_attended : 0;
            $mWorking = $mStat ? (int)$mStat->m_working : 0;

            $attendedDays = max($dAttended, $mAttended);
            $workingDays = max($dWorking, $mWorking, $schoolTotalWorkingDays);
            if ($workingDays > 0 && $attendedDays > $workingDays) {
                $attendedDays = $workingDays;
            }

            $reportData[] = [
                'student_id' => $s->id,
                'name' => $s->name,
                'class_name' => $stClass,
                'section' => $s->section,
                'admission_number' => $s->admission_number,
                'father_name' => $s->father_name ?? null,
                'marks' => $stMarks,
                'total_obtained' => $totalObtained,
                'total_max' => $totalMax,
                'percentage' => $percentage,
                'grade' => $grade,
                'attended_days' => $attendedDays,
                'total_working_days' => $workingDays,
                'attendance_percentage' => $workingDays > 0 ? round(($attendedDays / $workingDays) * 100, 1) : 0.0,
            ];
        }

        // Attach configured default max marks to subjects list
        $subjectsConfiguredList = [];
        foreach ($subjects as $sub) {
            $subjectsConfiguredList[] = [
                'id' => $sub->id,
                'name' => $sub->name,
                'code' => $sub->code,
                'class_name' => $sub->class_name,
                'max_marks' => isset($configuredMaxMarks[$sub->id]) ? (float)$configuredMaxMarks[$sub->id] : 100.0,
            ];
        }

        return response()->json([
            'subjects' => $subjectsConfiguredList,
            'students' => $reportData,
        ]);
    }

    public function bulkStoreMarks(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'exam_term_id' => 'required|exists:exam_terms,id',
            'marks_data' => 'required|array',
            'marks_data.*.student_id' => 'required|exists:students,id',
            'marks_data.*.subject_id' => 'required|exists:subjects,id',
            'marks_data.*.marks_obtained' => 'required|numeric|min:0',
            'marks_data.*.max_marks' => 'required|numeric|min:1',
        ]);

        $examTermId = $request->exam_term_id;
        $now = now();
        $upsertRows = [];

        foreach ($request->marks_data as $item) {
            $obtained = (float)$item['marks_obtained'];
            $maxM = (float)$item['max_marks'];

            if ($obtained > $maxM) {
                return response()->json([
                    'message' => "Validation Error: Marks obtained ($obtained) cannot exceed Maximum Marks ($maxM).",
                ], 422);
            }

            $upsertRows[] = [
                'school_id' => $schoolId,
                'exam_term_id' => $examTermId,
                'student_id' => $item['student_id'],
                'subject_id' => $item['subject_id'],
                'marks_obtained' => $obtained,
                'max_marks' => $maxM,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upsertRows)) {
            DB::table('exam_marks')->upsert(
                $upsertRows,
                ['school_id', 'exam_term_id', 'student_id', 'subject_id'],
                ['marks_obtained', 'max_marks', 'updated_at']
            );
        }

        $savedCount = count($upsertRows);
        return response()->json([
            'message' => "Marks updated successfully for $savedCount record(s).",
        ]);
    }

    /**
     * Compute Aggregate Results across selected exam terms with strict class-subject scoping
     */
    public function getAggregateMarks(Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'exam_term_ids' => 'required|array|min:1',
            'exam_term_ids.*' => 'exists:exam_terms,id',
            'class_name' => 'nullable|string',
            'term_weightages' => 'nullable|array',
            'use_weightages' => 'nullable|boolean',
        ]);

        $examTermIds = $request->exam_term_ids;
        $className = $request->class_name;
        $termWeightages = $request->term_weightages ?? [];
        $useWeightages = (bool)($request->use_weightages ?? false);

        // Fetch Exam Terms
        $terms = DB::table('exam_terms')
            ->where('school_id', $schoolId)
            ->whereIn('id', $examTermIds)
            ->get();

        // Fetch Students
        $query = DB::table('students')->where('school_id', $schoolId);
        if ($className && $className !== 'All Classes') {
            $query->where('class_name', $className);
        }
        $students = $query->orderBy('class_name')->orderBy('name')->get();

        // Fetch Subjects
        $subjectsQuery = DB::table('subjects')->where('school_id', $schoolId);
        if ($className && $className !== 'All Classes') {
            $subjectsQuery->where(function($q) use ($className) {
                $q->where('class_name', $className)->orWhere('class_name', 'All Classes');
            });
        }
        $subjects = $subjectsQuery->get();

        // Fetch Configured Max Marks across selected terms
        $configuredTermMaxMarks = DB::table('exam_term_subjects')
            ->where('school_id', $schoolId)
            ->whereIn('exam_term_id', $examTermIds)
            ->get();
        $termMaxMap = []; // [exam_term_id][subject_id] = max_marks
        foreach ($configuredTermMaxMarks as $cm) {
            $termMaxMap[$cm->exam_term_id][$cm->subject_id] = (float)$cm->max_marks;
        }

        // Fetch all marks for selected terms
        $allMarks = DB::table('exam_marks')
            ->where('school_id', $schoolId)
            ->whereIn('exam_term_id', $examTermIds)
            ->get();

        // Organize marks by [student_id][subject_id][exam_term_id] = {marks_obtained, max_marks}
        $marksMatrix = [];
        foreach ($allMarks as $m) {
            $marksMatrix[$m->student_id][$m->subject_id][$m->exam_term_id] = [
                'marks_obtained' => (float)$m->marks_obtained,
                'max_marks' => (float)$m->max_marks,
            ];
        }

        // Compute attendance statistics per student and overall school working days
        $studentIds = $students->pluck('id')->toArray();
        $attendanceStats = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->select(
                'student_id',
                DB::raw('count(*) as total_days'),
                DB::raw("SUM(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as attended_days")
            )
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $monthlyStats = DB::table('monthly_attendances')
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->select(
                'student_id',
                DB::raw('SUM(attended_days) as m_attended'),
                DB::raw('SUM(total_working_days) as m_working')
            )
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $schoolTotalWorkingDays = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->distinct('date')
            ->count('date');

        $studentAggregates = [];

        foreach ($students as $s) {
            $stClass = $s->className ?? $s->class_name;
            $subjectBreakdown = [];
            $totalAggregateObtained = 0.0;
            $totalAggregateMax = 0.0;

            foreach ($subjects as $sub) {
                // Class subject isolation check
                if ($sub->class_name !== 'All Classes' && $sub->class_name !== $stClass) {
                    continue;
                }

                $termBreakdown = [];
                $subRawObtainedSum = 0.0;
                $subRawMaxSum = 0.0;

                $subWeightedObtainedSum = 0.0;
                $subWeightedMaxSum = 0.0;

                foreach ($terms as $t) {
                    $defaultMax = $termMaxMap[$t->id][$sub->id] ?? 100.0;
                    $m = $marksMatrix[$s->id][$sub->id][$t->id] ?? null;

                    $obtained = $m ? $m['marks_obtained'] : 0.0;
                    $maxMarks = $m ? $m['max_marks'] : $defaultMax;

                    $subRawObtainedSum += $obtained;
                    $subRawMaxSum += $maxMarks;

                    $weight = isset($termWeightages[$t->id]) ? (float)$termWeightages[$t->id] : 0.0;
                    if ($useWeightages && $weight > 0 && $maxMarks > 0) {
                        $contribution = ($obtained / $maxMarks) * $weight;
                        $subWeightedObtainedSum += $contribution;
                        $subWeightedMaxSum += $weight;
                    }

                    $termBreakdown[$t->id] = [
                        'term_id' => $t->id,
                        'term_name' => $t->name,
                        'marks_obtained' => $obtained,
                        'max_marks' => $maxMarks,
                        'percentage' => $maxMarks > 0 ? round(($obtained / $maxMarks) * 100, 2) : 0,
                    ];
                }

                if ($useWeightages && $subWeightedMaxSum > 0) {
                    $subFinalObtained = round($subWeightedObtainedSum, 2);
                    $subFinalMax = round($subWeightedMaxSum, 2);
                } else {
                    $subFinalObtained = round($subRawObtainedSum, 2);
                    $subFinalMax = round($subRawMaxSum, 2);
                }

                $totalAggregateObtained += $subFinalObtained;
                $totalAggregateMax += $subFinalMax;

                $subPercentage = $subFinalMax > 0 ? round(($subFinalObtained / $subFinalMax) * 100, 2) : 0;

                $subjectBreakdown[$sub->id] = [
                    'subject_id' => $sub->id,
                    'subject_name' => $sub->name,
                    'term_breakdown' => $termBreakdown,
                    'aggregate_obtained' => $subFinalObtained,
                    'aggregate_max' => $subFinalMax,
                    'percentage' => $subPercentage,
                ];
            }

            $overallPercentage = $totalAggregateMax > 0 ? round(($totalAggregateObtained / $totalAggregateMax) * 100, 2) : 0;

            $grade = 'F';
            if ($overallPercentage >= 90) $grade = 'A+';
            elseif ($overallPercentage >= 80) $grade = 'A';
            elseif ($overallPercentage >= 70) $grade = 'B+';
            elseif ($overallPercentage >= 60) $grade = 'B';
            elseif ($overallPercentage >= 50) $grade = 'C';
            elseif ($overallPercentage >= 33) $grade = 'D';

            $attStat = $attendanceStats[$s->id] ?? null;
            $mStat = $monthlyStats[$s->id] ?? null;
            $dAttended = $attStat ? (int)$attStat->attended_days : 0;
            $dWorking = $attStat ? (int)$attStat->total_days : 0;
            $mAttended = $mStat ? (int)$mStat->m_attended : 0;
            $mWorking = $mStat ? (int)$mStat->m_working : 0;

            $attendedDays = max($dAttended, $mAttended);
            $workingDays = max($dWorking, $mWorking, $schoolTotalWorkingDays);
            if ($workingDays > 0 && $attendedDays > $workingDays) {
                $attendedDays = $workingDays;
            }

            $studentAggregates[] = [
                'student_id' => $s->id,
                'name' => $s->name,
                'class_name' => $stClass,
                'section' => $s->section,
                'admission_number' => $s->admission_number,
                'father_name' => $s->father_name ?? null,
                'subjects' => $subjectBreakdown,
                'total_obtained' => round($totalAggregateObtained, 2),
                'total_max' => round($totalAggregateMax, 2),
                'percentage' => $overallPercentage,
                'grade' => $grade,
                'attended_days' => $attendedDays,
                'total_working_days' => $workingDays,
                'attendance_percentage' => $workingDays > 0 ? round(($attendedDays / $workingDays) * 100, 1) : 0.0,
            ];
        }

        return response()->json([
            'terms' => $terms,
            'subjects' => $subjects,
            'students' => $studentAggregates,
            'use_weightages' => $useWeightages,
        ]);
    }
}
