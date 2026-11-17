<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolHolidayController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = SchoolHoliday::where('school_id', $user->school_id);

        if ($request->filled('session_id') && $request->session_id !== 'all') {
            $sessId = (int)$request->session_id;
            $query->where(function ($q) use ($sessId) {
                $q->where('academic_session_id', $sessId)
                  ->orWhereNull('academic_session_id');
            });
        }

        if ($request->filled('year')) {
            $year = (int)$request->year;
            $query->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                  ->orWhereYear('end_date', $year);
            });
        }

        $holidays = $query->orderBy('start_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $holidays,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:festival_holiday,summer_vacation,winter_break,gazetted_holiday,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'academic_session_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $holiday = SchoolHoliday::create([
            'school_id' => $user->school_id,
            'academic_session_id' => $validated['academic_session_id'] ?? null,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Holiday recorded successfully.',
            'data' => $holiday,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $holiday = SchoolHoliday::where('school_id', $user->school_id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:festival_holiday,summer_vacation,winter_break,gazetted_holiday,other',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'academic_session_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $holiday->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully.',
            'data' => $holiday,
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $holiday = SchoolHoliday::where('school_id', $user->school_id)->findOrFail($id);
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday removed successfully.',
        ]);
    }

    /**
     * Standard Indian school holidays presets for quick 1-click addition
     */
    public function presets(Request $request)
    {
        $year = (int)($request->query('year') ?? date('Y'));

        $presets = [
            ['title' => 'Republic Day', 'type' => 'gazetted_holiday', 'start_date' => "$year-01-26", 'end_date' => "$year-01-26"],
            ['title' => 'Maha Shivratri', 'type' => 'festival_holiday', 'start_date' => "$year-03-08", 'end_date' => "$year-03-08"],
            ['title' => 'Holi & Dhulandi', 'type' => 'festival_holiday', 'start_date' => "$year-03-25", 'end_date' => "$year-03-26"],
            ['title' => 'Eid-ul-Fitr', 'type' => 'festival_holiday', 'start_date' => "$year-04-11", 'end_date' => "$year-04-11"],
            ['title' => 'Summer Vacation', 'type' => 'summer_vacation', 'start_date' => "$year-05-16", 'end_date' => "$year-06-23"],
            ['title' => 'Independence Day', 'type' => 'gazetted_holiday', 'start_date' => "$year-08-15", 'end_date' => "$year-08-15"],
            ['title' => 'Raksha Bandhan', 'type' => 'festival_holiday', 'start_date' => "$year-08-19", 'end_date' => "$year-08-19"],
            ['title' => 'Janmashtami', 'type' => 'festival_holiday', 'start_date' => "$year-08-26", 'end_date' => "$year-08-26"],
            ['title' => 'Gandhi Jayanti', 'type' => 'gazetted_holiday', 'start_date' => "$year-10-02", 'end_date' => "$year-10-02"],
            ['title' => 'Dussehra Break', 'type' => 'festival_holiday', 'start_date' => "$year-10-12", 'end_date' => "$year-10-13"],
            ['title' => 'Diwali Holidays', 'type' => 'festival_holiday', 'start_date' => "$year-10-31", 'end_date' => "$year-11-04"],
            ['title' => 'Guru Nanak Jayanti', 'type' => 'festival_holiday', 'start_date' => "$year-11-15", 'end_date' => "$year-11-15"],
            ['title' => 'Christmas Break', 'type' => 'festival_holiday', 'start_date' => "$year-12-25", 'end_date' => "$year-12-25"],
            ['title' => 'Winter Vacation', 'type' => 'winter_break', 'start_date' => "$year-12-25", 'end_date' => ((int)$year + 1) . "-01-05"],
        ];

        return response()->json([
            'success' => true,
            'data' => $presets,
        ]);
    }
}
