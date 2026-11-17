<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }
        $schoolId = $user->school_id;

        // Resolve requested session or current session
        $currentSession = DB::table('academic_sessions')
            ->where('school_id', $schoolId)
            ->where('is_current', 1)
            ->first();
        $currentSessionId = $currentSession ? (int)$currentSession->id : (
            (int)(DB::table('academic_sessions')->where('school_id', $schoolId)->orderBy('id', 'asc')->value('id') ?? 0)
        );

        $sessionId = ($request->has('session_id') && !empty($request->session_id))
            ? (int)$request->session_id
            : $currentSessionId;

        // 1. STUDENTS (Session-aware)
        $studentController = new StudentController();
        $studentsReq = new Request(['session_id' => $sessionId, 'status' => 'active']);
        $students = $studentController->getStudentsForSession($studentsReq, $schoolId);

        $totalActive = count($students);
        $boys = 0;
        $girls = 0;
        $rteCount = 0;
        $nonRteCount = 0;
        $classCounts = [];

        foreach ($students as $st) {
            $g = strtolower(trim($st->gender ?? ''));
            if ($g === 'male' || $g === 'boy' || $g === 'm') $boys++;
            elseif ($g === 'female' || $g === 'girl' || $g === 'f') $girls++;

            $isRte = !empty($st->is_rte) && ($st->is_rte == 1 || $st->is_rte === true || $st->is_rte === '1');
            if ($isRte) {
                $rteCount++;
            } else {
                $nonRteCount++;
            }

            $c = $st->class_name ?? 'Unknown';
            $classCounts[$c] = ($classCounts[$c] ?? 0) + 1;
        }

        $standardOrder = [
            'Play Group', 'Nursery', 'LKG', 'UKG',
            '1st', '2nd', '3rd', '4th', '5th',
            '6th', '7th', '8th', '9th', '10th',
            '11th', '12th'
        ];
        $classWise = [];
        foreach ($standardOrder as $cn) {
            if (isset($classCounts[$cn])) {
                $classWise[] = ['class_name' => $cn, 'count' => $classCounts[$cn]];
                unset($classCounts[$cn]);
            }
        }
        foreach ($classCounts as $cn => $count) {
            $classWise[] = ['class_name' => $cn, 'count' => $count];
        }

        $tcCount = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'tc_issued')
            ->count();

        // 2. FEES & FINANCIALS
        $invoiceQuery = DB::table('fee_invoices')->where('school_id', $schoolId);
        if ($sessionId) {
            $invoiceQuery->where('academic_session_id', $sessionId);
        }
        $totalInvoiced = (float)($invoiceQuery->sum('amount') ?? 0);

        $paymentQuery = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where(function($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            });
        if ($sessionId) {
            $paymentQuery->where('academic_session_id', $sessionId);
        }
        $totalCollected = (float)($paymentQuery->sum('amount') ?? 0);

        $discountQuery = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('is_discount', 1);
        if ($sessionId) {
            $discountQuery->where('academic_session_id', $sessionId);
        }
        $totalDiscount = (float)($discountQuery->sum('amount') ?? 0);

        $totalPending = max(0, $totalInvoiced - $totalDiscount - $totalCollected);

        $todayDate = now()->toDateString();
        $todayCollected = (float)(DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('payment_date', $todayDate)
            ->where(function($q) {
                $q->where('is_discount', 0)->orWhereNull('is_discount');
            })
            ->sum('amount') ?? 0);

        $recentPayments = DB::table('fee_payments')
            ->join('students', 'fee_payments.student_id', '=', 'students.id')
            ->leftJoin('fee_categories', 'fee_payments.fee_category_id', '=', 'fee_categories.id')
            ->where('fee_payments.school_id', $schoolId)
            ->where(function($q) {
                $q->where('fee_payments.is_discount', 0)->orWhereNull('fee_payments.is_discount');
            })
            ->select(
                'fee_payments.id',
                'fee_payments.transaction_id as receipt_number',
                'fee_payments.amount',
                'fee_payments.payment_method',
                'fee_payments.payment_date',
                'students.name as student_name',
                'students.class_name',
                'fee_categories.name as fee_category_name'
            )
            ->orderBy('fee_payments.id', 'desc')
            ->limit(5)
            ->get();

        // 3. ADMISSION ENQUIRIES
        $enquiryQuery = DB::table('admission_enquiries')->where('school_id', $schoolId);
        if ($sessionId) {
            $enquiryQuery->where(function($q) use ($sessionId) {
                $q->where('target_session_id', $sessionId)
                  ->orWhereNull('target_session_id');
            });
        }
        $totalEnquiries = (clone $enquiryQuery)->count();
        $newPendingEnquiries = (clone $enquiryQuery)->whereIn('status', ['new', 'pending', 'follow_up'])->count();
        $convertedEnquiries = (clone $enquiryQuery)->where('status', 'converted')->count();

        // 4. ATTENDANCE
        $todayAttendance = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->where('date', $todayDate)
            ->get();
        $attendanceTotal = $todayAttendance->count();
        $attendancePresent = $todayAttendance->where('status', 'present')->count();
        $attendanceMarked = $attendanceTotal > 0;
        $attendancePercent = $attendanceTotal > 0 ? round(($attendancePresent / $attendanceTotal) * 100, 1) : 0;

        return response()->json([
            'session' => [
                'id' => $sessionId,
                'name' => DB::table('academic_sessions')->where('id', $sessionId)->value('name') ?? 'Current',
                'is_current' => ($sessionId === $currentSessionId),
            ],
            'students' => [
                'total_active' => $totalActive,
                'boys' => $boys,
                'girls' => $girls,
                'other_gender' => max(0, $totalActive - ($boys + $girls)),
                'rte_count' => $rteCount,
                'non_rte_count' => $nonRteCount,
                'tc_issued' => $tcCount,
                'class_wise' => $classWise,
            ],
            'fees' => [
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'total_discount' => $totalDiscount,
                'total_pending' => $totalPending,
                'today_collected' => $todayCollected,
                'collection_rate' => $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0,
                'recent_payments' => $recentPayments,
            ],
            'enquiries' => [
                'total' => $totalEnquiries,
                'pending' => $newPendingEnquiries,
                'converted' => $convertedEnquiries,
            ],
            'attendance' => [
                'is_marked' => $attendanceMarked,
                'total_marked' => $attendanceTotal,
                'present' => $attendancePresent,
                'absent' => $attendanceTotal - $attendancePresent,
                'percentage' => $attendancePercent,
            ],
        ]);
    }
}
