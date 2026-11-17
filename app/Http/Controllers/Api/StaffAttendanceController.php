<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolHoliday;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffAttendanceController extends Controller
{
    /**
     * Get attendance status for a single day across all active staff
     */
    public function daily(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $dateStr = $request->query('date', Carbon::today()->toDateString());
        $date = Carbon::parse($dateStr);
        $schoolId = $user->school_id;

        // Check if date is Sunday or Holiday / Vacation
        $isSunday = $date->isSunday();
        $holiday = SchoolHoliday::where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->first();

        $defaultStatus = 'present';
        $dayType = 'working_day';
        $dayLabel = 'Normal Working Day';

        if ($holiday) {
            $dayType = $holiday->type; // festival_holiday, summer_vacation, winter_break, etc.
            $dayLabel = $holiday->title;
            $defaultStatus = in_array($holiday->type, ['summer_vacation', 'winter_break']) ? 'vacation' : 'holiday';
        } elseif ($isSunday) {
            $dayType = 'sunday';
            $dayLabel = 'Weekly Off (Sunday)';
            $defaultStatus = 'holiday';
        }

        $staffList = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();

        $existing = StaffAttendance::where('school_id', $schoolId)
            ->whereDate('date', $dateStr)
            ->get()
            ->keyBy('staff_id');

        $result = [];
        $summary = [
            'total' => $staffList->count(),
            'present' => 0,
            'absent' => 0,
            'half_day' => 0,
            'paid_leave' => 0,
            'holiday' => 0,
            'vacation' => 0,
        ];

        foreach ($staffList as $st) {
            $rec = $existing->get($st->id);
            $status = $rec ? $rec->status : $defaultStatus;
            $remarks = $rec ? $rec->remarks : null;

            if (isset($summary[$status])) {
                $summary[$status]++;
            }

            $result[] = [
                'staff_id' => $st->id,
                'employee_code' => $st->employee_code,
                'name' => $st->name,
                'designation' => $st->designation,
                'department' => $st->department,
                'status' => $status,
                'remarks' => $remarks,
            ];
        }

        return response()->json([
            'success' => true,
            'date' => $dateStr,
            'day_name' => $date->format('l'),
            'day_type' => $dayType,
            'day_label' => $dayLabel,
            'is_holiday_or_sunday' => ($isSunday || $holiday != null),
            'summary' => $summary,
            'data' => $result,
        ]);
    }

    /**
     * Save/upsert staff attendance for a specific date
     */
    public function storeDaily(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'session_id' => 'nullable|integer',
            'attendances' => 'required|array',
            'attendances.*.staff_id' => 'required|integer|exists:staff,id',
            'attendances.*.status' => 'required|in:present,absent,half_day,paid_leave,holiday,vacation',
            'attendances.*.remarks' => 'nullable|string',
        ]);

        $schoolId = $user->school_id;
        $dateStr = $validated['date'];
        $sessionId = $validated['session_id'] ?? null;

        foreach ($validated['attendances'] as $att) {
            StaffAttendance::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'staff_id' => $att['staff_id'],
                    'date' => $dateStr,
                ],
                [
                    'academic_session_id' => $sessionId,
                    'status' => $att['status'],
                    'remarks' => $att['remarks'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff attendance saved successfully.',
        ]);
    }

    /**
     * Monthly staff attendance register matrix
     */
    public function monthly(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $month = (int)($request->query('month') ?? Carbon::today()->month);
        $year = (int)($request->query('year') ?? Carbon::today()->year);
        $schoolId = $user->school_id;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $totalDays = $startDate->daysInMonth;

        $staffList = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();

        $attendances = StaffAttendance::where('school_id', $schoolId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('staff_id');

        $holidays = SchoolHoliday::where('school_id', $schoolId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->get();

        $matrix = [];
        foreach ($staffList as $st) {
            $stAtt = $attendances->get($st->id) ? $attendances->get($st->id)->keyBy(fn($item) => $item->date->format('Y-m-d')) : collect();

            $dayStats = [];
            $counts = ['P' => 0, 'A' => 0, 'HD' => 0, 'PL' => 0, 'H' => 0, 'V' => 0];

            for ($d = 1; $d <= $totalDays; $d++) {
                $curDate = Carbon::create($year, $month, $d);
                $dStr = $curDate->toDateString();

                $isSunday = $curDate->isSunday();
                $matchedHol = $holidays->first(fn($h) => $dStr >= $h->start_date->toDateString() && $dStr <= $h->end_date->toDateString());

                $status = 'present';
                if ($stAtt->has($dStr)) {
                    $status = $stAtt->get($dStr)->status;
                } elseif ($matchedHol) {
                    $status = in_array($matchedHol->type, ['summer_vacation', 'winter_break']) ? 'vacation' : 'holiday';
                } elseif ($isSunday) {
                    $status = 'holiday';
                }

                $code = match ($status) {
                    'present' => 'P',
                    'absent' => 'A',
                    'half_day' => 'HD',
                    'paid_leave' => 'PL',
                    'holiday' => 'H',
                    'vacation' => 'V',
                    default => 'P',
                };

                $dayStats[$d] = $code;
                $counts[$code]++;
            }

            $matrix[] = [
                'staff_id' => $st->id,
                'employee_code' => $st->employee_code,
                'name' => $st->name,
                'designation' => $st->designation,
                'counts' => $counts,
                'days' => $dayStats,
            ];
        }

        return response()->json([
            'success' => true,
            'month' => $month,
            'year' => $year,
            'total_days' => $totalDays,
            'data' => $matrix,
        ]);
    }
}
