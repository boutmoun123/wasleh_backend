<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Middleware\EnsurePublishingQuota;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'success', 'message' => 'WASLA Backend API is working']));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:otp');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
});

Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{job}', [JobController::class, 'publicShow'])->whereNumber('job');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::put('/auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password-change');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:5,1');

    Route::middleware(EnsureRole::class.':seeker')->prefix('seeker')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile/section/{section}', [ProfileController::class, 'updateSection']);
        Route::post('/profile/parse-cv', [ProfileController::class, 'parseCv'])->middleware('throttle:5,1');
        Route::get('/profile/ats-analysis', [ProfileController::class, 'atsAnalysis']);
        Route::post('/profile/upload-cv', [ProfileController::class, 'uploadCv']);
        Route::get('/profile/download-cv', [ProfileController::class, 'downloadCv']);
        Route::get('/profile/generate-cv', [ProfileController::class, 'generatePdf']);
        Route::get('/recommendations', [RecommendationController::class, 'index']);
        Route::get('/jobs/{job}/match', [RecommendationController::class, 'evaluate']);
        Route::post('/jobs/{job}/apply', [ApplicationController::class, 'apply'])->middleware('throttle:10,1');
        Route::get('/applications', [ApplicationController::class, 'myApplications']);
        Route::patch('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw']);
    });
    Route::middleware(EnsureRole::class.':employer')->prefix('employer')->group(function () {
        Route::get('/company', [CompanyController::class, 'show']);
        Route::put('/company', [CompanyController::class, 'save']);
        Route::post('/company/documents', [CompanyController::class, 'uploadDocument']);
        Route::get('/company/documents/{document}', [CompanyController::class, 'downloadDocument']);
        Route::get('/candidates', [CandidateController::class, 'index']);
        Route::get('/jobs', [JobController::class, 'mineIndex']);
        Route::post('/jobs', [JobController::class, 'store']);
        Route::put('/jobs/{job}', [JobController::class, 'update']);
        Route::delete('/jobs/{job}', [JobController::class, 'destroy']);
        Route::post('/jobs/{job}/publish', [JobController::class, 'publish'])->middleware(EnsurePublishingQuota::class);
        Route::post('/jobs/{job}/pause', [JobController::class, 'pause']);
        Route::post('/jobs/{job}/close', [JobController::class, 'close']);
        Route::get('/jobs/{job}/applicants', [ApplicationController::class, 'applicants']);
        Route::patch('/applications/{application}/status', [ApplicationController::class, 'status']);
        Route::get('/applications/{application}/contact', [ApplicationController::class, 'contact']);
    });
    Route::middleware(EnsureRole::class.':admin')->prefix('admin')->group(function () {
        Route::get('/companies', [AdminController::class, 'companies']);
        Route::patch('/companies/{company}/verify', [AdminController::class, 'verifyCompany']);
        Route::post('/companies/{company}/quota', [AdminController::class, 'grantQuota']);
        Route::get('/company-documents/{document}', [AdminController::class, 'downloadDocument']);
        Route::get('/reports', [AdminController::class, 'reports']);
        Route::patch('/reports/{report}', [AdminController::class, 'reviewReport']);
        Route::get('/jobs/{job}/applicants/export', [AdminController::class, 'exportApplicants']);
    });
});
