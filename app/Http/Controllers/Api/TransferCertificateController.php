<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TransferCertificateController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $query = DB::table('transfer_certificates')
            ->join('students', 'transfer_certificates.student_id', '=', 'students.id')
            ->leftJoin('academic_sessions', 'transfer_certificates.academic_session_id', '=', 'academic_sessions.id')
            ->where('transfer_certificates.school_id', $user->school_id)
            ->select(
                'transfer_certificates.*',
                'students.name as student_name',
                'students.admission_number',
                'students.class_name as current_class',
                'students.section',
                'students.father_name',
                'students.dob',
                'students.contact_number',
                'students.student_pen',
                'academic_sessions.name as session_name'
            );

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('students.name', 'like', $search)
                  ->orWhere('students.admission_number', 'like', $search)
                  ->orWhere('transfer_certificates.tc_number', 'like', $search)
                  ->orWhere('students.father_name', 'like', $search);
            });
        }

        if ($request->filled('session_id')) {
            $query->where('transfer_certificates.academic_session_id', $request->session_id);
        }

        $records = $query->orderBy('transfer_certificates.issue_date', 'desc')
            ->orderBy('transfer_certificates.id', 'desc')
            ->get();

        return response()->json($records);
    }

    public function checkStudentDues($studentId)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $student = DB::table('students')
            ->where('school_id', $user->school_id)
            ->where('id', $studentId)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        // Sessions list for breakdown
        $sessions = DB::table('academic_sessions')
            ->where('school_id', $user->school_id)
            ->orderBy('start_date', 'asc')
            ->get();

        // Total Invoices
        $invoices = DB::table('fee_invoices')
            ->select('academic_session_id', DB::raw('SUM(amount) as total_amount'))
            ->where('school_id', $user->school_id)
            ->where('student_id', $studentId)
            ->groupBy('academic_session_id')
            ->get()
            ->keyBy('academic_session_id');

        // Total Payments
        $payments = DB::table('fee_payments')
            ->select('academic_session_id', 'is_discount', DB::raw('SUM(amount) as total_amount'))
            ->where('school_id', $user->school_id)
            ->where('student_id', $studentId)
            ->groupBy('academic_session_id', 'is_discount')
            ->get();

        $paymentsMap = [];
        foreach ($payments as $p) {
            $discKey = $p->is_discount ? 'discount' : 'paid';
            $paymentsMap[$p->academic_session_id][$discKey] = (float)$p->total_amount;
        }

        $totalBilledAll = 0.0;
        $totalPaidAll = 0.0;
        $totalDiscountAll = 0.0;
        $sessionBreakdown = [];

        foreach ($sessions as $s) {
            $sId = $s->id;
            $billed = isset($invoices[$sId]) ? (float)$invoices[$sId]->total_amount : 0.0;
            $paid = $paymentsMap[$sId]['paid'] ?? 0.0;
            $disc = $paymentsMap[$sId]['discount'] ?? 0.0;
            $balance = max(0.0, $billed - $paid - $disc);

            $totalBilledAll += $billed;
            $totalPaidAll += $paid;
            $totalDiscountAll += $disc;

            $sessionBreakdown[] = [
                'session_id' => $sId,
                'session_name' => $s->name,
                'is_current' => (bool)$s->is_current,
                'billed' => $billed,
                'paid' => $paid,
                'discount' => $disc,
                'balance' => $balance,
            ];
        }

        $totalPendingAll = max(0.0, $totalBilledAll - $totalPaidAll - $totalDiscountAll);

        // Fetch latest attendance stats
        $attMonthly = DB::table('monthly_attendances')
            ->where('student_id', $studentId)
            ->select(
                DB::raw('SUM(total_working_days) as total_working'),
                DB::raw('SUM(attended_days) as total_attended')
            )
            ->first();

        $workingDays = $attMonthly && $attMonthly->total_working ? (int)$attMonthly->total_working : 0;
        $attendedDays = $attMonthly && $attMonthly->total_attended ? (int)$attMonthly->total_attended : 0;

        return response()->json([
            'student' => $student,
            'total_billed' => $totalBilledAll,
            'total_paid' => $totalPaidAll,
            'total_discount' => $totalDiscountAll,
            'pending_dues' => $totalPendingAll,
            'dues_cleared' => ($totalPendingAll <= 0),
            'sessions' => $sessionBreakdown,
            'attendance' => [
                'total_working_days' => $workingDays,
                'attended_days' => $attendedDays,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'tc_number' => 'nullable|string|max:100',
            'application_date' => 'nullable|date',
            'issue_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'conduct' => 'nullable|string|max:100',
            'last_class_studied' => 'nullable|string|max:50',
            'qualified_for_promotion' => 'nullable|string|max:50',
            'total_working_days' => 'nullable|integer|min:0',
            'attended_days' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
            'rajpsp_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $student = DB::table('students')
            ->where('school_id', $user->school_id)
            ->where('id', $request->student_id)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        // Active session ID
        $sessionId = DB::table('academic_sessions')
            ->where('school_id', $user->school_id)
            ->where('is_current', 1)
            ->value('id') ?? DB::table('academic_sessions')->where('school_id', $user->school_id)->value('id');

        // Calculate dues
        $billed = DB::table('fee_invoices')->where('student_id', $student->id)->sum('amount') ?? 0;
        $paid = DB::table('fee_payments')->where('student_id', $student->id)->where('is_discount', false)->sum('amount') ?? 0;
        $disc = DB::table('fee_payments')->where('student_id', $student->id)->where('is_discount', true)->sum('amount') ?? 0;
        $pendingDues = max(0, $billed - $paid - $disc);

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('rajpsp_file')) {
            $file = $request->file('rajpsp_file');
            $fileName = $file->getClientOriginalName();
            $storedName = 'tc_' . $student->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('transfer_certificates', $storedName, 'public');
        }

        $tcId = DB::table('transfer_certificates')->insertGetId([
            'school_id' => $user->school_id,
            'student_id' => $student->id,
            'academic_session_id' => $sessionId,
            'tc_number' => $request->tc_number ?: ('TC/' . date('Y') . '/' . str_pad($student->id, 4, '0', STR_PAD_LEFT)),
            'application_date' => $request->application_date,
            'issue_date' => $request->issue_date,
            'reason' => $request->reason ?: 'Parent Request / Relocation',
            'conduct' => $request->conduct ?: 'Good',
            'last_class_studied' => $request->last_class_studied ?: $student->class_name,
            'qualified_for_promotion' => $request->qualified_for_promotion ?: 'Yes',
            'total_working_days' => (int)($request->total_working_days ?? 0),
            'attended_days' => (int)($request->attended_days ?? 0),
            'dues_cleared' => ($pendingDues <= 0),
            'pending_dues_amount' => $pendingDues,
            'rajpsp_file_path' => $filePath,
            'rajpsp_file_name' => $fileName,
            'remarks' => $request->remarks,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update student status to 'tc_issued'
        DB::table('students')->where('id', $student->id)->update([
            'status' => 'tc_issued',
            'updated_at' => now(),
        ]);

        $created = DB::table('transfer_certificates')->where('id', $tcId)->first();

        return response()->json([
            'message' => "Transfer Certificate issued successfully for student {$student->name}.",
            'tc' => $created,
        ], 201);
    }

    public function downloadRajPspFile($id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $tc = DB::table('transfer_certificates')
            ->where('school_id', $user->school_id)
            ->where('id', $id)
            ->first();

        if (!$tc || !$tc->rajpsp_file_path) {
            return response()->json(['message' => 'RajPSP TC document not found for this certificate.'], 404);
        }

        $path = storage_path('app/public/' . $tc->rajpsp_file_path);
        if (!file_exists($path)) {
            return response()->json(['message' => 'Document file does not exist on server storage.'], 404);
        }

        return response()->download($path, $tc->rajpsp_file_name ?: 'RajPSP_TC.pdf');
    }
}
