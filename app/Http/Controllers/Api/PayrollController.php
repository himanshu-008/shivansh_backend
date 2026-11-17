<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\SchoolHoliday;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * List payroll records for a given month, year, and session
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $schoolId = $user->school_id;
        $month = (int)($request->query('month') ?? Carbon::today()->month);
        $year = (int)($request->query('year') ?? Carbon::today()->year);

        $query = Payroll::with('staff')
            ->where('school_id', $schoolId)
            ->where('month', $month)
            ->where('year', $year);

        if ($request->filled('session_id') && $request->session_id !== 'all') {
            $sessId = (int)$request->session_id;
            $query->where(function ($q) use ($sessId) {
                $q->where('academic_session_id', $sessId)
                  ->orWhereNull('academic_session_id');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $payrolls = $query->orderBy('id', 'asc')->get();

        $summary = [
            'total_staff' => $payrolls->count(),
            'total_gross' => (float)$payrolls->sum('gross_salary'),
            'total_deductions' => (float)$payrolls->sum('deductions'),
            'total_net' => (float)$payrolls->sum('net_salary'),
            'paid_count' => $payrolls->where('status', 'paid')->count(),
            'pending_count' => $payrolls->where('status', '!=', 'paid')->count(),
            'paid_amount' => (float)$payrolls->where('status', 'paid')->sum('net_salary'),
            'pending_amount' => (float)$payrolls->where('status', '!=', 'paid')->sum('net_salary'),
        ];

        return response()->json([
            'success' => true,
            'month' => $month,
            'year' => $year,
            'summary' => $summary,
            'data' => $payrolls,
        ]);
    }

    /**
     * Generate or recalculate payroll for the month with Vacation & Holiday logic
     */
    public function calculate(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2035',
            'session_id' => 'nullable|integer',
        ]);

        $schoolId = $user->school_id;
        $month = (int)$validated['month'];
        $year = (int)$validated['year'];
        $sessionId = $validated['session_id'] ?? null;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $totalDays = $startDate->daysInMonth;

        // Fetch Holidays & Vacations in this month
        $holidays = SchoolHoliday::where('school_id', $schoolId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate->toDateString())
                          ->where('end_date', '>=', $endDate->toDateString());
                  });
            })
            ->get();

        // Categorize each day of the month into: normal, sunday, festival_holiday, summer_vacation, winter_break
        $dayTypes = [];
        $monthVacationDaysCount = 0;
        $monthHolidayDaysCount = 0;

        for ($d = 1; $d <= $totalDays; $d++) {
            $cur = Carbon::create($year, $month, $d);
            $dStr = $cur->toDateString();

            $isSunday = $cur->isSunday();
            $matched = $holidays->first(fn($h) => $dStr >= $h->start_date->toDateString() && $dStr <= $h->end_date->toDateString());

            if ($matched) {
                if (in_array($matched->type, ['summer_vacation', 'winter_break'])) {
                    $dayTypes[$dStr] = 'vacation';
                    $monthVacationDaysCount++;
                } else {
                    $dayTypes[$dStr] = 'holiday';
                    $monthHolidayDaysCount++;
                }
            } elseif ($isSunday) {
                $dayTypes[$dStr] = 'holiday'; // Sundays are paid weekly offs
                $monthHolidayDaysCount++;
            } else {
                $dayTypes[$dStr] = 'working_day';
            }
        }

        $activeWorkingDaysInMonth = $totalDays - $monthVacationDaysCount;

        // Active staff members
        $staffList = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->get();

        // Fetch existing attendance records
        $attendances = StaffAttendance::where('school_id', $schoolId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('staff_id');

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($staffList as $st) {
            $stAtt = $attendances->get($st->id) ? $attendances->get($st->id)->keyBy(fn($item) => $item->date->format('Y-m-d')) : collect();

            $pCount = 0.0;
            $plCount = 0.0;
            $aCount = 0.0;
            $hdCount = 0.0;
            $hCount = 0.0;
            $vCount = 0.0;

            for ($d = 1; $d <= $totalDays; $d++) {
                $dStr = Carbon::create($year, $month, $d)->toDateString();
                $dtype = $dayTypes[$dStr];

                if ($stAtt->has($dStr)) {
                    $stat = $stAtt->get($dStr)->status;
                    match ($stat) {
                        'present' => $pCount += 1.0,
                        'paid_leave' => $plCount += 1.0,
                        'absent' => $aCount += 1.0,
                        'half_day' => $hdCount += 1.0,
                        'holiday' => $hCount += 1.0,
                        'vacation' => $vCount += 1.0,
                        default => $pCount += 1.0,
                    };
                } else {
                    // Default based on calendar
                    if ($dtype === 'vacation') {
                        $vCount += 1.0;
                    } elseif ($dtype === 'holiday') {
                        $hCount += 1.0;
                    } else {
                        // If no specific absent marked, default to present for staff
                        $pCount += 1.0;
                    }
                }
            }

            // Standard gross salary
            $baseSalary = (float)$st->base_salary;
            $allowances = (float)($st->hra + $st->da + $st->allowances);
            $grossSalary = $baseSalary + $allowances;
            $stdDeductions = (float)($st->pf_deduction + $st->tds_deduction + $st->other_deductions);

            // Compute Payable Days based on Vacation Policy
            $policy = $st->vacation_pay_policy ?? 'full_pay_12_months';
            $unpaidVacationDays = 0.0;

            if ($policy === 'full_pay_12_months') {
                // Permanent Teacher: full vacation is paid
                $unpaidVacationDays = 0.0;
                $payableDays = $pCount + $plCount + $hCount + $vCount + ($hdCount * 0.5);
            } elseif ($policy === 'unpaid_vacation') {
                // Contractual Teacher: vacation days are unpaid!
                $unpaidVacationDays = $vCount;
                $payableDays = $pCount + $plCount + $hCount + ($hdCount * 0.5);
            } else {
                // Half-pay during vacation
                $unpaidVacationDays = $vCount * 0.5;
                $payableDays = $pCount + $plCount + $hCount + ($vCount * 0.5) + ($hdCount * 0.5);
            }

            // Ensure payable days does not exceed total days
            $payableDays = min((float)$totalDays, max(0.0, $payableDays));
            $unpaidAbsentDays = $aCount + ($hdCount * 0.5);

            // Pro-rata Calculation
            if ($totalDays > 0) {
                $proRataRatio = $payableDays / (float)$totalDays;
                $computedGross = round($grossSalary * $proRataRatio, 2);
            } else {
                $computedGross = $grossSalary;
            }

            // Pro-rata deductions if salary is reduced, or standard deductions
            $deductions = min($computedGross, $stdDeductions);
            $netSalary = max(0.0, round($computedGross - $deductions, 2));

            // Don't overwrite if already marked as 'paid'
            $existing = Payroll::where('school_id', $schoolId)
                ->where('staff_id', $st->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($existing && $existing->status === 'paid') {
                continue;
            }

            Payroll::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'staff_id' => $st->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'academic_session_id' => $sessionId,
                    'total_days' => $totalDays,
                    'active_working_days' => $activeWorkingDaysInMonth,
                    'present_days' => $pCount,
                    'paid_leaves' => $plCount,
                    'holidays' => $hCount,
                    'vacation_days' => $vCount,
                    'unpaid_vacation_days' => $unpaidVacationDays,
                    'unpaid_absent_days' => $unpaidAbsentDays,
                    'payable_days' => $payableDays,
                    'base_salary' => $baseSalary,
                    'allowances' => $allowances,
                    'gross_salary' => $computedGross,
                    'deductions' => $deductions,
                    'net_salary' => $netSalary,
                    'status' => 'processed',
                    'remarks' => ($unpaidVacationDays > 0)
                        ? "Pro-rated: {$unpaidVacationDays} days unpaid summer/winter break"
                        : "Standard full monthly pay",
                ]
            );
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Payroll calculated for $updatedCount staff members for month $month/$year.",
            'vacation_days_in_month' => $monthVacationDaysCount,
            'active_working_days' => $activeWorkingDaysInMonth,
        ]);
    }

    /**
     * Update individual payroll record (e.g. adjustments, bonus, advance, custom remarks)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $payroll = Payroll::where('school_id', $user->school_id)->findOrFail($id);

        $validated = $request->validate([
            'payable_days' => 'sometimes|numeric|min:0',
            'gross_salary' => 'sometimes|numeric|min:0',
            'deductions' => 'sometimes|numeric|min:0',
            'net_salary' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,processed,paid',
            'payment_method' => 'sometimes|in:bank_transfer,cash,cheque,upi',
            'transaction_ref' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $payroll->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payroll record updated.',
            'data' => $payroll,
        ]);
    }

    /**
     * Disburse payroll & sync automatically with Expenses table
     */
    public function disburse(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'integer|exists:payrolls,id',
            'payment_method' => 'required|in:bank_transfer,cash,cheque,upi',
            'payment_date' => 'nullable|date',
            'transaction_ref' => 'nullable|string',
        ]);

        $schoolId = $user->school_id;
        $paymentDate = $validated['payment_date'] ?? Carbon::today()->toDateString();
        $paymentMethod = $validated['payment_method'];
        $txRef = $validated['transaction_ref'] ?? null;

        $payrolls = Payroll::with('staff')
            ->where('school_id', $schoolId)
            ->whereIn('id', $validated['payroll_ids'])
            ->get();

        $disbursedCount = 0;
        $totalDisbursed = 0.0;

        DB::transaction(function () use ($payrolls, $paymentDate, $paymentMethod, $txRef, $user, $schoolId, &$disbursedCount, &$totalDisbursed) {
            foreach ($payrolls as $p) {
                if ($p->net_salary <= 0) {
                    $p->update([
                        'status' => 'paid',
                        'payment_date' => $paymentDate,
                        'payment_method' => $paymentMethod,
                        'transaction_ref' => $txRef,
                    ]);
                    $disbursedCount++;
                    continue;
                }

                $monthName = Carbon::create($p->year, $p->month, 1)->format('M Y');
                $staffName = $p->staff ? $p->staff->name : 'Staff';
                $empCode = $p->staff ? $p->staff->employee_code : '';

                // Create or update record in Expenses table
                $expense = null;
                if ($p->expense_id) {
                    $expense = Expense::where('school_id', $schoolId)->find($p->expense_id);
                }

                if ($expense) {
                    $expense->update([
                        'amount' => $p->net_salary,
                        'expense_date' => $paymentDate,
                        'payment_method' => $paymentMethod,
                        'invoice_number' => $txRef ?? $expense->invoice_number,
                    ]);
                } else {
                    $expense = Expense::create([
                        'school_id' => $schoolId,
                        'academic_session_id' => $p->academic_session_id,
                        'category' => 'Staff Salaries',
                        'title' => "Salary - $staffName ($empCode) - $monthName",
                        'amount' => $p->net_salary,
                        'expense_date' => $paymentDate,
                        'payment_method' => $paymentMethod,
                        'vendor_name' => $staffName,
                        'invoice_number' => $txRef ?? "SAL-{$p->year}{$p->month}-{$p->id}",
                        'description' => "Monthly payroll disbursement for $monthName. Total payable days: {$p->payable_days}/{$p->total_days}",
                        'created_by' => $user->id,
                    ]);
                }

                $p->update([
                    'status' => 'paid',
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'transaction_ref' => $txRef,
                    'expense_id' => $expense->id,
                ]);

                $disbursedCount++;
                $totalDisbursed += (float)$p->net_salary;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Successfully disbursed salary for $disbursedCount staff members totaling ₹ " . number_format($totalDisbursed, 2),
            'disbursed_count' => $disbursedCount,
            'total_amount' => $totalDisbursed,
        ]);
    }

    /**
     * Get detailed data payload for generating the printable salary slip PDF
     */
    public function payslip($id)
    {
        $user = Auth::user();
        $payroll = Payroll::where('school_id', $user->school_id)
            ->with(['staff', 'session'])
            ->findOrFail($id);

        $monthName = Carbon::create($payroll->year, $payroll->month, 1)->format('F Y');

        return response()->json([
            'success' => true,
            'month_name' => $monthName,
            'payroll' => $payroll,
            'staff' => $payroll->staff,
        ]);
    }

    /**
     * Bank Direct Transfer CSV export
     */
    public function exportBankCsv(Request $request)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;
        $month = (int)($request->query('month') ?? Carbon::today()->month);
        $year = (int)($request->query('year') ?? Carbon::today()->year);

        $payrolls = Payroll::with('staff')
            ->where('school_id', $schoolId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('net_salary', '>', 0)
            ->get();

        $rows = [];
        $rows[] = ['Beneficiary Name', 'Account Number', 'IFSC Code', 'Amount', 'Payment Narration', 'Employee Code'];

        $monthStr = Carbon::create($year, $month, 1)->format('M-Y');

        foreach ($payrolls as $p) {
            $st = $p->staff;
            $rows[] = [
                $st ? $st->name : '',
                $st ? ($st->account_number ?? '') : '',
                $st ? ($st->ifsc_code ?? '') : '',
                $p->net_salary,
                "Salary $monthStr",
                $st ? $st->employee_code : '',
            ];
        }

        return response()->json([
            'success' => true,
            'filename' => "bank_payroll_{$year}_{$month}.csv",
            'rows' => $rows,
        ]);
    }
}
