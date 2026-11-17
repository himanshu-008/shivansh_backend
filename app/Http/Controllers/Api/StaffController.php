<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Staff::where('school_id', $user->school_id);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        if ($request->filled('designation') && $request->designation !== 'all') {
            $query->where('designation', $request->designation);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('employee_code', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        $staff = $query->orderBy('name', 'asc')->get();

        // Metrics summary
        $totalStaff = Staff::where('school_id', $user->school_id)->count();
        $activeStaff = Staff::where('school_id', $user->school_id)->where('status', 'active')->count();
        $totalMonthlyPayroll = (float)Staff::where('school_id', $user->school_id)->where('status', 'active')->sum(DB::raw('base_salary + hra + da + allowances'));

        return response()->json([
            'success' => true,
            'summary' => [
                'total_staff' => $totalStaff,
                'active_staff' => $activeStaff,
                'total_monthly_payroll' => $totalMonthlyPayroll,
            ],
            'data' => $staff,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'employee_code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'dob' => 'nullable|date',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'aadhaar_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'photo_url' => 'nullable|string',
            'designation' => 'required|string|max:100',
            'department' => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'status' => 'nullable|in:active,resigned,on_leave',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'upi_id' => 'nullable|string|max:100',
            'base_salary' => 'nullable|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'da' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'pf_deduction' => 'nullable|numeric|min:0',
            'tds_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'vacation_pay_policy' => 'nullable|in:full_pay_12_months,unpaid_vacation,half_pay',
        ]);

        // Auto-generate employee code if missing
        if (empty($validated['employee_code'])) {
            $lastId = Staff::where('school_id', $user->school_id)->max('id') ?? 0;
            $validated['employee_code'] = 'EMP-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        }

        $validated['school_id'] = $user->school_id;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['vacation_pay_policy'] = $validated['vacation_pay_policy'] ?? 'full_pay_12_months';

        $staff = Staff::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Staff profile created successfully.',
            'data' => $staff,
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        $staff = Staff::where('school_id', $user->school_id)
            ->with(['payrolls' => fn($q) => $q->orderBy('year', 'desc')->orderBy('month', 'desc')->limit(6)])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $staff = Staff::where('school_id', $user->school_id)->findOrFail($id);

        $validated = $request->validate([
            'employee_code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|in:male,female,other',
            'dob' => 'nullable|date',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'aadhaar_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'photo_url' => 'nullable|string',
            'designation' => 'sometimes|required|string|max:100',
            'department' => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'status' => 'sometimes|required|in:active,resigned,on_leave',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'upi_id' => 'nullable|string|max:100',
            'base_salary' => 'nullable|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'da' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'pf_deduction' => 'nullable|numeric|min:0',
            'tds_deduction' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'vacation_pay_policy' => 'nullable|in:full_pay_12_months,unpaid_vacation,half_pay',
        ]);

        $staff->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Staff profile updated successfully.',
            'data' => $staff,
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $staff = Staff::where('school_id', $user->school_id)->findOrFail($id);
        
        // Soft deactivate rather than hard delete if payroll exists
        if ($staff->payrolls()->exists()) {
            $staff->update(['status' => 'resigned']);
            return response()->json([
                'success' => true,
                'message' => 'Staff marked as resigned since payroll records exist.',
            ]);
        }

        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff profile deleted successfully.',
        ]);
    }

    public function designations()
    {
        $standard = [
            'Principal',
            'Vice Principal',
            'Headmistress / Headmaster',
            'PGT Teacher',
            'TGT Teacher',
            'PRT Teacher',
            'Pre-Primary / Nursery Teacher',
            'Physical Education / Sports Teacher',
            'Computer / IT Teacher',
            'Music & Arts Teacher',
            'Librarian',
            'Office Superintendent / Clerk',
            'Accountant / Cashier',
            'Lab Assistant',
            'Peon / Office Boy',
            'School Bus Driver',
            'Bus Conductor',
            'Security Guard',
            'Other',
        ];

        $departments = [
            'Teaching',
            'Administration',
            'Accounts & Finance',
            'Support Staff',
            'Transport',
            'Security',
        ];

        return response()->json([
            'success' => true,
            'designations' => $standard,
            'departments' => $departments,
        ]);
    }
}
