<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report Card - {{ $student->fullName }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.4; padding: 20px; }
        .border-box { border: 5px double #1d4d87; padding: 30px; position: relative; height: 950px; }
        .header { text-align: center; margin-bottom: 30px; }
        .school-name { font-size: 32px; font-weight: bold; color: #1d4d87; margin: 0; }
        .school-info { font-size: 14px; color: #555; }
        
        .report-title { font-size: 24px; font-weight: bold; text-align: center; background: #1d4d87; color: white; padding: 10px; margin: 20px 0; border-radius: 5px; }
        
        .student-info { margin-bottom: 30px; width: 100%; }
        .student-info td { padding: 8px 0; font-size: 15px; }
        .label { font-weight: bold; color: #444; width: 150px; }
        
        .marks-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .marks-table th { background-color: #f0f4f8; border: 1px solid #1d4d87; padding: 12px; text-align: center; font-size: 14px; }
        .marks-table td { border: 1px solid #ccc; padding: 12px; text-align: center; font-size: 14px; }
        .subject-name { text-align: left !important; font-weight: bold; }
        
        .summary { margin-top: 40px; display: table; width: 100%; }
        .summary-box { display: table-cell; border: 1px solid #1d4d87; padding: 15px; width: 33%; border-radius: 5px; }
        .val { font-size: 22px; font-weight: bold; color: #1d4d87; display: block; margin-top: 5px; }
        
        .grade-chart { margin-top: 40px; font-size: 11px; color: #666; }
        .grade-chart table { border-collapse: collapse; width: 100%; }
        .grade-chart td { border: 1px solid #eee; padding: 5px; text-align: center; }

        .footer { position: absolute; bottom: 50px; width: 90%; }
        .sign-row { width: 100%; margin-top: 50px; }
        .sign-box { width: 33%; float: left; text-align: center; }
        .sign-line { border-top: 1px solid #333; width: 80%; margin: auto; margin-top: 40px; padding-top: 5px; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="border-box">
        <div class="header">
            <h1 class="school-name">SHIVANSH INTERNATIONAL SCHOOL</h1>
            <div class="school-info">Affiliated to CBSE, New Delhi | Session 2025-26</div>
        </div>

        <div class="report-title">ACADEMIC PERFORMANCE REPORT</div>

        <table class="student-info">
            <tr>
                <td class="label">Student Name:</td>
                <td>{{ $student->fullName }}</td>
                <td class="label">Roll No:</td>
                <td>{{ $student->rollNo ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Class/Section:</td>
                <td>{{ $student->classId }}</td>
                <td class="label">Exam Type:</td>
                <td>{{ $exam->name }}</td>
            </tr>
            <tr>
                <td class="label">Parent Name:</td>
                <td>{{ $student->parentName }}</td>
                <td class="label">Attendance:</td>
                <td>94%</td>
            </tr>
        </table>

        <table class="marks-table">
            <thead>
                <tr>
                    <th class="subject-name">Subject</th>
                    <th>Max Marks</th>
                    <th>Min Marks</th>
                    <th>Marks Obtained</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $totalMax = 0; 
                    $totalObtained = 0;
                @endphp
                @foreach($marks as $m)
                    @php 
                        $max = $m->schedule->max_marks ?? 100;
                        $obtained = $m->marks_obtained ?? 0;
                        $totalMax += $max;
                        $totalObtained += $obtained;
                        
                        $percentage = ($obtained / $max) * 100;
                        $grade = 'E';
                        if($percentage >= 90) $grade = 'A1';
                        elseif($percentage >= 80) $grade = 'A2';
                        elseif($percentage >= 70) $grade = 'B1';
                        elseif($percentage >= 60) $grade = 'B2';
                        elseif($percentage >= 50) $grade = 'C1';
                        elseif($percentage >= 40) $grade = 'C2';
                        elseif($percentage >= 33) $grade = 'D';
                    @endphp
                    <tr>
                        <td class="subject-name">{{ $m->schedule->subject->name }}</td>
                        <td>{{ $max }}</td>
                        <td>{{ $m->schedule->min_marks ?? 33 }}</td>
                        <td>{{ $obtained }}</td>
                        <td style="font-weight: bold;">{{ $grade }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-box">
                GRAND TOTAL
                <span class="val">{{ $totalObtained }} / {{ $totalMax }}</span>
            </div>
            <div style="width: 20px; display: table-cell;"></div>
            <div class="summary-box">
                PERCENTAGE
                <span class="val">{{ number_format(($totalObtained / $totalMax) * 100, 2) }}%</span>
            </div>
            <div style="width: 20px; display: table-cell;"></div>
            <div class="summary-box">
                RESULT
                <span class="val" style="color: {{ ($totalObtained/$totalMax) >= 0.33 ? '#28a745' : '#dc3545' }}">
                    {{ ($totalObtained/$totalMax) >= 0.33 ? 'PASS' : 'FAIL' }}
                </span>
            </div>
        </div>

        <div class="footer">
            <div class="sign-row">
                <div class="sign-box">
                    <div class="sign-line">Class Teacher</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line">Coordinator</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line">Principal</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
