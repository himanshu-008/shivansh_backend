<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Fee Ledger - {{ $student->first_name }} {{ $student->last_name }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.5; }
        .container { padding: 30px; border: 1px solid #eee; max-width: 800px; margin: auto; }
        .header { text-align: center; border-bottom: 2px solid #1d4d87; padding-bottom: 20px; margin-bottom: 25px; }
        .school-name { font-size: 28px; font-weight: bold; color: #1d4d87; margin: 0; }
        .school-info { font-size: 12px; color: #666; margin-top: 5px; }
        .ledger-title { font-size: 20px; font-weight: bold; text-transform: uppercase; margin: 15px 0; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        
        .info-grid { display: table; width: 100%; margin-bottom: 25px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 5px 0; font-size: 13px; }
        .label { font-weight: bold; color: #555; width: 120px; }
        
        .section-header { font-size: 15px; font-weight: bold; color: #1d4d87; margin-top: 25px; margin-bottom: 10px; text-transform: uppercase; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; font-size: 13px; color: #444; }
        .table td { border: 1px solid #dee2e6; padding: 8px 10px; font-size: 13px; }
        
        .summary-box { margin-top: 25px; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; }
        .summary-row { display: table; width: 100%; margin-bottom: 5px; }
        .summary-cell { display: table-cell; font-size: 14px; }
        .summary-cell.val { text-align: right; font-weight: bold; }
        .summary-cell.val.net { font-size: 16px; color: #c0392b; }
        .summary-cell.val.paid { color: #27ae60; }
        
        .footer { margin-top: 40px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="school-name">{{ strtoupper($school?->name ?? 'SHIVANSH SCHOOL') }}</h1>
            <div class="school-info">
                {{ $school?->address ?? 'Jaipur, Rajasthan' }}<br>
                Phone: {{ $school?->phone ?? '8740021756' }} | Email: {{ $school?->email ?? 'contact@shivanshschool.in' }}
            </div>
        </div>

        <div class="ledger-title">Student Fee Ledger</div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell label">Student Name:</div>
                <div class="info-cell"><b>{{ $student->first_name }} {{ $student->last_name }}</b></div>
                <div class="info-cell label">Student ID:</div>
                <div class="info-cell">{{ $student->student_id }}</div>
            </div>
            <div class="info-row">
                <div class="info-cell label">Class / Section:</div>
                <div class="info-cell">
                    {{ $student->academicRecords->first()?->schoolClass?->name ?? '--' }}
                    @if($student->academicRecords->first()?->section)
                        - {{ $student->academicRecords->first()->section }}
                    @endif
                </div>
                <div class="info-cell label">Admission No:</div>
                <div class="info-cell">{{ $student->admission_no }}</div>
            </div>
            <div class="info-row">
                <div class="info-cell label">Parent Name:</div>
                <div class="info-cell">{{ $student->father_name ?? $student->mother_name }}</div>
                <div class="info-cell label">Primary Contact:</div>
                <div class="info-cell">{{ $student->parent_primary_contact }}</div>
            </div>
        </div>

        <div class="section-header">1. Fee Structure (Configured Class Dues)</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Fee Category</th>
                    <th>Billing Cycle</th>
                    <th style="text-align: right;">Installment Amount (INR)</th>
                    <th style="text-align: right;">Annual Total (INR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($structures as $s)
                <tr>
                    <td>{{ $s->feeCategory?->name ?? 'Class Fee' }}</td>
                    <td style="text-transform: capitalize;">{{ $s->frequency }}</td>
                    <td style="text-align: right;">{{ number_format($s->amount, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($s->annual_amount, 2) }}</td>
                </tr>
                @empty
                @endforelse
                
                @if($transportFare > 0)
                <tr>
                    <td>Transport Fee (Bus Routing)</td>
                    <td>Monthly</td>
                    <td style="text-align: right;">{{ number_format($transportFare, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($transportFare * 12, 2) }}</td>
                </tr>
                @endif

                @if(count($structures) === 0 && $transportFare <= 0)
                <tr>
                    <td colspan="4" style="text-align: center; color: #888;">No class fees or transport services configured.</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="section-header">2. Payments Ledger History</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Payment Date</th>
                    <th>Fee Category</th>
                    <th>Method</th>
                    <th style="text-align: right;">Amount Paid (INR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td><b>{{ $p->receipt_no }}</b></td>
                    <td>{{ \Carbon\Carbon::parse($p->payment_date)->format('d-M-Y') }}</td>
                    <td>{{ $p->feeStructure?->feeCategory?->name ?? 'Class Fee' }}</td>
                    <td style="text-transform: uppercase;">{{ $p->payment_method }}</td>
                    <td style="text-align: right;">{{ number_format($p->amount_paid, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #888;">No payments recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row">
                <div class="summary-cell">Total Class Fee Dues:</div>
                <div class="summary-cell val">₹{{ number_format($totalDues, 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell">Total Fees Paid:</div>
                <div class="summary-cell val paid">₹{{ number_format($totalPaid, 2) }}</div>
            </div>
            <div class="summary-row" style="border-top: 1px dashed #ccc; padding-top: 8px; margin-top: 8px;">
                <div class="summary-cell" style="font-weight: bold;">Outstanding Dues:</div>
                <div class="summary-cell val net">₹{{ number_format($remainingDue, 2) }}</div>
            </div>
        </div>

        <div class="footer">
            This ledger shows the official fee details and payment history logs for the current academic session.
            <br>
            <i>Generated on: {{ date('d-M-Y H:i:s') }}</i>
        </div>
    </div>
</body>
</html>
