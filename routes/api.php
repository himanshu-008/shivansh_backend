<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\UdiseController;
use App\Http\Controllers\Api\StudentController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('school');
    });

    Route::get('/dashboard/overview', [\App\Http\Controllers\Api\DashboardController::class, 'overview']);

    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store']);
    Route::put('/schools/{id}', [SchoolController::class, 'update']);
    Route::post('/schools/{id}/logo', [SchoolController::class, 'uploadLogo']);
    Route::get('/udise/fetch/{code}', [UdiseController::class, 'fetch']);
    
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/export', [StudentController::class, 'export']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::put('/students/{id}', [StudentController::class, 'update']);
    Route::delete('/students/{id}', [StudentController::class, 'destroy']);
    Route::post('/students/bulk-import', [StudentController::class, 'bulkImport']);
    Route::post('/students/bulk-assign-roll-numbers', [StudentController::class, 'bulkAssignRollNumbers']);
    Route::post('/students/update-roll-numbers', [StudentController::class, 'updateRollNumbers']);
    Route::post('/students/{id}/mark-struck-off', [StudentController::class, 'markStruckOff']);
    Route::post('/students/{id}/reactivate', [StudentController::class, 'reactivate']);

    Route::get('/lookups', [\App\Http\Controllers\Api\LookupController::class, 'index']);
    Route::post('/lookups', [\App\Http\Controllers\Api\LookupController::class, 'store']);

    Route::get('/academic-sessions', [\App\Http\Controllers\Api\AcademicSessionController::class, 'index']);
    Route::post('/academic-sessions', [\App\Http\Controllers\Api\AcademicSessionController::class, 'store']);
    Route::put('/academic-sessions/{id}/set-current', [\App\Http\Controllers\Api\AcademicSessionController::class, 'setCurrent']);

    Route::post('/students/bulk-promote', [StudentController::class, 'bulkPromote']);
    Route::get('/students/{studentId}/check-dues', [\App\Http\Controllers\Api\TransferCertificateController::class, 'checkStudentDues']);
    Route::get('/transfer-certificates', [\App\Http\Controllers\Api\TransferCertificateController::class, 'index']);
    Route::post('/transfer-certificates', [\App\Http\Controllers\Api\TransferCertificateController::class, 'store']);
    Route::get('/transfer-certificates/{id}/download', [\App\Http\Controllers\Api\TransferCertificateController::class, 'downloadRajPspFile']);

    Route::apiResource('fee-categories', \App\Http\Controllers\Api\FeeCategoryController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('fee-structures', \App\Http\Controllers\Api\FeeStructureController::class)->only(['index', 'store', 'destroy']);

    Route::get('/fee-ledger', [\App\Http\Controllers\Api\FeePaymentController::class, 'ledger']);
    Route::get('/fee-payments', [\App\Http\Controllers\Api\FeePaymentController::class, 'index']);
    Route::post('/fee-payments', [\App\Http\Controllers\Api\FeePaymentController::class, 'store']);
    Route::post('/fee-dues/generate', [\App\Http\Controllers\Api\FeePaymentController::class, 'generateDues']);
    Route::get('/fee-history/{studentId}', [\App\Http\Controllers\Api\FeePaymentController::class, 'studentHistory']);
    Route::apiResource('school-classes', \App\Http\Controllers\Api\SchoolClassController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('transport-slabs', \App\Http\Controllers\Api\TransportSlabController::class);
    Route::post('/students/{studentId}/assign-transport', [\App\Http\Controllers\Api\TransportSlabController::class, 'assignTransport']);
    Route::post('/transport-dues/generate', [\App\Http\Controllers\Api\TransportSlabController::class, 'generateDues']);

    Route::apiResource('book-sets', \App\Http\Controllers\Api\BookSetController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/students/{studentId}/assign-book-set', [\App\Http\Controllers\Api\BookSetController::class, 'assignBookSet']);
    Route::post('/students/bulk-assign-book-set', [\App\Http\Controllers\Api\BookSetController::class, 'bulkAssignBookSet']);
    Route::post('/book-dues/generate', [\App\Http\Controllers\Api\BookSetController::class, 'generateDues']);

    Route::apiResource('dress-items', \App\Http\Controllers\Api\DressItemController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/students/{studentId}/assign-dress', [\App\Http\Controllers\Api\DressItemController::class, 'assignDress']);
    Route::post('/students/bulk-assign-dress', [\App\Http\Controllers\Api\DressItemController::class, 'bulkAssignDress']);
    Route::post('/students/issue-dress-items', [\App\Http\Controllers\Api\DressItemController::class, 'issueCustomItems']);
    Route::post('/dress-dues/generate', [\App\Http\Controllers\Api\DressItemController::class, 'generateDues']);

    // Inventory Management Routes (Books & Dress)
    Route::get('/inventory/overview', [\App\Http\Controllers\Api\InventoryController::class, 'overview']);
    Route::get('/inventory/items', [\App\Http\Controllers\Api\InventoryController::class, 'items']);
    Route::post('/inventory/adjust', [\App\Http\Controllers\Api\InventoryController::class, 'adjust']);
    Route::get('/inventory/transactions', [\App\Http\Controllers\Api\InventoryController::class, 'transactions']);

    // Academics & Attendance Routes
    Route::get('/attendances', [\App\Http\Controllers\Api\AttendanceController::class, 'index']);
    Route::post('/attendances/bulk-mark', [\App\Http\Controllers\Api\AttendanceController::class, 'bulkStore']);
    Route::get('/attendances/summary', [\App\Http\Controllers\Api\AttendanceController::class, 'summary']);
    Route::get('/attendances/monthly', [\App\Http\Controllers\Api\AttendanceController::class, 'monthlyIndex']);
    Route::post('/attendances/monthly/bulk-save', [\App\Http\Controllers\Api\AttendanceController::class, 'monthlyBulkStore']);

    Route::apiResource('subjects', \App\Http\Controllers\Api\SubjectController::class)->only(['index', 'store', 'destroy']);

    Route::get('/exam-terms', [\App\Http\Controllers\Api\ExamController::class, 'indexTerms']);
    Route::post('/exam-terms', [\App\Http\Controllers\Api\ExamController::class, 'storeTerm']);
    Route::delete('/exam-terms/{id}', [\App\Http\Controllers\Api\ExamController::class, 'destroyTerm']);
    Route::get('/exam-term-subjects', [\App\Http\Controllers\Api\ExamController::class, 'indexTermSubjects']);
    Route::post('/exam-term-subjects/bulk-store', [\App\Http\Controllers\Api\ExamController::class, 'storeTermSubjects']);
    Route::get('/exam-marks', [\App\Http\Controllers\Api\ExamController::class, 'getMarks']);
    Route::post('/exam-marks/bulk-store', [\App\Http\Controllers\Api\ExamController::class, 'bulkStoreMarks']);
    Route::post('/exam-marks/aggregate', [\App\Http\Controllers\Api\ExamController::class, 'getAggregateMarks']);

    // Admission Enquiries Routes
    Route::get('/admission-enquiries', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'index']);
    Route::post('/admission-enquiries', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'store']);
    Route::get('/admission-enquiries/{id}', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'show']);
    Route::put('/admission-enquiries/{id}', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'update']);
    Route::delete('/admission-enquiries/{id}', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'destroy']);
    Route::post('/admission-enquiries/{id}/convert', [\App\Http\Controllers\Api\AdmissionEnquiryController::class, 'convertToStudent']);

    // ID Card & Photo Management Routes
    Route::get('/id-card-templates', [\App\Http\Controllers\Api\IdCardController::class, 'getTemplates']);
    Route::post('/id-card-templates', [\App\Http\Controllers\Api\IdCardController::class, 'saveTemplate']);
    Route::delete('/id-card-templates/{id}', [\App\Http\Controllers\Api\IdCardController::class, 'deleteTemplate']);
    Route::post('/students/{studentId}/photo', [\App\Http\Controllers\Api\IdCardController::class, 'updateStudentPhoto']);
    Route::post('/students/bulk-photos', [\App\Http\Controllers\Api\IdCardController::class, 'bulkUpdatePhotos']);

    // Expense Management Routes
    Route::get('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'index']);
    Route::post('/expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'store']);
    Route::put('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy']);
    Route::get('/expense-categories', [\App\Http\Controllers\Api\ExpenseController::class, 'categories']);

    // Reports Modules (Fees, Uniform/Dress, Financial Overview)
    Route::get('/reports/fees', [\App\Http\Controllers\Api\ReportController::class, 'feesReport']);
    Route::get('/reports/dress', [\App\Http\Controllers\Api\ReportController::class, 'dressReport']);
    Route::get('/reports/financial-overview', [\App\Http\Controllers\Api\ReportController::class, 'financialOverview']);

    // School Holidays & Vacation Calendar Routes
    Route::get('/school-holidays/presets', [\App\Http\Controllers\Api\SchoolHolidayController::class, 'presets']);
    Route::get('/school-holidays', [\App\Http\Controllers\Api\SchoolHolidayController::class, 'index']);
    Route::post('/school-holidays', [\App\Http\Controllers\Api\SchoolHolidayController::class, 'store']);
    Route::put('/school-holidays/{id}', [\App\Http\Controllers\Api\SchoolHolidayController::class, 'update']);
    Route::delete('/school-holidays/{id}', [\App\Http\Controllers\Api\SchoolHolidayController::class, 'destroy']);

    // Staff & Teacher Management Routes
    Route::get('/staff/designations', [\App\Http\Controllers\Api\StaffController::class, 'designations']);
    Route::get('/staff', [\App\Http\Controllers\Api\StaffController::class, 'index']);
    Route::post('/staff', [\App\Http\Controllers\Api\StaffController::class, 'store']);
    Route::get('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'show']);
    Route::put('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'update']);
    Route::delete('/staff/{id}', [\App\Http\Controllers\Api\StaffController::class, 'destroy']);

    // Staff Attendance Routes
    Route::get('/staff-attendance/daily', [\App\Http\Controllers\Api\StaffAttendanceController::class, 'daily']);
    Route::post('/staff-attendance/daily', [\App\Http\Controllers\Api\StaffAttendanceController::class, 'storeDaily']);
    Route::get('/staff-attendance/monthly', [\App\Http\Controllers\Api\StaffAttendanceController::class, 'monthly']);

    // Payroll & Salary Routes
    Route::get('/payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'index']);
    Route::post('/payrolls/calculate', [\App\Http\Controllers\Api\PayrollController::class, 'calculate']);
    Route::put('/payrolls/{id}', [\App\Http\Controllers\Api\PayrollController::class, 'update']);
    Route::post('/payrolls/disburse', [\App\Http\Controllers\Api\PayrollController::class, 'disburse']);
    Route::get('/payrolls/{id}/payslip', [\App\Http\Controllers\Api\PayrollController::class, 'payslip']);
    Route::get('/payrolls/export-bank-csv', [\App\Http\Controllers\Api\PayrollController::class, 'exportBankCsv']);
});

// Public Mobile Quick Photo Upload Routes (Direct Phone Camera & QR capture)
Route::get('/public/students/{studentId}/photo-upload', [\App\Http\Controllers\Api\IdCardController::class, 'showMobileUploadPage']);
Route::post('/public/students/{studentId}/photo-upload', [\App\Http\Controllers\Api\IdCardController::class, 'handleMobileUpload']);
Route::get('/public/students/{studentId}/photo', [\App\Http\Controllers\Api\IdCardController::class, 'getStudentPhotoStatus']);


