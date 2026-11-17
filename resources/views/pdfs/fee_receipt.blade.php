<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Receipt - {{ $payment->receipt_no }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.3; margin: 0; padding: 10px; }
        .container { max-width: 800px; margin: auto; }
        .receipt-section { border: 1.5px solid #1d4d87; padding: 20px; border-radius: 10px; background-color: #fff; position: relative; }
        .header { text-align: center; border-bottom: 1.5px solid #1d4d87; padding-bottom: 10px; margin-bottom: 15px; }
        .school-name { font-size: 22px; font-weight: bold; color: #1d4d87; margin: 0; }
        .school-info { font-size: 11px; color: #666; margin-top: 3px; }
        .copy-tag { position: absolute; top: 15px; left: 15px; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #fff; background-color: #1d4d87; padding: 4px 8px; border-radius: 4px; }
        .receipt-title { font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 0; color: #444; }
        
        .info-grid { display: table; width: 100%; margin-bottom: 15px; margin-top: 15px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 4px 0; font-size: 13px; }
        .label { font-weight: bold; color: #555; width: 110px; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px; text-align: left; font-size: 13px; }
        .table td { border: 1px solid #dee2e6; padding: 8px; font-size: 13px; }
        
        .total-box { margin-top: 15px; text-align: right; float: right; width: 50%; }
        .total-row { font-size: 16px; font-weight: bold; color: #1d4d87; }
        
        .signature { margin-top: 25px; text-align: right; float: right; width: 40%; }
        .sig-line { border-top: 1px solid #333; width: 150px; display: inline-block; margin-top: 25px; }
        
        .footer { margin-top: 20px; font-size: 10px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 10px; clear: both; }
        .divider-line { border-top: 1.5px dashed #666; text-align: center; margin: 25px 0; height: 1px; line-height: 0.1em; }
    </style>
</head>
<body>
    <div class="container">
        <!-- ==================== PARENT COPY ==================== -->
        <div class="receipt-section">
            <span class="copy-tag">Parent Copy</span>
            <div class="header">
                <h1 class="school-name">{{ strtoupper($school?->name ?? 'SHIVANSH INTERNATIONAL SCHOOL') }}</h1>
                <div class="school-info">
                    {{ $school?->address ?? '123 Knowledge Park, Education Hub, New Delhi - 110001' }}<br>
                    Phone: {{ $school?->phone ?? '+91 9876543210' }} | Email: {{ $school?->email ?? 'contact@shivanshschool.edu.in' }}
                </div>
            </div>

            <div style="text-align: center; margin-top: 10px; margin-bottom: 10px;">
                <div class="receipt-title">Fee Receipt</div>
                <div style="font-size: 13px; margin-top: 4px; color: #555;">
                    Receipt No: <b>{{ $payment->receipt_no }}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 
                    Academic Year: <b>{{ $payment->academicYear?->year }}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 
                    Date: <b>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</b>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-row">
                    <div class="info-cell label">Student Name:</div>
                    <div class="info-cell">{{ $payment->student->first_name }} {{ $payment->student->last_name }}</div>
                    <div class="info-cell label">Admission No:</div>
                    <div class="info-cell">{{ $payment->student->admission_no ?: $payment->student->student_id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-cell label">Class/Section:</div>
                    <div class="info-cell">
                        {{ $payment->student->academicRecords->first()?->schoolClass?->name ?? 'N/A' }}{{ $payment->student->academicRecords->first()?->section ? ' - ' . $payment->student->academicRecords->first()->section : '' }}
                    </div>
                    <div class="info-cell label">Parent Name:</div>
                    <div class="info-cell">{{ $payment->student->father_name }}</div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align: right; width: 150px;">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $payment->remarks ?: ($payment->feeStructure->feeCategory->name . ' Fee') }}</td>
                        <td style="text-align: right;">{{ number_format($payment->amount_paid, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div style="width: 100%; overflow: hidden; margin-top: 10px;">
                <div style="float: left; width: 50%; font-size: 12px; margin-top: 15px;">
                    <div>Payment Method: <b>{{ strtoupper($payment->payment_method) }}</b></div>
                </div>
                <div style="float: right; width: 50%; text-align: right;">
                    <div class="total-row">Total Paid: ₹{{ number_format($payment->amount_paid, 2) }}</div>
                    <div class="signature">
                        <div class="sig-line"></div>
                        <div style="font-size: 11px; margin-top: 3px;">Authorized Signatory</div>
                    </div>
                </div>
            </div>

            <div class="footer">
                This is a computer-generated document and does not require a physical signature.
            </div>
        </div>

        <!-- ==================== CUT LINE ==================== -->
        <div class="divider-line">
            <span style="background: #fff; padding: 0 15px; font-size: 10px; color: #666; font-family: 'Helvetica'; font-weight: bold; letter-spacing: 2px;">✂ CUT HERE ✂</span>
        </div>

        <!-- ==================== OFFICE COPY ==================== -->
        <div class="receipt-section">
            <span class="copy-tag">Office Copy</span>
            <div class="header">
                <h1 class="school-name">{{ strtoupper($school?->name ?? 'SHIVANSH INTERNATIONAL SCHOOL') }}</h1>
                <div class="school-info">
                    {{ $school?->address ?? '123 Knowledge Park, Education Hub, New Delhi - 110001' }}<br>
                    Phone: {{ $school?->phone ?? '+91 9876543210' }} | Email: {{ $school?->email ?? 'contact@shivanshschool.edu.in' }}
                </div>
            </div>

            <div style="text-align: center; margin-top: 10px; margin-bottom: 10px;">
                <div class="receipt-title">Fee Receipt</div>
                <div style="font-size: 13px; margin-top: 4px; color: #555;">
                    Receipt No: <b>{{ $payment->receipt_no }}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 
                    Academic Year: <b>{{ $payment->academicYear?->year }}</b> &nbsp;&nbsp;|&nbsp;&nbsp; 
                    Date: <b>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</b>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-row">
                    <div class="info-cell label">Student Name:</div>
                    <div class="info-cell">{{ $payment->student->first_name }} {{ $payment->student->last_name }}</div>
                    <div class="info-cell label">Admission No:</div>
                    <div class="info-cell">{{ $payment->student->admission_no ?: $payment->student->student_id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-cell label">Class/Section:</div>
                    <div class="info-cell">
                        {{ $payment->student->academicRecords->first()?->schoolClass?->name ?? 'N/A' }}{{ $payment->student->academicRecords->first()?->section ? ' - ' . $payment->student->academicRecords->first()->section : '' }}
                    </div>
                    <div class="info-cell label">Parent Name:</div>
                    <div class="info-cell">{{ $payment->student->father_name }}</div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align: right; width: 150px;">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $payment->remarks ?: ($payment->feeStructure->feeCategory->name . ' Fee') }}</td>
                        <td style="text-align: right;">{{ number_format($payment->amount_paid, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div style="width: 100%; overflow: hidden; margin-top: 10px;">
                <div style="float: left; width: 50%; font-size: 12px; margin-top: 15px;">
                    <div>Payment Method: <b>{{ strtoupper($payment->payment_method) }}</b></div>
                </div>
                <div style="float: right; width: 50%; text-align: right;">
                    <div class="total-row">Total Paid: ₹{{ number_format($payment->amount_paid, 2) }}</div>
                    <div class="signature">
                        <div class="sig-line"></div>
                        <div style="font-size: 11px; margin-top: 3px;">Authorized Signatory</div>
                    </div>
                </div>
            </div>

            <div class="footer">
                This is a computer-generated document and does not require a physical signature.
            </div>
        </div>
    </div>
</body>
</html>
