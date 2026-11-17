<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class IdCardController extends Controller
{
    /**
     * Get or initialize ID card templates for the authenticated school.
     */
    public function getTemplates(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $templates = DB::table('id_card_templates')
            ->where('school_id', $user->school_id)
            ->orderBy('id', 'asc')
            ->get();

        if ($templates->isEmpty()) {
            $this->seedDefaultTemplates($user->school_id);
            $templates = DB::table('id_card_templates')
                ->where('school_id', $user->school_id)
                ->orderBy('id', 'asc')
                ->get();
        }

        return response()->json($templates);
    }

    private function seedDefaultTemplates($schoolId)
    {
        $now = now();
        $defaults = [
            [
                'school_id' => $schoolId,
                'name' => 'Standard CR80 Vertical (54x86 mm)',
                'orientation' => 'portrait',
                'width_mm' => 54.0,
                'height_mm' => 86.0,
                'primary_color' => '#1B6CA8',
                'accent_color' => '#F39C12',
                'show_blood_group' => true,
                'show_dob' => true,
                'show_father_name' => true,
                'show_contact' => true,
                'show_address' => true,
                'show_qr_code' => true,
                'show_signature' => true,
                'signature_url' => null,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'school_id' => $schoolId,
                'name' => 'Standard CR80 Horizontal (86x54 mm)',
                'orientation' => 'landscape',
                'width_mm' => 86.0,
                'height_mm' => 54.0,
                'primary_color' => '#2E7D32',
                'accent_color' => '#FFB300',
                'show_blood_group' => true,
                'show_dob' => true,
                'show_father_name' => true,
                'show_contact' => true,
                'show_address' => true,
                'show_qr_code' => true,
                'show_signature' => true,
                'signature_url' => null,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'school_id' => $schoolId,
                'name' => 'Extended Lanyard Badge (70x100 mm)',
                'orientation' => 'portrait',
                'width_mm' => 70.0,
                'height_mm' => 100.0,
                'primary_color' => '#4A148C',
                'accent_color' => '#FF4081',
                'show_blood_group' => true,
                'show_dob' => true,
                'show_father_name' => true,
                'show_contact' => true,
                'show_address' => true,
                'show_qr_code' => true,
                'show_signature' => true,
                'signature_url' => null,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('id_card_templates')->insert($defaults);
    }

    /**
     * Create or update an ID card template.
     */
    public function saveTemplate(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:100',
            'orientation' => 'required|string|in:portrait,landscape',
            'width_mm' => 'required|numeric|min:30|max:300',
            'height_mm' => 'required|numeric|min:30|max:300',
            'primary_color' => 'nullable|string|max:20',
            'accent_color' => 'nullable|string|max:20',
            'show_admission_no' => 'nullable|boolean',
            'show_roll_no' => 'nullable|boolean',
            'show_blood_group' => 'nullable|boolean',
            'show_dob' => 'nullable|boolean',
            'show_father_name' => 'nullable|boolean',
            'show_mother_name' => 'nullable|boolean',
            'show_gender' => 'nullable|boolean',
            'show_contact' => 'nullable|boolean',
            'show_address' => 'nullable|boolean',
            'show_student_pen' => 'nullable|boolean',
            'show_apaar_id' => 'nullable|boolean',
            'show_aadhar_no' => 'nullable|boolean',
            'custom_fields' => 'nullable',
            'show_qr_code' => 'nullable|boolean',
            'show_signature' => 'nullable|boolean',
            'signature_url' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $data = [
            'school_id' => $user->school_id,
            'name' => $validated['name'],
            'orientation' => $validated['orientation'],
            'width_mm' => (float)$validated['width_mm'],
            'height_mm' => (float)$validated['height_mm'],
            'primary_color' => $validated['primary_color'] ?? '#1B6CA8',
            'accent_color' => $validated['accent_color'] ?? '#F39C12',
            'show_admission_no' => $request->boolean('show_admission_no', true),
            'show_roll_no' => $request->boolean('show_roll_no', true),
            'show_blood_group' => $request->boolean('show_blood_group', true),
            'show_dob' => $request->boolean('show_dob', true),
            'show_father_name' => $request->boolean('show_father_name', true),
            'show_mother_name' => $request->boolean('show_mother_name', false),
            'show_gender' => $request->boolean('show_gender', false),
            'show_contact' => $request->boolean('show_contact', true),
            'show_address' => $request->boolean('show_address', true),
            'show_student_pen' => $request->boolean('show_student_pen', false),
            'show_apaar_id' => $request->boolean('show_apaar_id', false),
            'show_aadhar_no' => $request->boolean('show_aadhar_no', false),
            'custom_fields' => is_array($request->input('custom_fields')) ? json_encode($request->input('custom_fields')) : $request->input('custom_fields'),
            'show_qr_code' => $request->boolean('show_qr_code', false),
            'show_signature' => $request->boolean('show_signature', false),
            'signature_url' => $validated['signature_url'] ?? null,
            'is_default' => $request->boolean('is_default', false),
            'updated_at' => now(),
        ];

        if ($data['is_default']) {
            DB::table('id_card_templates')
                ->where('school_id', $user->school_id)
                ->update(['is_default' => false]);
        }

        if (!empty($validated['id'])) {
            DB::table('id_card_templates')
                ->where('school_id', $user->school_id)
                ->where('id', $validated['id'])
                ->update($data);
            $template = DB::table('id_card_templates')->where('id', $validated['id'])->first();
        } else {
            $data['created_at'] = now();
            $id = DB::table('id_card_templates')->insertGetId($data);
            $template = DB::table('id_card_templates')->where('id', $id)->first();
        }

        return response()->json($template);
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate($id)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        DB::table('id_card_templates')
            ->where('school_id', $user->school_id)
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Template deleted successfully.']);
    }

    /**
     * Update individual student photo and blood group.
     */
    public function updateStudentPhoto(Request $request, $studentId)
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

        $photoUrl = $request->input('photo_url');
        $bloodGroup = $request->input('blood_group');
        $address = $request->input('address');
        $motherName = $request->input('mother_name');
        $fatherName = $request->input('father_name');
        $gender = $request->input('gender');
        $dob = $request->input('dob');

        // Handle uploaded file if present
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = 'student_' . $studentId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/student_photos/' . $user->school_id, $filename);
            $photoUrl = Storage::url($path);
        }

        $updateData = ['updated_at' => now()];
        if ($photoUrl !== null) {
            $updateData['photo_url'] = $photoUrl;
        }
        if ($bloodGroup !== null) {
            $updateData['blood_group'] = $bloodGroup;
        }
        if ($address !== null) {
            $updateData['address'] = $address;
        }
        if ($motherName !== null) {
            $updateData['mother_name'] = $motherName;
        }
        if ($fatherName !== null) {
            $updateData['father_name'] = $fatherName;
        }
        if ($gender !== null) {
            $updateData['gender'] = $gender;
        }
        if ($dob !== null) {
            $updateData['dob'] = $dob;
        }

        DB::table('students')
            ->where('school_id', $user->school_id)
            ->where('id', $studentId)
            ->update($updateData);

        $updatedStudent = DB::table('students')->where('id', $studentId)->first();
        return response()->json([
            'message' => 'Student photo updated successfully.',
            'student' => $updatedStudent
        ]);
    }

    /**
     * Bulk update photos with conflict resolution.
     * conflict_mode: 'overwrite_all' | 'update_new_only' | 'skip_existing'
     */
    public function bulkUpdatePhotos(Request $request)
    {
        $user = Auth::user();
        if (!$user->school_id) {
            return response()->json(['message' => 'Not associated with a school.'], 403);
        }

        $request->validate([
            'photos' => 'required|array',
            'photos.*.photo' => 'required|string', // Base64 or URL
            'conflict_mode' => 'nullable|string|in:overwrite_all,update_new_only,skip_existing',
        ]);

        $conflictMode = $request->input('conflict_mode', 'overwrite_all');
        $photos = $request->input('photos');

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($photos as $item) {
            $student = null;
            if (!empty($item['student_id'])) {
                $student = DB::table('students')
                    ->where('school_id', $user->school_id)
                    ->where('id', $item['student_id'])
                    ->first();
            } elseif (!empty($item['admission_number'])) {
                $student = DB::table('students')
                    ->where('school_id', $user->school_id)
                    ->where('admission_number', trim($item['admission_number']))
                    ->first();
            } elseif (!empty($item['roll_number']) && !empty($item['class_name'])) {
                $student = DB::table('students')
                    ->where('school_id', $user->school_id)
                    ->where('class_name', trim($item['class_name']))
                    ->where('roll_number', trim($item['roll_number']))
                    ->first();
            }

            if (!$student) {
                $notFound++;
                continue;
            }

            $hasExistingPhoto = !empty($student->photo_url);

            if ($hasExistingPhoto) {
                if ($conflictMode === 'skip_existing') {
                    $skipped++;
                    continue;
                }
                // 'update_new_only' means keep existing photo, only update if student has NO photo!
                if ($conflictMode === 'update_new_only') {
                    $skipped++;
                    continue;
                }
            }

            // overwrite_all or student had no photo
            DB::table('students')
                ->where('id', $student->id)
                ->update([
                    'photo_url' => $item['photo'],
                    'updated_at' => now(),
                ]);
            $updated++;
        }

        return response()->json([
            'message' => "Photos processed: $updated updated, $skipped skipped, $notFound not found.",
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'not_found_count' => $notFound,
        ]);
    }

    /**
     * Show responsive mobile-friendly webpage for snapping/uploading student photo via phone camera.
     */
    public function showMobileUploadPage($studentId)
    {
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student) {
            return response('<h3>Student not found.</h3>', 404);
        }
        $school = DB::table('schools')->where('id', $student->school_id)->first();
        $schoolName = $school ? $school->name : 'School ERP';

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Upload Student Photo</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: #F1F5F9; color: #1E293B; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 16px; }
    .card { background: #FFFFFF; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 400px; padding: 24px; text-align: center; }
    .school-title { font-size: 13px; font-weight: 700; color: #1B6CA8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .student-name { font-size: 20px; font-weight: 800; color: #0F172A; margin-bottom: 4px; }
    .student-meta { font-size: 13px; color: #64748B; margin-bottom: 20px; }
    .photo-preview-box { width: 160px; height: 190px; border-radius: 12px; border: 2px dashed #94A3B8; margin: 0 auto 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; background: #F8FAFC; cursor: pointer; position: relative; }
    .photo-preview-box img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .photo-placeholder { color: #64748B; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; }
    .btn-camera { background: #1B6CA8; color: white; margin-bottom: 10px; }
    .btn-camera:active { background: #144f7c; }
    .btn-submit { background: #10B981; color: white; display: none; }
    .btn-submit:active { background: #059669; }
    .status-msg { margin-top: 14px; font-size: 13px; font-weight: 500; }
    .status-success { color: #059669; }
    .status-error { color: #DC2626; }
  </style>
</head>
<body>
  <div class="card">
    <div class="school-title">' . htmlspecialchars($schoolName) . '</div>
    <div class="student-name">' . htmlspecialchars($student->name) . '</div>
    <div class="student-meta">Class: ' . htmlspecialchars($student->class_name) . ($student->section ? ' (' . htmlspecialchars($student->section) . ')' : '') . ' | Roll: ' . htmlspecialchars($student->roll_number ?? '-') . '</div>
    
    <div class="photo-preview-box" id="previewBox" onclick="document.getElementById(\'cameraInput\').click()">
      <img id="previewImg" alt="Preview" />
      <div class="photo-placeholder" id="placeholder">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#1B6CA8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span style="font-size: 12px; font-weight: 600; color: #1B6CA8;">Tap to Snap Photo</span>
      </div>
    </div>

    <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display:none" onchange="handleFileSelect(event)">

    <button type="button" class="btn btn-camera" onclick="document.getElementById(\'cameraInput\').click()">
      📷 Open Phone Camera
    </button>
    <button type="button" id="submitBtn" class="btn btn-submit" onclick="uploadPhoto()">
      ✓ Save Student Photo
    </button>

    <div id="statusMsg" class="status-msg"></div>
  </div>

  <script>
    let selectedBase64 = null;

    function handleFileSelect(event) {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(e) {
        selectedBase64 = e.target.result;
        const img = document.getElementById("previewImg");
        img.src = selectedBase64;
        img.style.display = "block";
        document.getElementById("placeholder").style.display = "none";
        document.getElementById("submitBtn").style.display = "block";
        document.getElementById("statusMsg").innerText = "";
      };
      reader.readAsDataURL(file);
    }

    async function uploadPhoto() {
      if (!selectedBase64) return;
      const btn = document.getElementById("submitBtn");
      const msg = document.getElementById("statusMsg");
      btn.disabled = true;
      btn.innerText = "Uploading...";
      try {
        const response = await fetch("/api/public/students/' . $studentId . '/photo-upload", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ photo_url: selectedBase64 })
        });
        const res = await response.json();
        if (response.ok) {
          msg.className = "status-msg status-success";
          msg.innerHTML = "🎉 Photo uploaded successfully!<br>You can now close this tab.";
          btn.style.display = "none";
        } else {
          msg.className = "status-msg status-error";
          msg.innerText = res.message || "Upload failed. Please try again.";
          btn.disabled = false;
          btn.innerText = "✓ Save Student Photo";
        }
      } catch (err) {
        msg.className = "status-msg status-error";
        msg.innerText = "Connection error. Please try again.";
        btn.disabled = false;
        btn.innerText = "✓ Save Student Photo";
      }
    }
  </script>
</body>
</html>';

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Handle public mobile photo upload without requiring login session on phone.
     */
    public function handleMobileUpload(Request $request, $studentId)
    {
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $photoUrl = $request->input('photo_url');
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = 'student_' . $studentId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/student_photos/' . $student->school_id, $filename);
            $photoUrl = Storage::url($path);
        }

        if (empty($photoUrl)) {
            return response()->json(['message' => 'No photo provided.'], 422);
        }

        DB::table('students')
            ->where('id', $studentId)
            ->update([
                'photo_url' => $photoUrl,
                'updated_at' => now(),
            ]);

        $updated = DB::table('students')->where('id', $studentId)->first();
        return response()->json([
            'message' => 'Student photo uploaded successfully.',
            'photo_url' => $updated->photo_url,
            'student' => $updated,
        ]);
    }

    /**
     * Public endpoint to check current student photo status
     */
    public function getStudentPhotoStatus($studentId)
    {
        $student = DB::table('students')->where('id', $studentId)->first(['id', 'photo_url', 'blood_group', 'updated_at']);
        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        return response()->json($student);
    }
}

