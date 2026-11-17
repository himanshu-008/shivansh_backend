<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AdmissionEnquiry;
use App\Models\Student;

class AdmissionEnquiryController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = Auth::user()->school_id;

        // Identify running (current) session and next new session
        $sessions = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->orderBy('start_date', 'asc')
            ->get();

        $runningSession = $sessions->firstWhere('is_current', 1) ?? $sessions->first();
        $runningSessionId = $runningSession ? $runningSession->id : null;

        // Find next session after running session
        $nextSession = null;
        if ($runningSession) {
            $nextSession = $sessions->first(function ($s) use ($runningSession) {
                return $s->id !== $runningSession->id && $s->start_date >= $runningSession->end_date;
            });
            if (!$nextSession) {
                // Pick highest ID or latest session if not current
                $nextSession = $sessions->firstWhere('id', '!=', $runningSession->id);
            }
        }
        $nextSessionId = $nextSession ? $nextSession->id : null;

        $query = AdmissionEnquiry::with('academicSession')
            ->where('school_id', $schoolId);

        // Filter by specific session
        if ($request->has('session_id') && !empty($request->session_id) && $request->session_id !== 'all') {
            $query->where('academic_session_id', $request->session_id);
        }

        // Filter by class
        if ($request->has('class_name') && !empty($request->class_name) && $request->class_name !== 'All Classes') {
            $query->where('class_name', $request->class_name);
        }

        // Filter by status
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by search query
        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower(trim($request->search));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(student_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(enquiry_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(father_name) LIKE ?', ["%{$search}%"])
                  ->orWhere('contact_number', 'LIKE', "%{$search}%");
            });
        }

        // Filter pending follow-ups
        if ($request->boolean('follow_up_due')) {
            $query->whereNotNull('follow_up_date')
                  ->where('follow_up_date', '<=', now()->toDateString())
                  ->whereNotIn('status', ['admitted', 'rejected', 'cancelled']);
        }

        $enquiries = $query->orderBy('enquiry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Calculate session and status metrics
        $allEnquiries = AdmissionEnquiry::where('school_id', $schoolId)->get();

        $runningCount = $runningSessionId ? $allEnquiries->where('academic_session_id', $runningSessionId)->count() : 0;
        $nextCount = $nextSessionId ? $allEnquiries->where('academic_session_id', $nextSessionId)->count() : 0;
        $admittedCount = $allEnquiries->where('status', 'admitted')->count();
        $todayStr = now()->toDateString();
        $followUpDueCount = $allEnquiries->filter(function ($e) use ($todayStr) {
            return !empty($e->follow_up_date) && $e->follow_up_date <= $todayStr && !in_array($e->status, ['admitted', 'rejected', 'cancelled']);
        })->count();

        return response()->json([
            'enquiries' => $enquiries,
            'meta' => [
                'running_session' => $runningSession,
                'next_session' => $nextSession,
                'total_count' => $allEnquiries->count(),
                'running_session_count' => $runningCount,
                'next_session_count' => $nextCount,
                'admitted_count' => $admittedCount,
                'follow_up_due_count' => $followUpDueCount,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = Auth::user()->school_id;

        $validated = $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'enquiry_number' => 'nullable|string|max:100',
            'enquiry_date' => 'nullable|date',
            'student_name' => 'required|string|max:255',
            'gender' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'class_name' => 'required|string|max:50',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'contact_number' => 'required|string|max:50',
            'alternate_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'previous_school' => 'nullable|string|max:255',
            'reference_source' => 'nullable|string|max:100',
            'counselor_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:enquired,form_purchased,test_scheduled,interview_done,admitted,rejected,cancelled',
            'follow_up_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'advance_fee_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        // Default session if not given: current or next session
        $sessionId = $validated['academic_session_id'] ?? null;
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        // Generate enquiry number if not provided
        $enquiryNumber = $validated['enquiry_number'] ?? null;
        if (empty($enquiryNumber)) {
            $sessionName = DB::table('academic_sessions')->where('id', $sessionId)->value('name') ?? date('Y');
            $yearPart = explode('-', $sessionName)[0];
            $count = AdmissionEnquiry::where('school_id', $schoolId)->where('academic_session_id', $sessionId)->count() + 1;
            $enquiryNumber = 'ENQ/' . $yearPart . '/' . str_pad($count, 3, '0', STR_PAD_LEFT);
        }

        $enquiry = AdmissionEnquiry::create([
            'school_id' => $schoolId,
            'academic_session_id' => $sessionId,
            'enquiry_number' => $enquiryNumber,
            'enquiry_date' => $validated['enquiry_date'] ?? now()->toDateString(),
            'student_name' => trim($validated['student_name']),
            'gender' => $validated['gender'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'class_name' => trim($validated['class_name']),
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'contact_number' => trim($validated['contact_number']),
            'alternate_number' => $validated['alternate_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'previous_school' => $validated['previous_school'] ?? null,
            'reference_source' => $validated['reference_source'] ?? 'Walk-in',
            'counselor_name' => $validated['counselor_name'] ?? null,
            'status' => $validated['status'] ?? 'enquired',
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'advance_fee_paid' => $validated['advance_fee_paid'] ?? 0.00,
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'transaction_id' => $validated['transaction_id'] ?? null,
        ]);

        $enquiry->load('academicSession');

        return response()->json($enquiry, 201);
    }

    public function show($id)
    {
        $schoolId = Auth::user()->school_id;
        $enquiry = AdmissionEnquiry::with(['academicSession', 'convertedStudent'])
            ->where('school_id', $schoolId)
            ->findOrFail($id);

        return response()->json($enquiry);
    }

    public function update(Request $request, $id)
    {
        $schoolId = Auth::user()->school_id;
        $enquiry = AdmissionEnquiry::where('school_id', $schoolId)->findOrFail($id);

        $validated = $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'student_name' => 'sometimes|required|string|max:255',
            'gender' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'class_name' => 'sometimes|required|string|max:50',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'contact_number' => 'sometimes|required|string|max:50',
            'alternate_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'previous_school' => 'nullable|string|max:255',
            'reference_source' => 'nullable|string|max:100',
            'counselor_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:enquired,form_purchased,test_scheduled,interview_done,admitted,rejected,cancelled',
            'follow_up_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'advance_fee_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        $enquiry->update($validated);
        $enquiry->load('academicSession');

        return response()->json($enquiry);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $enquiry = AdmissionEnquiry::where('school_id', $schoolId)->findOrFail($id);
        $enquiry->delete();

        return response()->json(['message' => 'Admission enquiry deleted successfully.']);
    }

    /**
     * Convert an enquiry directly into an enrolled student in the target session
     */
    public function convertToStudent(Request $request, $id)
    {
        $schoolId = Auth::user()->school_id;
        $enquiry = AdmissionEnquiry::where('school_id', $schoolId)->findOrFail($id);

        if ($enquiry->converted_student_id) {
            return response()->json(['message' => 'This enquiry has already been converted to an admitted student.'], 400);
        }

        $admissionNumber = $request->input('admission_number');
        if (empty($admissionNumber)) {
            // Auto generate admission number if not provided
            $lastAdm = DB::table('students')->where('school_id', $schoolId)->max('id') ?? 0;
            $admissionNumber = 'ADM/' . date('Y') . '/' . ($lastAdm + 1);
        }

        $section = $request->input('section') ?? 'A';

        $student = Student::create([
            'school_id' => $schoolId,
            'academic_session_id' => $enquiry->academic_session_id,
            'name' => $enquiry->student_name,
            'admission_number' => $admissionNumber,
            'class_name' => $request->input('class_name') ?? $enquiry->class_name,
            'section' => $section,
            'father_name' => $enquiry->father_name,
            'mother_name' => $enquiry->mother_name,
            'dob' => $enquiry->dob,
            'gender' => $enquiry->gender,
            'contact_number' => $enquiry->contact_number,
            'address' => $enquiry->address,
            'status' => 'active',
        ]);

        $enquiry->update([
            'status' => 'admitted',
            'converted_student_id' => $student->id,
            'notes' => ($enquiry->notes ? $enquiry->notes . "\n" : '') . "Converted to student on " . now()->toFormattedDateString() . " with Adm No: {$admissionNumber}.",
        ]);

        return response()->json([
            'message' => "Successfully admitted {$enquiry->student_name} with Admission No: {$admissionNumber}!",
            'student' => $student,
            'enquiry' => $enquiry,
        ]);
    }
}
