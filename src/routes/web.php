<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployerProvisioningController;
use App\Http\Controllers\Admin\JobController as AdminJobController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employer\ApplicantController as EmployerApplicantController;
use App\Http\Controllers\Employer\CompanyController as EmployerCompanyController;
use App\Http\Controllers\Employer\DashboardController as EmployerDashboardController;
use App\Http\Controllers\Employer\JobController as EmployerJobController;
use App\Http\Controllers\JobSeeker\ApplicationController as JobSeekerApplicationController;
use App\Http\Controllers\JobSeeker\DashboardController as JobSeekerDashboardController;
use App\Http\Controllers\JobSeeker\JobController as JobSeekerJobController;
use App\Http\Controllers\Locked\EmployerAccessController;
use App\Http\Controllers\Locked\SeekerAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ApplyController;
use App\Http\Controllers\Public\PricingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/apply', ApplyController::class)->name('apply');
Route::get('/pricing', PricingController::class)->name('pricing');

Route::middleware(['auth', 'password.change.required'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/locked/seeker-access', SeekerAccessController::class)->name('locked.seeker');
    Route::get('/locked/employer-access', EmployerAccessController::class)->name('locked.employer');

    Route::middleware(['role:job_seeker'])->prefix('jobseeker')->name('jobseeker.')->group(function () {
        Route::get('/dashboard', JobSeekerDashboardController::class)->name('dashboard');

        Route::get('/profile', [\App\Http\Controllers\JobSeeker\ProfileController::class, 'edit'])
            ->name('profile.edit');

        Route::patch('/profile', [\App\Http\Controllers\JobSeeker\ProfileController::class, 'update'])
            ->name('profile.update');

        Route::post('/profile/resume', [\App\Http\Controllers\JobSeeker\DocumentController::class, 'uploadResume'])
            ->name('profile.resume.upload');

        Route::delete('/profile/resume', [\App\Http\Controllers\JobSeeker\DocumentController::class, 'clearResume'])
            ->name('profile.resume.clear');

        Route::post('/profile/cover-letter', [\App\Http\Controllers\JobSeeker\DocumentController::class, 'uploadCoverLetter'])
            ->name('profile.cover-letter.upload');

        Route::post('/documents', [\App\Http\Controllers\JobSeeker\JobSeekerDocumentController::class, 'store'])
            ->name('documents.store');

        Route::delete('/documents/{document}', [\App\Http\Controllers\JobSeeker\JobSeekerDocumentController::class, 'destroy'])
            ->name('documents.destroy');

        Route::middleware('seeker.access')->group(function () {
            Route::get('/jobs', [JobSeekerJobController::class, 'index'])->name('jobs.index');
            Route::get('/jobs/{job}', [JobSeekerJobController::class, 'show'])->name('jobs.show');

            Route::get('/jobs/{job}/apply', [JobSeekerApplicationController::class, 'create'])->name('jobs.apply');
            Route::post('/jobs/{job}/apply', [JobSeekerApplicationController::class, 'store'])->name('jobs.apply.store');

            Route::get('/applications', [JobSeekerApplicationController::class, 'index'])->name('applications.index');
            Route::patch('/applications/{application}/withdraw', [JobSeekerApplicationController::class, 'withdraw'])->name('applications.withdraw');
        });
    });

    Route::middleware(['role:employer'])->prefix('employer')->name('employer.')->group(function () {
        Route::get('/dashboard', EmployerDashboardController::class)->name('dashboard');

        Route::get('/company', [EmployerCompanyController::class, 'edit'])->name('company.edit');
        Route::patch('/company', [EmployerCompanyController::class, 'update'])->name('company.update');
        Route::post('/company/logo', [\App\Http\Controllers\Employer\LogoController::class, 'upload'])->name('company.logo.upload');
        Route::delete('/company/logo', [\App\Http\Controllers\Employer\LogoController::class, 'remove'])->name('company.logo.remove');

        Route::middleware('employer.posting.access')->group(function () {
            Route::get('/jobs', [EmployerJobController::class, 'index'])->name('jobs.index');
            Route::get('/jobs/create', [EmployerJobController::class, 'create'])->name('jobs.create');
            Route::post('/jobs', [EmployerJobController::class, 'store'])->name('jobs.store');
            Route::get('/jobs/{job}/edit', [EmployerJobController::class, 'edit'])->name('jobs.edit');
            Route::patch('/jobs/{job}', [EmployerJobController::class, 'update'])->name('jobs.update');
            Route::get('/applicants', [EmployerApplicantController::class, 'index'])->name('applicants.index');
            Route::patch('/applications/{application}/status', [EmployerApplicantController::class, 'updateStatus'])->name('applications.update-status');
        });
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');

        Route::post('/users/{user}/issue-temporary-password', [\App\Http\Controllers\Admin\UserController::class, 'issueTemporaryPassword'])
            ->name('users.issue-temporary-password');

        Route::patch('/users/{user}/force-password-change', [\App\Http\Controllers\Admin\UserController::class, 'forcePasswordChange'])
            ->name('users.force-password-change');

        Route::patch('/users/{user}/clear-password-change', [\App\Http\Controllers\Admin\UserController::class, 'clearPasswordChange'])
            ->name('users.clear-password-change');

        Route::post('/users/{user}/grant-access', [\App\Http\Controllers\Admin\UserController::class, 'grantAccess'])
            ->name('users.grant-access');

        Route::delete('/users/{user}/revoke-access/{type}', [\App\Http\Controllers\Admin\UserController::class, 'revokeAccess'])
            ->name('users.revoke-access');

        Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
        Route::patch('/jobs/{job}/approve', [AdminJobController::class, 'approve'])->name('jobs.approve');
        Route::patch('/jobs/{job}/pending', [AdminJobController::class, 'setPending'])->name('jobs.pending');
        Route::patch('/jobs/{job}/archive', [AdminJobController::class, 'archive'])->name('jobs.archive');

        Route::get('/entitlements', [\App\Http\Controllers\Admin\EntitlementController::class, 'index'])->name('entitlements.index');
        Route::post('/entitlements', [\App\Http\Controllers\Admin\EntitlementController::class, 'store'])->name('entitlements.store');
        Route::delete('/entitlements/{entitlement}', [\App\Http\Controllers\Admin\EntitlementController::class, 'destroy'])->name('entitlements.destroy');

        Route::get('/employers/create', [EmployerProvisioningController::class, 'create'])->name('employers.create');
        Route::post('/employers', [EmployerProvisioningController::class, 'store'])->name('employers.store');

        Route::get('/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'store'])->name('payments.store');
        Route::delete('/payments/{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'destroy'])->name('payments.destroy');

        Route::post('/payments/{payment}/confirm', [PaymentReviewController::class, 'confirm'])
            ->name('payments.confirm');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/wipay/seeker', [\App\Http\Controllers\Payment\CheckoutController::class, 'seeker'])
            ->name('wipay.seeker');

        Route::get('/wipay/employer', [\App\Http\Controllers\Payment\CheckoutController::class, 'employer'])
            ->name('wipay.employer');

        Route::match(['get', 'post'], '/wipay/callback', [\App\Http\Controllers\Payment\CheckoutController::class, 'callback'])
            ->name('wipay.callback');
    });

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

Route::middleware('auth')->group(function () {
    Route::get('/force-change-password', [ForcedPasswordChangeController::class, 'edit'])->name('forced-password.edit');
    Route::put('/force-change-password', [ForcedPasswordChangeController::class, 'update'])->name('forced-password.update');
});

require __DIR__.'/auth.php';