<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransportSlab;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransportSlabController extends Controller
{
    public function index()
    {
        $schoolId = Auth::user()->school_id;
        $slabs = TransportSlab::where('school_id', $schoolId)->orderBy('min_km')->get();

        // Auto-seed default KM slabs if empty
        if ($slabs->isEmpty()) {
            TransportSlab::insert([
                ['school_id' => $schoolId, 'name' => '0 - 3 km', 'min_km' => 0, 'max_km' => 3, 'monthly_fee' => 400.00, 'description' => 'Local area transport', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'name' => '3 - 7 km', 'min_km' => 3, 'max_km' => 7, 'monthly_fee' => 750.00, 'description' => 'Mid distance route', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'name' => '7 - 12 km', 'min_km' => 7, 'max_km' => 12, 'monthly_fee' => 1100.00, 'description' => 'Outer city route', 'created_at' => now(), 'updated_at' => now()],
                ['school_id' => $schoolId, 'name' => '12+ km', 'min_km' => 12, 'max_km' => 30, 'monthly_fee' => 1500.00, 'description' => 'Long distance route', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $slabs = TransportSlab::where('school_id', $schoolId)->orderBy('min_km')->get();
        }

        return response()->json($slabs);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'min_km' => 'nullable|numeric|min:0',
            'max_km' => 'nullable|numeric|min:0',
            'monthly_fee' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $slab = TransportSlab::create([
            'school_id' => Auth::user()->school_id,
            'name' => $request->name,
            'min_km' => $request->min_km ?? 0,
            'max_km' => $request->max_km ?? 0,
            'monthly_fee' => $request->monthly_fee,
            'description' => $request->description,
        ]);

        return response()->json($slab, 201);
    }

    public function update($id, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $slab = TransportSlab::where('school_id', $schoolId)->where('id', $id)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'min_km' => 'nullable|numeric|min:0',
            'max_km' => 'nullable|numeric|min:0',
            'monthly_fee' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $slab->update([
            'name' => $request->name,
            'min_km' => $request->min_km ?? $slab->min_km,
            'max_km' => $request->max_km ?? $slab->max_km,
            'monthly_fee' => $request->monthly_fee,
            'description' => $request->description,
        ]);

        return response()->json($slab);
    }

    public function destroy($id)
    {
        $schoolId = Auth::user()->school_id;
        $slab = TransportSlab::where('school_id', $schoolId)->where('id', $id)->firstOrFail();
        $slab->delete();

        return response()->json(['message' => 'Transport slab deleted successfully']);
    }

    public function assignTransport($studentId, Request $request)
    {
        $schoolId = Auth::user()->school_id;
        $request->validate([
            'transport_slab_id' => 'nullable|exists:transport_slabs,id',
        ]);

        DB::table('students')
            ->where('school_id', $schoolId)
            ->where('id', $studentId)
            ->update(['transport_slab_id' => $request->transport_slab_id]);

        return response()->json(['message' => 'Student transport slab updated successfully']);
    }

    public function generateDues(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'class_name' => 'nullable|string',
            'month' => 'nullable|string',
        ]);

        $schoolId = Auth::user()->school_id ?? $request->school_id ?? 1;
        $sessionId = $request->academic_session_id;
        if (!$sessionId) {
            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $schoolId)
                ->where('is_current', 1)
                ->value('id') ?? DB::table('academic_sessions')->where('school_id', $schoolId)->value('id');
        }

        $transportCatId = DB::table('fee_categories')
            ->where('school_id', $schoolId)
            ->where('name', 'LIKE', '%Transport%')
            ->value('id');

        $activeMonths = ['April', 'May', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
        
        $selectedMonth = $request->input('month');
        $targetMonths = [];

        if ($selectedMonth && strtolower($selectedMonth) !== 'all') {
            if (strtolower($selectedMonth) === 'june') {
                $targetMonths = [];
            } else {
                $targetMonths = [ucfirst(strtolower($selectedMonth))];
            }
        } else {
            $targetMonths = $activeMonths;
        }

        // ONLY fetch students who actually have transport_slab_id assigned!
        $query = DB::table('students')
            ->where('school_id', $schoolId)
            ->whereNotNull('transport_slab_id');

        if ($request->class_name && $request->class_name !== 'All Classes') {
            $query->where('class_name', $request->class_name);
        }

        $students = $query->get();

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($students as $student) {
            $slab = DB::table('transport_slabs')->where('id', $student->transport_slab_id)->first();
            if ($slab && $slab->monthly_fee > 0) {
                foreach ($targetMonths as $monthName) {
                    $title = "Transport Fee - {$monthName} ({$slab->name})";
                    $exists = DB::table('fee_invoices')
                        ->where('student_id', $student->id)
                        ->where('academic_session_id', $sessionId)
                        ->where('fee_category_id', $transportCatId)
                        ->where('title', 'LIKE', "Transport Fee - {$monthName}%")
                        ->exists();

                    if (!$exists) {
                        \App\Models\FeeInvoice::create([
                            'school_id' => $schoolId,
                            'student_id' => $student->id,
                            'academic_session_id' => $sessionId,
                            'fee_category_id' => $transportCatId,
                            'title' => $title,
                            'amount' => $slab->monthly_fee,
                            'due_date' => now()->addDays(30),
                        ]);
                        $generatedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }

        if ($generatedCount === 0 && $skippedCount > 0) {
            return response()->json([
                'message' => "All $skippedCount transport dues were already generated. No duplicate dues created."
            ]);
        }

        return response()->json([
            'message' => "Transport dues processing complete: $generatedCount new dues generated for transport-opted students. ($skippedCount already existed)."
        ]);
    }
}
