<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\School; // Need to create this model

class SchoolController extends Controller
{
    public function index()
    {
        // Only SuperAdmin can see all schools (we should enforce this via middleware later)
        $schools = DB::table('schools')->orderBy('created_at', 'desc')->get();
        return response()->json($schools);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'board_type' => 'required|string',
            'udise_code' => 'nullable|string|unique:schools',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:6',
        ]);

        // Wrap in a transaction to ensure both or neither are created
        DB::beginTransaction();
        try {
            $schoolId = DB::table('schools')->insertGetId([
                'name' => $validated['name'],
                'board_type' => $validated['board_type'],
                'udise_code' => $validated['udise_code'],
                'enabled_modules' => json_encode(['admissions', 'fees', 'attendance']), // default modules
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create the initial School Admin
            DB::table('users')->insert([
                'name' => 'School Admin',
                'email' => $validated['admin_email'],
                'password' => bcrypt($validated['admin_password']),
                'role' => 'school_admin',
                'school_id' => $schoolId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $school = DB::table('schools')->where('id', $schoolId)->first();
            return response()->json([
                'school' => $school,
                'admin_email' => $validated['admin_email']
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to register school: ' . $e->getMessage()], 500);
        }
    }

    public function update($id, Request $request)
    {
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && (int)$user->school_id !== (int)$id) {
            return response()->json(['message' => 'Unauthorized to update this school.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'board_type' => 'nullable|string',
            'udise_code' => 'nullable|string',
            'registration_no' => 'nullable|string',
            'contact_email' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'address' => 'nullable|string',
            'logo_url' => 'nullable|string',
        ]);

        if (!empty($validated['logo_url'])) {
            $validated['logo_url'] = self::optimizeLogo($validated['logo_url']);
        }

        $school = School::findOrFail($id);
        $school->update($validated);

        return response()->json($school);
    }

    public function uploadLogo($id, Request $request)
    {
        $user = auth()->user();
        if ($user && $user->role !== 'super_admin' && (int)$user->school_id !== (int)$id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $school = School::findOrFail($id);

        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => 'required|image|max:10240',
            ]);
            $file = $request->file('logo');
            $mime = $file->getMimeType();
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $dataUrl = self::optimizeLogo("data:{$mime};base64,{$base64}");
            $school->update(['logo_url' => $dataUrl]);
            return response()->json(['logo_url' => $dataUrl, 'school' => $school]);
        } elseif ($request->has('logo_url')) {
            $dataUrl = self::optimizeLogo($request->logo_url);
            $school->update(['logo_url' => $dataUrl]);
            return response()->json(['logo_url' => $dataUrl, 'school' => $school]);
        }

        return response()->json(['message' => 'No image or logo_url provided.'], 400);
    }

    public static function optimizeLogo($logoUrl)
    {
        if (empty($logoUrl)) return null;
        if (!str_starts_with($logoUrl, 'data:image')) return $logoUrl;

        try {
            $commaPos = strpos($logoUrl, ',');
            if ($commaPos === false) return $logoUrl;
            $raw = base64_decode(substr($logoUrl, $commaPos + 1));
            if (!$raw) return $logoUrl;

            $src = @imagecreatefromstring($raw);
            if (!$src) return $logoUrl;

            $w = imagesx($src);
            $h = imagesy($src);
            $maxDim = 280;

            if ($w <= $maxDim && $h <= $maxDim) {
                imagedestroy($src);
                return $logoUrl;
            }

            if ($w > $h) {
                $newW = $maxDim;
                $newH = (int)($h * ($maxDim / $w));
            } else {
                $newH = $maxDim;
                $newW = (int)($w * ($maxDim / $h));
            }

            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            ob_start();
            imagepng($dst, null, 8);
            $optimizedBytes = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return 'data:image/png;base64,' . base64_encode($optimizedBytes);
        } catch (\Exception $e) {
            return $logoUrl;
        }
    }
}
