<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $students = $this->getStudentsForSession($request, $user->school_id);
        return response()->json($students);
    }

    public function getStudentsForSession(Request $request, $schoolId)
    {
        $currentSession = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->first();

        $currentSessionId = $currentSession ? (int)$currentSession->id : (
            (int)(DB::table('academic_sessions')->where('school_id', $schoolId)->orderBy('id', 'asc')->value('id') ?? 0)
        );

        $requestedSessionId = ($request->has('session_id') && !empty($request->session_id))
            ? (int)$request->session_id
            : $currentSessionId;

        // If querying the current active session (or no specific session)
        if (!$requestedSessionId || $requestedSessionId === $currentSessionId) {
            $query = DB::table('students')
                ->where('school_id', $schoolId);

            if ($currentSessionId) {
                $query->where(function ($q) use ($currentSessionId) {
                    $q->where('academic_session_id', $currentSessionId)
                      ->orWhereNull('academic_session_id');
                });
            }

            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where(function($q) {
                        $q->where('status', 'active')->orWhereNull('status');
                    });
                } elseif ($request->status !== 'all') {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has('class_name') && !empty($request->class_name) && $request->class_name !== 'All Classes') {
                $query->where('class_name', $request->class_name);
            }

            if ($request->has('is_rte') && $request->is_rte !== '' && $request->is_rte !== null && $request->is_rte !== 'all') {
                $isRteVal = filter_var($request->is_rte, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $query->where('is_rte', $isRteVal);
            }

            return $query
                ->orderBy('class_name')
                ->orderByRaw('CAST(roll_number AS UNSIGNED) ASC')
                ->orderBy('name')
                ->get();
        }

        // If querying a future or different target session:
        // 1. Fetch students promoted to this target session
        $promotedStudents = DB::table('students')
            ->join('student_promotions', 'students.id', '=', 'student_promotions.student_id')
            ->where('student_promotions.school_id', $schoolId)
            ->where('student_promotions.to_session_id', $requestedSessionId)
            ->select(
                'students.*',
                'student_promotions.to_class as session_class_name',
                'student_promotions.to_section as session_section',
                'student_promotions.action as promotion_action'
            )
            ->get();

        foreach ($promotedStudents as $st) {
            $st->class_name = $st->session_class_name;
            $st->section = $st->session_section;
            $st->academic_session_id = $requestedSessionId;
            if ($st->promotion_action === 'passed_out') {
                $st->status = 'alumni';
            } elseif ($st->promotion_action === 'tc_issued') {
                $st->status = 'tc_issued';
            } else {
                $st->status = 'active';
            }
            unset($st->session_class_name, $st->session_section, $st->promotion_action);
        }

        // 2. Fetch students directly admitted into this session
        $directStudents = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $requestedSessionId)
            ->whereNotIn('id', $promotedStudents->pluck('id'))
            ->get();

        $allStudents = $promotedStudents->merge($directStudents);

        // 3. Fallback: Check if this was a past session where students were promoted from
        if ($allStudents->isEmpty()) {
            $pastStudents = DB::table('students')
                ->join('student_promotions', 'students.id', '=', 'student_promotions.student_id')
                ->where('student_promotions.school_id', $schoolId)
                ->where('student_promotions.from_session_id', $requestedSessionId)
                ->select(
                    'students.*',
                    'student_promotions.from_class as session_class_name',
                    'student_promotions.from_section as session_section'
                )
                ->get();

            foreach ($pastStudents as $st) {
                $st->class_name = $st->session_class_name;
                $st->section = $st->session_section;
                $st->academic_session_id = $requestedSessionId;
                $st->status = 'active';
                unset($st->session_class_name, $st->session_section);
            }
            $allStudents = $pastStudents;
        }

        // Apply filters in collection
        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'active') {
                $allStudents = $allStudents->filter(fn($s) => ($s->status ?? 'active') === 'active');
            } elseif ($request->status !== 'all') {
                $allStudents = $allStudents->filter(fn($s) => $s->status === $request->status);
            }
        }

        if ($request->has('class_name') && !empty($request->class_name) && $request->class_name !== 'All Classes') {
            $targetClass = $request->class_name;
            $allStudents = $allStudents->filter(fn($s) => $s->class_name === $targetClass);
        }

        if ($request->has('is_rte') && $request->is_rte !== '' && $request->is_rte !== null && $request->is_rte !== 'all') {
            $isRteVal = filter_var($request->is_rte, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $allStudents = $allStudents->filter(fn($s) => ((int)($s->is_rte ?? 0)) === $isRteVal);
        }

        return $allStudents
            ->sortBy([
                ['class_name', 'asc'],
                ['roll_number', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    public static function normalizeClassName($rawClass)
    {
        if (empty($rawClass)) return '';
        $clean = strtolower(trim($rawClass));

        $map = [
            'pp.3+' => 'Nursery',
            'nursery/kg/pp3' => 'Nursery',
            'nursery' => 'Nursery',
            'nur' => 'Nursery',
            'pp.4+' => 'LKG',
            'lkg' => 'LKG',
            'lower kg' => 'LKG',
            'pp.5+' => 'UKG',
            'ukg/kg2/pp1' => 'UKG',
            'ukg' => 'UKG',
            'upper kg' => 'UKG',
            'first' => '1st',
            'i' => '1st',
            '1' => '1st',
            '1st' => '1st',
            'class 1' => '1st',
            'class i' => '1st',
            'second' => '2nd',
            'ii' => '2nd',
            '2' => '2nd',
            '2nd' => '2nd',
            'class 2' => '2nd',
            'class ii' => '2nd',
            'third' => '3rd',
            'iii' => '3rd',
            '3' => '3rd',
            '3rd' => '3rd',
            'class 3' => '3rd',
            'class iii' => '3rd',
            'fourth' => '4th',
            'iv' => '4th',
            '4' => '4th',
            '4th' => '4th',
            'class 4' => '4th',
            'class iv' => '4th',
            'fifth' => '5th',
            'v' => '5th',
            '5' => '5th',
            '5th' => '5th',
            'class 5' => '5th',
            'class v' => '5th',
            'sixth' => '6th',
            'vi' => '6th',
            '6' => '6th',
            '6th' => '6th',
            'class 6' => '6th',
            'class vi' => '6th',
            'seventh' => '7th',
            'vii' => '7th',
            '7' => '7th',
            '7th' => '7th',
            'class 7' => '7th',
            'class vii' => '7th',
            'eighth' => '8th',
            'viii' => '8th',
            '8' => '8th',
            '8th' => '8th',
            'class 8' => '8th',
            'class viii' => '8th',
            'ninth' => '9th',
            'ix' => '9th',
            '9' => '9th',
            '9th' => '9th',
            'class 9' => '9th',
            'class ix' => '9th',
            'tenth' => '10th',
            'x' => '10th',
            '10' => '10th',
            '10th' => '10th',
            'class 10' => '10th',
            'class x' => '10th',
            'eleventh' => '11th',
            'xi' => '11th',
            '11' => '11th',
            '11th' => '11th',
            'class 11' => '11th',
            'class xi' => '11th',
            'twelfth' => '12th',
            'xii' => '12th',
            '12' => '12th',
            '12th' => '12th',
            'class 12' => '12th',
            'class xii' => '12th',
        ];

        return $map[$clean] ?? trim($rawClass);
    }

    public static function normalizeSection($rawSection)
    {
        if (empty($rawSection)) return null;
        $clean = strtoupper(trim($rawSection));
        $clean = preg_replace('/^(SEC|SECTION)\s*/i', '', $clean);
        return empty($clean) ? null : $clean;
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'admission_number' => 'nullable|string',
            'roll_number' => 'nullable|string',
            'class_name' => 'required|string',
            'section' => 'nullable|string',
            'father_name' => 'nullable|string',
            'mother_name' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'category' => 'nullable|string',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
            'aadhar_number' => 'nullable|string',
            'email' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'student_pen' => 'nullable|string',
            'student_nic_id' => 'nullable|string',
            'apaar_id' => 'nullable|string',
            'bpl_beneficiary' => 'nullable|string',
            'is_rte' => 'nullable|boolean',
        ]);

        $currentSessionId = DB::table('academic_sessions')
            ->where('school_id', $user->school_id)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')
            ->where('school_id', $user->school_id)
            ->value('id');

        $studentId = DB::table('students')->insertGetId([
            'school_id' => $user->school_id,
            'academic_session_id' => $request->academic_session_id ?? $currentSessionId,
            'name' => $validated['name'],
            'admission_number' => $validated['admission_number'] ?? null,
            'roll_number' => $validated['roll_number'] ?? null,
            'class_name' => self::normalizeClassName($validated['class_name']),
            'section' => self::normalizeSection($validated['section'] ?? null),
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'category' => $validated['category'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'address' => $validated['address'] ?? null,
            'aadhar_number' => $validated['aadhar_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'student_pen' => $validated['student_pen'] ?? null,
            'student_nic_id' => $validated['student_nic_id'] ?? null,
            'apaar_id' => $validated['apaar_id'] ?? null,
            'bpl_beneficiary' => $validated['bpl_beneficiary'] ?? null,
            'is_rte' => $request->boolean('is_rte') ? 1 : 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = DB::table('students')->where('id', $studentId)->first();

        return response()->json($student, 201);
    }

    public function bulkImport(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'file' => 'required|file',
            'source' => 'required|string|in:rajpsp,udise'
        ]);

        $file = $request->file('file');
        $source = $request->input('source');
        $conflictMode = $request->input('conflict_mode', 'overwrite_all'); // 'overwrite_all' | 'update_new_only' | 'skip_existing'

        if ($source === 'udise') {
            try {
                $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\DataImport, $file);
                if (empty($data) || empty($data[0])) {
                    return response()->json(['message' => 'Invalid UDISE+ Excel/CSV format.'], 400);
                }
                return $this->importUdiseArray($data[0], $user->school_id, $conflictMode);
            } catch (\Exception $e) {
                // Fallback to manual CSV parsing if Excel fails (e.g. for pure text CSV files)
                $content = file_get_contents($file->getRealPath());
                return $this->importUdiseCsv($content, $user->school_id, $conflictMode);
            }
        }

        $content = file_get_contents($file->getRealPath());
        return $this->importRajPspHtml($content, $user->school_id, $conflictMode);
    }

    private function importUdiseArray($rows, $schoolId, $conflictMode = 'overwrite_all')
    {
        $headers = [];
        $dataRows = [];

        foreach ($rows as $row) {
            if (empty(array_filter($row))) continue;
            
            // Check if this row is the header row
            if (empty($headers) && in_array('Name', $row) && in_array('Class', $row)) {
                $headers = $row;
            } elseif (!empty($headers)) {
                $dataRows[] = $row;
            }
        }

        if (empty($headers)) {
            return response()->json(['message' => 'Invalid UDISE+ Excel format. Could not find Name/Class headers.'], 400);
        }

        $nameIdx = array_search('Name', $headers);
        $classIdx = array_search('Class', $headers);
        $sectionIdx = array_search('Section', $headers);
        $genderIdx = array_search('Gender', $headers);
        $penIdx = array_search('Student PEN', $headers);
        $fatherIdx = array_search('Father Name', $headers);
        $motherIdx = array_search('Mother Name', $headers);
        $categoryIdx = array_search('Social Category', $headers);
        $bplIdx = array_search('BPL beneficiary', $headers);
        $aadharIdx = array_search('AADHAAR No.', $headers);
        $apaarIdx = array_search('APAAR ID', $headers);

        $rteIdx = false;
        foreach ($headers as $idx => $h) {
            $hLower = strtolower(trim((string)$h));
            if ($hLower === 'rte' || str_contains($hLower, 'is_rte') || str_contains($hLower, 'rte student') || str_contains($hLower, 'getting free education')) {
                $rteIdx = $idx;
                break;
            }
        }

        $processedCount = 0;
        $skippedCount = 0;
        $now = now();

        foreach ($dataRows as $cols) {
            $name = isset($cols[$nameIdx]) ? trim($cols[$nameIdx]) : null;
            $className = isset($cols[$classIdx]) ? self::normalizeClassName($cols[$classIdx]) : null;
            
            if (empty($name) || empty($className)) continue;

            $section = $sectionIdx !== false && isset($cols[$sectionIdx]) ? self::normalizeSection($cols[$sectionIdx]) : null;
            $gender = $genderIdx !== false && isset($cols[$genderIdx]) ? trim($cols[$genderIdx]) : null;
            $pen = $penIdx !== false && isset($cols[$penIdx]) && trim($cols[$penIdx]) !== 'NA' ? trim($cols[$penIdx]) : null;
            $fatherName = $fatherIdx !== false && isset($cols[$fatherIdx]) && trim($cols[$fatherIdx]) !== 'NA' ? trim($cols[$fatherIdx]) : null;
            $motherName = $motherIdx !== false && isset($cols[$motherIdx]) && trim($cols[$motherIdx]) !== 'NA' ? trim($cols[$motherIdx]) : null;
            $category = $categoryIdx !== false && isset($cols[$categoryIdx]) && trim($cols[$categoryIdx]) !== 'NA' ? trim($cols[$categoryIdx]) : null;
            
            if ($category && strpos($category, '-') !== false) {
                $category = explode('-', $category)[1];
            }

            $bpl = $bplIdx !== false && isset($cols[$bplIdx]) && trim($cols[$bplIdx]) !== 'NA' ? trim($cols[$bplIdx]) : null;
            $aadhar = $aadharIdx !== false && isset($cols[$aadharIdx]) && trim($cols[$aadharIdx]) !== 'NOT AVAILABLE' && !str_starts_with(trim($cols[$aadharIdx]), '***') ? trim($cols[$aadharIdx]) : null;
            $apaar = $apaarIdx !== false && isset($cols[$apaarIdx]) && trim($cols[$apaarIdx]) !== 'NA' ? trim($cols[$apaarIdx]) : null;

            $isRte = null;
            if ($rteIdx !== false && isset($cols[$rteIdx])) {
                $rVal = strtolower(trim((string)$cols[$rteIdx]));
                if (in_array($rVal, ['yes', '1', 'true', 'rte', 'हाँ'])) {
                    $isRte = 1;
                } elseif (in_array($rVal, ['no', '0', 'false', 'नहीं', 'not applicable', 'na'])) {
                    $isRte = 0;
                }
            }

            $updateAttributes = array_filter([
                'name' => $name,
                'class_name' => $className,
                'section' => $section,
                'gender' => $gender,
                'father_name' => $fatherName,
                'mother_name' => $motherName,
                'category' => $category,
                'bpl_beneficiary' => $bpl,
                'student_pen' => $pen,
                'apaar_id' => $apaar,
            ], fn($val) => !is_null($val) && $val !== '');

            if ($isRte !== null) {
                $updateAttributes['is_rte'] = $isRte;
            }
            
            $updateAttributes['status'] = 'active';
            $updateAttributes['updated_at'] = $now;

            if ($aadhar) {
                $updateAttributes['aadhar_number'] = $aadhar;
            }

            $existing = null;
            if (!empty($pen)) {
                $existing = DB::table('students')->where('school_id', $schoolId)->where('student_pen', $pen)->first();
            }
            if (!$existing && $name) {
                $query = DB::table('students')->where('school_id', $schoolId)->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);
                if ($fatherName) {
                    $query->whereRaw('LOWER(father_name) = ?', [strtolower(trim($fatherName))]);
                }
                $existing = $query->first();
            }

            if ($existing) {
                if ($conflictMode === 'skip_existing') {
                    $skippedCount++;
                    continue;
                }

                if ($conflictMode === 'update_new_only') {
                    $fillAttributes = [];
                    foreach ($updateAttributes as $key => $val) {
                        // Only update if existing record currently has this field empty or null
                        if (empty($existing->$key)) {
                            $fillAttributes[$key] = $val;
                        }
                    }
                    if (!empty($fillAttributes)) {
                        $fillAttributes['updated_at'] = $now;
                        DB::table('students')->where('id', $existing->id)->update($fillAttributes);
                    }
                    $processedCount++;
                    continue;
                }

                DB::table('students')->where('id', $existing->id)->update($updateAttributes);
            } else {
                $updateAttributes['school_id'] = $schoolId;
                $currentSessionId = DB::table('academic_sessions')
                    ->where('school_id', $schoolId)
                    ->where('is_current', 1)
                    ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
                if ($currentSessionId) {
                    $updateAttributes['academic_session_id'] = $currentSessionId;
                }
                DB::table('students')->insert($updateAttributes);
            }
            $processedCount++;
        }

        $msg = $processedCount . ' students successfully imported or updated from UDISE+.';
        if ($skippedCount > 0) {
            $msg .= " ($skippedCount skipped as existing).";
        }

        return response()->json([
            'message' => $msg,
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
        ], 200);
    }

    private function importUdiseCsv($content, $schoolId, $conflictMode = 'overwrite_all')
    {
        $lines = explode("\n", $content);
        $headers = [];
        $dataRows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'List of All Students')) {
                continue;
            }
            if (empty($headers)) {
                $headers = str_getcsv($line);
            } else {
                $dataRows[] = str_getcsv($line);
            }
        }

        if (empty($headers)) {
            return response()->json(['message' => 'Invalid UDISE+ CSV format.'], 400);
        }

        $nameIdx = array_search('Name', $headers);
        $classIdx = array_search('Class', $headers);
        $sectionIdx = array_search('Section', $headers);
        $genderIdx = array_search('Gender', $headers);
        $penIdx = array_search('Student PEN', $headers);
        $fatherIdx = array_search('Father Name', $headers);
        $motherIdx = array_search('Mother Name', $headers);
        $categoryIdx = array_search('Social Category', $headers);
        $bplIdx = array_search('BPL beneficiary', $headers);
        $aadharIdx = array_search('AADHAAR No.', $headers);
        $apaarIdx = array_search('APAAR ID', $headers);

        $rteIdx = false;
        foreach ($headers as $idx => $h) {
            $hLower = strtolower(trim((string)$h));
            if ($hLower === 'rte' || str_contains($hLower, 'is_rte') || str_contains($hLower, 'rte student') || str_contains($hLower, 'getting free education')) {
                $rteIdx = $idx;
                break;
            }
        }

        if ($nameIdx === false || $classIdx === false) {
            return response()->json(['message' => 'Required columns (Name, Class) missing.'], 400);
        }

        $processedCount = 0;
        $skippedCount = 0;
        $now = now();

        foreach ($dataRows as $cols) {
            if (count($cols) < count($headers)) continue;

            $name = trim($cols[$nameIdx]);
            $className = self::normalizeClassName($cols[$classIdx]);
            
            if (empty($name) || empty($className)) continue;

            $section = $sectionIdx !== false ? trim($cols[$sectionIdx]) : null;
            $gender = $genderIdx !== false ? trim($cols[$genderIdx]) : null;
            $pen = $penIdx !== false && trim($cols[$penIdx]) !== 'NA' ? trim($cols[$penIdx]) : null;
            $fatherName = $fatherIdx !== false && trim($cols[$fatherIdx]) !== 'NA' ? trim($cols[$fatherIdx]) : null;
            $motherName = $motherIdx !== false && trim($cols[$motherIdx]) !== 'NA' ? trim($cols[$motherIdx]) : null;
            $category = $categoryIdx !== false && trim($cols[$categoryIdx]) !== 'NA' ? trim($cols[$categoryIdx]) : null;
            
            if ($category && strpos($category, '-') !== false) {
                $category = explode('-', $category)[1];
            }

            $bpl = $bplIdx !== false && trim($cols[$bplIdx]) !== 'NA' ? trim($cols[$bplIdx]) : null;
            $aadhar = $aadharIdx !== false && trim($cols[$aadharIdx]) !== 'NOT AVAILABLE' && !str_starts_with(trim($cols[$aadharIdx]), '***') ? trim($cols[$aadharIdx]) : null;
            $apaar = $apaarIdx !== false && trim($cols[$apaarIdx]) !== 'NA' ? trim($cols[$apaarIdx]) : null;

            $isRte = null;
            if ($rteIdx !== false && isset($cols[$rteIdx])) {
                $rVal = strtolower(trim((string)$cols[$rteIdx]));
                if (in_array($rVal, ['yes', '1', 'true', 'rte', 'हाँ'])) {
                    $isRte = 1;
                } elseif (in_array($rVal, ['no', '0', 'false', 'नहीं', 'not applicable', 'na'])) {
                    $isRte = 0;
                }
            }

            $matchAttributes = [
                'school_id' => $schoolId,
            ];

            if (!empty($pen)) {
                $matchAttributes['student_pen'] = $pen;
            } else {
                $matchAttributes['name'] = $name;
                $matchAttributes['father_name'] = $fatherName;
            }

            $updateAttributes = array_filter([
                'name' => $name,
                'class_name' => $className,
                'section' => $section,
                'gender' => $gender,
                'father_name' => $fatherName,
                'mother_name' => $motherName,
                'category' => $category,
                'bpl_beneficiary' => $bpl,
                'student_pen' => $pen,
                'apaar_id' => $apaar,
            ], fn($val) => !is_null($val) && $val !== '');

            if ($isRte !== null) {
                $updateAttributes['is_rte'] = $isRte;
            }
            
            $updateAttributes['status'] = 'active';
            $updateAttributes['updated_at'] = $now;

            if ($aadhar) {
                $updateAttributes['aadhar_number'] = $aadhar;
            }

            $existing = DB::table('students')->where($matchAttributes)->first();
            if ($existing) {
                if ($conflictMode === 'skip_existing') {
                    $skippedCount++;
                    continue;
                }

                if ($conflictMode === 'update_new_only') {
                    $fillAttributes = [];
                    foreach ($updateAttributes as $key => $val) {
                        if (empty($existing->$key)) {
                            $fillAttributes[$key] = $val;
                        }
                    }
                    if (!empty($fillAttributes)) {
                        $fillAttributes['updated_at'] = $now;
                        DB::table('students')->where('id', $existing->id)->update($fillAttributes);
                    }
                    $processedCount++;
                    continue;
                }

                DB::table('students')->where('id', $existing->id)->update($updateAttributes);
            } else {
                $updateAttributes = array_merge($matchAttributes, $updateAttributes);
                $currentSessionId = DB::table('academic_sessions')
                    ->where('school_id', $schoolId)
                    ->where('is_current', 1)
                    ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
                if ($currentSessionId) {
                    $updateAttributes['academic_session_id'] = $currentSessionId;
                }
                DB::table('students')->insert($updateAttributes);
            }
            $processedCount++;
        }

        $msg = $processedCount . ' students successfully imported or updated from UDISE+.';
        if ($skippedCount > 0) {
            $msg .= " ($skippedCount skipped as existing).";
        }

        return response()->json([
            'message' => $msg,
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
        ], 200);
    }

    private function importRajPspHtml($content, $schoolId, $conflictMode = 'overwrite_all')
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        
        $tables = $dom->getElementsByTagName('table');
        if ($tables->length == 0) {
            return response()->json(['message' => 'Invalid file format. No table found.'], 400);
        }

        $rows = $tables->item(0)->getElementsByTagName('tr');
        if ($rows->length <= 1) {
            return response()->json(['message' => 'No student data found in the file.'], 400);
        }

        $headerRow = $rows->item(0)->getElementsByTagName('th');
        $headers = [];
        foreach ($headerRow as $th) {
            $headers[] = trim($th->nodeValue);
        }

        $nameIdx = array_search('Student Name', $headers);
        $nicIdx = array_search('Student NIC ID', $headers);
        if ($nicIdx === false) {
            foreach ($headers as $idx => $h) {
                if (stripos($h, 'NIC') !== false) {
                    $nicIdx = $idx;
                }
            }
        }
        $admIdx = array_search('Admission Number/SR No', $headers);
        $classIdx = array_search('Studying in Class', $headers);
        $fatherIdx = array_search('Father Name', $headers);
        $motherIdx = array_search('Mother Name', $headers);
        $dobIdx = array_search('DOB', $headers);
        $mobileIdx = array_search('Mobile Number', $headers);
        $genderIdx = array_search('Gender', $headers);
        $catIdx = array_search('Social Category', $headers);
        $relIdx = array_search('Religion', $headers);
        $addIdx = array_search('Habitation or Locality', $headers);
        $aadharIdx = array_search('Aadhar Number', $headers);
        $emailIdx = array_search('Email Address', $headers);

        $freeEduIdx = array_search('Getting Free Education', $headers);
        if ($freeEduIdx === false) {
            foreach ($headers as $idx => $h) {
                if (stripos($h, 'Free Education') !== false || stripos($h, 'RTE') !== false || stripos($h, 'निशुल्क') !== false) {
                    $freeEduIdx = $idx;
                    break;
                }
            }
        }

        if ($nameIdx === false || $classIdx === false) {
            return response()->json(['message' => 'Required columns (Student Name, Studying in Class) missing.'], 400);
        }

        $processedCount = 0;
        $skippedCount = 0;
        $now = now();

        for ($i = 1; $i < $rows->length; $i++) {
            $cols = $rows->item($i)->getElementsByTagName('td');
            if ($cols->length < count($headers)) continue;

            $name = trim($cols->item($nameIdx)->nodeValue);
            $className = self::normalizeClassName($cols->item($classIdx)->nodeValue);
            
            if (empty($name) || empty($className)) continue;

            $nicId = ($nicIdx !== false && $cols->item($nicIdx)) ? trim($cols->item($nicIdx)->nodeValue) : null;
            $admissionNo = $admIdx !== false ? trim($cols->item($admIdx)->nodeValue) : null;
            $fatherName = $fatherIdx !== false ? trim($cols->item($fatherIdx)->nodeValue) : null;
            $motherName = $motherIdx !== false ? trim($cols->item($motherIdx)->nodeValue) : null;
            $mobile = $mobileIdx !== false ? trim($cols->item($mobileIdx)->nodeValue) : null;
            $gender = $genderIdx !== false ? trim($cols->item($genderIdx)->nodeValue) : null;
            $category = $catIdx !== false ? trim($cols->item($catIdx)->nodeValue) : null;
            $religion = $relIdx !== false ? trim($cols->item($relIdx)->nodeValue) : null;
            $address = $addIdx !== false ? trim($cols->item($addIdx)->nodeValue) : null;
            $aadhar = $aadharIdx !== false ? trim($cols->item($aadharIdx)->nodeValue) : null;
            $email = $emailIdx !== false ? trim($cols->item($emailIdx)->nodeValue) : null;
            
            $isRte = false;
            if ($freeEduIdx !== false && $cols->item($freeEduIdx)) {
                $freeVal = strtolower(trim($cols->item($freeEduIdx)->nodeValue ?? ''));
                if ($freeVal === 'yes' || $freeVal === '1' || $freeVal === 'true' || $freeVal === 'हाँ') {
                    $isRte = true;
                }
            }

            $dobStr = $dobIdx !== false ? trim($cols->item($dobIdx)->nodeValue) : null;
            $dob = null;
            if ($dobStr) {
                $parts = explode('/', $dobStr);
                if (count($parts) == 3) {
                    $dob = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }

            $updateAttributes = array_filter([
                'name' => $name,
                'class_name' => $className,
                'student_nic_id' => $nicId,
                'father_name' => $fatherName,
                'mother_name' => $motherName,
                'dob' => $dob,
                'contact_number' => $mobile,
                'gender' => $gender,
                'category' => $category,
                'religion' => $religion,
                'address' => $address,
                'aadhar_number' => $aadhar,
                'email' => $email,
            ], fn($val) => !is_null($val) && $val !== '');
            
            $updateAttributes['is_rte'] = $isRte ? 1 : 0;
            $updateAttributes['status'] = 'active';
            $updateAttributes['updated_at'] = $now;

            $existing = null;
            if (!empty($nicId)) {
                $existing = DB::table('students')->where('school_id', $schoolId)->where('student_nic_id', $nicId)->first();
            }
            if (!$existing && !empty($admissionNo)) {
                $existing = DB::table('students')->where('school_id', $schoolId)->where('admission_number', $admissionNo)->first();
            }
            if (!$existing && $name) {
                $query = DB::table('students')->where('school_id', $schoolId)->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);
                if ($fatherName) {
                    $query->whereRaw('LOWER(father_name) = ?', [strtolower(trim($fatherName))]);
                }
                $existing = $query->first();
            }

            if ($existing) {
                if ($conflictMode === 'skip_existing') {
                    $skippedCount++;
                    continue;
                }

                if ($conflictMode === 'update_new_only') {
                    $fillAttributes = [];
                    foreach ($updateAttributes as $key => $val) {
                        if (empty($existing->$key)) {
                            $fillAttributes[$key] = $val;
                        }
                    }
                    if (!empty($fillAttributes)) {
                        $fillAttributes['updated_at'] = $now;
                        DB::table('students')->where('id', $existing->id)->update($fillAttributes);
                    }
                    $processedCount++;
                    continue;
                }

                DB::table('students')->where('id', $existing->id)->update($updateAttributes);
            } else {
                $updateAttributes['school_id'] = $schoolId;
                $currentSessionId = DB::table('academic_sessions')
                    ->where('school_id', $schoolId)
                    ->where('is_current', 1)
                    ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
                if ($currentSessionId) {
                    $updateAttributes['academic_session_id'] = $currentSessionId;
                }
                if (!empty($admissionNo)) {
                    $updateAttributes['admission_number'] = $admissionNo;
                }
                DB::table('students')->insert($updateAttributes);
            }
            $processedCount++;
        }

        $msg = $processedCount . ' students successfully imported or updated.';
        if ($skippedCount > 0) {
            $msg .= " ($skippedCount skipped as existing).";
        }

        return response()->json([
            'message' => $msg,
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $student = DB::table('students')
            ->where('id', $id)
            ->where('school_id', $user->school_id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_name' => 'required|string',
            'section' => 'nullable|string',
            'admission_number' => 'nullable|string',
            'roll_number' => 'nullable|string',
            'father_name' => 'nullable|string',
            'mother_name' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'category' => 'nullable|string',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
            'aadhar_number' => 'nullable|string',
            'email' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'student_pen' => 'nullable|string',
            'student_nic_id' => 'nullable|string',
            'apaar_id' => 'nullable|string',
            'bpl_beneficiary' => 'nullable|string',
            'is_rte' => 'nullable|boolean',
        ]);

        DB::table('students')->where('id', $id)->update([
            'name' => $validated['name'],
            'class_name' => $validated['class_name'],
            'section' => $validated['section'] ?? null,
            'admission_number' => $validated['admission_number'] ?? null,
            'roll_number' => $validated['roll_number'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'category' => $validated['category'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'address' => $validated['address'] ?? null,
            'aadhar_number' => $validated['aadhar_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'student_pen' => $validated['student_pen'] ?? null,
            'student_nic_id' => $validated['student_nic_id'] ?? null,
            'apaar_id' => $validated['apaar_id'] ?? null,
            'bpl_beneficiary' => $validated['bpl_beneficiary'] ?? null,
            'is_rte' => $request->has('is_rte') ? ($request->boolean('is_rte') ? 1 : 0) : ($student->is_rte ?? 0),
            'updated_at' => now(),
        ]);

        $updatedStudent = DB::table('students')->where('id', $id)->first();
        return response()->json($updatedStudent, 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $student = DB::table('students')->where('id', $id)->where('school_id', $user->school_id)->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        DB::table('students')->where('id', $id)->delete();
        return response()->json(['message' => 'Student deleted successfully.'], 200);
    }

    public function markStruckOff(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $student = DB::table('students')
            ->where('id', $id)
            ->where('school_id', $user->school_id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $reason = $request->input('reason', 'Prolonged Unsanctioned Absence');
        $date = $request->input('struck_off_date', date('Y-m-d'));
        $remarks = $request->input('remarks', '');

        DB::table('students')->where('id', $id)->update([
            'status' => 'struck_off',
            'struck_off_reason' => $reason,
            'struck_off_date' => $date,
            'remarks' => $remarks ?: $student->remarks,
            'updated_at' => now(),
        ]);

        $updated = DB::table('students')->where('id', $id)->first();

        return response()->json([
            'message' => "Student {$student->name} has been struck off from active roll.",
            'student' => $updated,
        ], 200);
    }

    public function reactivate(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $student = DB::table('students')
            ->where('id', $id)
            ->where('school_id', $user->school_id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        DB::table('students')->where('id', $id)->update([
            'status' => 'active',
            'struck_off_reason' => null,
            'struck_off_date' => null,
            'updated_at' => now(),
        ]);

        $updated = DB::table('students')->where('id', $id)->first();

        return response()->json([
            'message' => "Student {$student->name} has been reactivated to the active roll.",
            'student' => $updated,
        ], 200);
    }

    public function bulkPromote(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'from_session_id' => 'required|exists:academic_sessions,id',
            'to_session_id' => 'required|exists:academic_sessions,id',
            'promotions' => 'required|array|min:1',
            'promotions.*.student_id' => 'required|exists:students,id',
            'promotions.*.action' => 'required|in:promote,retain,passed_out,tc_issued',
            'promotions.*.to_class' => 'nullable|string',
            'promotions.*.to_section' => 'nullable|string',
            'promotions.*.remarks' => 'nullable|string',
        ]);

        $schoolId = $user->school_id;
        $fromSessionId = (int)$request->from_session_id;
        $toSessionId = (int)$request->to_session_id;
        $now = now();
        $date = now()->toDateString();

        $currentSession = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->first();
        $currentSessionId = $currentSession ? (int)$currentSession->id : null;
        $isPromotingToActiveSession = ($currentSessionId && $toSessionId === $currentSessionId);

        $processed = 0;
        DB::beginTransaction();
        try {
            foreach ($request->promotions as $p) {
                $st = DB::table('students')->where('id', $p['student_id'])->where('school_id', $schoolId)->first();
                if (!$st) continue;

                $action = $p['action'];
                $toClass = $p['to_class'] ?: $st->class_name;
                $toSection = $p['to_section'] ?: $st->section;

                // Safely stage/record in student_promotions
                DB::table('student_promotions')->updateOrInsert(
                    [
                        'school_id' => $schoolId,
                        'student_id' => $st->id,
                        'to_session_id' => $toSessionId,
                    ],
                    [
                        'from_session_id' => $fromSessionId,
                        'from_class' => $st->class_name,
                        'from_section' => $st->section,
                        'to_class' => $toClass,
                        'to_section' => $toSection,
                        'action' => $action,
                        'promotion_date' => $date,
                        'remarks' => $p['remarks'] ?? null,
                        'updated_at' => $now,
                    ]
                );

                // Only mutate current student record if the target session is already the active session!
                // If it is an upcoming session, the student remains in their current class for the running year.
                if ($isPromotingToActiveSession) {
                    $updateData = ['updated_at' => $now];
                    if ($action === 'promote' || $action === 'retain') {
                        $updateData['academic_session_id'] = $toSessionId;
                        $updateData['class_name'] = $toClass;
                        $updateData['section'] = $toSection;
                        $updateData['status'] = 'active';
                    } elseif ($action === 'passed_out') {
                        $updateData['academic_session_id'] = $toSessionId;
                        $updateData['status'] = 'alumni';
                    } elseif ($action === 'tc_issued') {
                        $updateData['status'] = 'tc_issued';
                    }

                    DB::table('students')->where('id', $st->id)->update($updateData);
                }

                $processed++;
            }
            DB::commit();

            return response()->json([
                'message' => "Successfully processed promotion for $processed student(s).",
                'count' => $processed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process promotions: ' . $e->getMessage()], 500);
        }
    }

    public function bulkAssignRollNumbers(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'class_name' => 'required|string',
            'section' => 'nullable|string',
            'session_id' => 'nullable|integer',
            'skip_existing' => 'nullable|boolean',
            'start_from' => 'nullable|integer',
        ]);

        $className = self::normalizeClassName($request->class_name);
        $section = self::normalizeSection($request->section);
        $skipExisting = $request->boolean('skip_existing', true);
        $startFrom = max(1, (int)($request->start_from ?? 1));

        $query = DB::table('students')
            ->where('school_id', $user->school_id)
            ->where('class_name', $className)
            ->where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            });

        if ($section) {
            $query->where('section', $section);
        }

        if ($request->session_id) {
            $query->where('academic_session_id', $request->session_id);
        }

        // Alphabetical A to Z ordering for normal session setup
        $students = $query->orderBy('name', 'asc')->get();

        if ($students->isEmpty()) {
            return response()->json(['message' => 'No active students found in the selected class.'], 404);
        }

        // Collect existing roll numbers if skipExisting is true (protect mid-session additions)
        $usedRolls = [];
        if ($skipExisting) {
            foreach ($students as $st) {
                if (!empty($st->roll_number) && is_numeric($st->roll_number)) {
                    $usedRolls[] = (int)$st->roll_number;
                }
            }
        }

        $currentRoll = $startFrom;
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($students as $st) {
                // If student already has a roll number and skipExisting is true, preserve it (mid-session protection)
                if ($skipExisting && !empty($st->roll_number)) {
                    continue;
                }

                // Advance currentRoll if it collides with a preserved roll number
                while (in_array($currentRoll, $usedRolls)) {
                    $currentRoll++;
                }

                DB::table('students')->where('id', $st->id)->update([
                    'roll_number' => (string)$currentRoll,
                    'updated_at' => now(),
                ]);

                $usedRolls[] = $currentRoll;
                $currentRoll++;
                $updated++;
            }
            DB::commit();

            return response()->json([
                'message' => "Successfully allocated roll numbers to $updated student(s).",
                'updated_count' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to assign roll numbers: ' . $e->getMessage()], 500);
        }
    }

    public function updateRollNumbers(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'roll_numbers' => 'required|array',
            'roll_numbers.*.id' => 'required|integer',
            'roll_numbers.*.roll_number' => 'nullable|string',
        ]);

        $items = $request->roll_numbers;
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                DB::table('students')
                    ->where('id', $item['id'])
                    ->where('school_id', $user->school_id)
                    ->update([
                        'roll_number' => !empty($item['roll_number']) ? trim((string)$item['roll_number']) : null,
                        'updated_at' => now(),
                    ]);
            }
            DB::commit();
            return response()->json(['message' => 'Roll numbers updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update roll numbers: ' . $e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $students = $this->getStudentsForSession($request, $user->school_id);

        $filename = 'students_export_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($students) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM so Excel opens with proper encoding
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Roll No',
                'Admission No',
                'Student NIC ID',
                'Student Name',
                'Class',
                'Section',
                'RTE (Right to Education)',
                'Father Name',
                'Mother Name',
                'Date of Birth',
                'Gender',
                'Category',
                'Religion',
                'Contact Number',
                'Address',
                'Aadhar Number',
                'Student PEN (UDISE)',
                'APAAR ID (UDISE)',
                'BPL Beneficiary',
                'Status'
            ], ',', '"', "\\");

            foreach ($students as $s) {
                fputcsv($handle, [
                    $s->roll_number ?? '',
                    $s->admission_number ?? '',
                    $s->student_nic_id ?? '',
                    $s->name ?? '',
                    $s->class_name ?? '',
                    $s->section ?? '',
                    (!empty($s->is_rte) && ($s->is_rte == 1 || $s->is_rte === true)) ? 'Yes' : 'No',
                    $s->father_name ?? '',
                    $s->mother_name ?? '',
                    $s->dob ?? '',
                    $s->gender ?? '',
                    $s->category ?? '',
                    $s->religion ?? '',
                    $s->contact_number ?? '',
                    $s->address ?? '',
                    $s->aadhar_number ?? '',
                    $s->student_pen ?? '',
                    $s->apaar_id ?? '',
                    $s->bpl_beneficiary ?? '',
                    $s->status ?? 'active',
                ], ',', '"', "\\");
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
