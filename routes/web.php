<?php

use App\Enums\BrandingImageKey;
use App\Enums\UserRole;
use App\Exports\ShortlistExport;
use App\Exports\SucLevellingExport;
use App\Http\Controllers\Public\GoogleAuthController;
use App\Models\ApplicationAdditionalRequirement;
use App\Models\ApplicationDocument;
use App\Models\BrandingImage;
use App\Models\College;
use App\Models\OfferingAdditionalRequirement;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Models\StandardRequirement;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

Route::livewire('/', 'public::landing')->name('home');

// Branding images live in the database; this streams one out. The ?v= stamp
// from BrandingImage::url() changes when an image is replaced, so browsers
// may cache it for a long time.
Route::get('/branding/{key}', function (BrandingImageKey $key) {
    $image = BrandingImage::findOrFail($key->value);

    return response($image->data, 200, [
        'Content-Type' => $image->mime_type,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('branding.show');

// Browsing is public and shows only offerings open for registration.
Route::livewire('/programs', 'public::browse')->name('programs.index');
Route::livewire('/programs/{offering}', 'public::show')->name('programs.show');

Route::livewire('/results', 'public::results')->name('results.index');
Route::livewire('/programs/{offering}/results', 'public::offering-results')->name('programs.results');

// One sign-in for students, staff and administrators.
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth::login')->name('login');
    Route::livewire('/register', 'auth::register')->name('register');
    Route::livewire('/forgot-password', 'auth::forgot-password')->name('password.request');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
});

// Signs a guest in, or finishes connecting a signed-in staff member's Google account.
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

// Students: applying and everything after it needs an account. Each route
// checks the application, file or form belongs to the signed-in student.
Route::middleware(['auth', 'role:'.UserRole::Student->value])->group(function () {
    Route::livewire('/account/profile', 'student::complete-profile')->name('student.profile.complete');

    Route::middleware('student.profile')->group(function () {
        Route::livewire('/programs/{offering}/apply', 'student::apply')->name('programs.apply');

        Route::prefix('my')->name('student.')->group(function () {
            Route::livewire('/applications', 'student::applications.index')->name('applications.index');
            Route::livewire('/applications/{application}', 'student::applications.show')->name('applications.show');
            Route::livewire('/notifications', 'student::notifications')->name('notifications');
            Route::livewire('/settings', 'student::settings')->name('settings');

            Route::get('/documents/{document}', function (ApplicationDocument $document) {
                abort_unless($document->application->isOwnedBy(auth()->user()), 404);
                abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on the server.');

                return Storage::disk('local')->response($document->file_path, $document->original_name);
            })->name('documents.show');
            Route::get('/requirements/{submission}', function (ApplicationAdditionalRequirement $submission) {
                abort_unless($submission->application->isOwnedBy(auth()->user()), 404);
                abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404, 'That file is no longer on the server.');

                return Storage::disk('local')->response($submission->file_path, $submission->original_name);
            })->name('additional-requirements.show');
            Route::get('/blank-forms/{requirement}', function (OfferingAdditionalRequirement $requirement) {
                abort_unless(
                    auth()->user()->applicant->applications()->where('program_offering_id', $requirement->program_offering_id)->exists(),
                    404
                );
                abort_unless($requirement->template_path && Storage::disk('local')->exists($requirement->template_path), 404, 'That file is no longer on the server.');

                return Storage::disk('local')->download($requirement->template_path, $requirement->template_name);
            })->name('additional-requirements.template');
        });
    });
});

// A staff account's first sign-in: replace the generated password and connect Google.
Route::middleware(['auth', 'role:'.UserRole::OsdwStaff->value.','.UserRole::Admin->value])->group(function () {
    Route::livewire('/staff/account/setup', 'staff::account-setup')->name('staff.account.setup');
    Route::get('/auth/google/link', [GoogleAuthController::class, 'link'])->name('auth.google.link');
});

Route::livewire('/dashboard', 'staff::dashboard')
    ->middleware(['auth', 'role:'.UserRole::Admin->value.','.UserRole::OsdwStaff->value, 'staff.setup'])
    ->name('dashboard');

Route::middleware(['auth', 'role:'.UserRole::OsdwStaff->value.','.UserRole::Admin->value, 'staff.setup'])->prefix('staff')->name('staff.')->group(function () {
    Route::livewire('/profile', 'staff::profile')->name('profile');
    Route::livewire('/notifications', 'staff::notifications')->name('notifications');

    Route::livewire('/applications', 'staff::applications.index')->name('applications.index');
    Route::livewire('/applications/offerings/{offering}', 'staff::applications.workspace')->name('applications.workspace');
    Route::livewire('/applications/{application}/review', 'staff::applications.review')->name('applications.review');
    Route::get('/applications/offerings/{offering}/shortlist/export', function (ProgramOffering $offering) {
        return Excel::download(
            new ShortlistExport($offering),
            'shortlist-'.str($offering->scholarshipProgram->name)->slug().'.xlsx'
        );
    })->name('applications.workspace.shortlist.export');
    Route::get('/applications/documents/{document}', function (ApplicationDocument $document) {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on the server.');

        return Storage::disk('local')->response($document->file_path, $document->original_name);
    })->name('applications.documents.show');
    Route::get('/applications/requirements/{submission}', function (ApplicationAdditionalRequirement $submission) {
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404, 'That file is no longer on the server.');

        return Storage::disk('local')->response($submission->file_path, $submission->original_name);
    })->name('applications.additional-requirements.show');
    Route::get('/additional-requirements/{requirement}/blank-form', function (OfferingAdditionalRequirement $requirement) {
        abort_unless($requirement->template_path && Storage::disk('local')->exists($requirement->template_path), 404, 'That file is no longer on the server.');

        return Storage::disk('local')->response($requirement->template_path, $requirement->template_name);
    })->name('additional-requirements.template');

    Route::livewire('/renewals/offerings/{offering}', 'staff::renewals.index')->name('renewals.index');
    Route::livewire('/renewals/{application}/review', 'staff::renewals.review')->name('renewals.review');

    Route::livewire('/grantees', 'staff::grantees.index')->name('grantees.index');
    Route::livewire('/grantees/{award}', 'staff::grantees.show')->name('grantees.show');

    Route::livewire('/announcements', 'staff::announcements.index')->name('announcements.index');

    Route::livewire('/reports', 'staff::reports.index')->name('reports.index');
    Route::get('/reports/suc-levelling/export', function (Request $request) {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'integer', 'exists:scholarship_programs,id'],
            'offering' => ['nullable', 'integer', 'exists:program_offerings,id'],
            'college' => ['nullable', 'integer', 'exists:colleges,id'],
        ]);
        $offering = isset($validated['offering']) ? ProgramOffering::find($validated['offering']) : null;

        return Excel::download(
            new SucLevellingExport(
                $validated['q'] ?? '',
                isset($validated['program']) ? (int) $validated['program'] : null,
                $offering,
                isset($validated['college']) ? (int) $validated['college'] : null,
            ),
            'suc-levelling-'.($offering ? str($offering->scholarshipProgram->name.' '.$offering->displayName())->slug() : 'all').'.xlsx'
        );
    })->name('reports.suc-levelling.export');
    Route::get('/reports/suc-levelling/print', function (Request $request, ReportService $reportService) {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'integer', 'exists:scholarship_programs,id'],
            'offering' => ['nullable', 'integer', 'exists:program_offerings,id'],
            'college' => ['nullable', 'integer', 'exists:colleges,id'],
        ]);
        $offering = isset($validated['offering']) ? ProgramOffering::with('scholarshipProgram', 'academicTerm')->find($validated['offering']) : null;
        $programId = isset($validated['program']) ? (int) $validated['program'] : null;
        $collegeId = isset($validated['college']) ? (int) $validated['college'] : null;

        return view('staff.reports.print', [
            'rows' => $reportService->sucLevelling(
                $reportService->granteeQuery($validated['q'] ?? '', $programId, $offering, $collegeId)->get()
            ),
            'offering' => $offering,
            'program' => $programId ? ScholarshipProgram::find($programId) : null,
            'college' => $collegeId ? College::find($collegeId) : null,
            'search' => trim($validated['q'] ?? ''),
        ]);
    })->name('reports.suc-levelling.print');
});

Route::livewire('/audit-log', 'staff::audit-log.index')
    ->middleware(['auth', 'role:'.UserRole::Admin->value])
    ->name('audit-log.index');

Route::middleware(['auth', 'role:'.UserRole::Admin->value])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/settings', 'admin::settings.index')->name('settings');
    Route::livewire('/settings/system', 'admin::settings.system')->name('settings.system');
    Route::livewire('/settings/deleted-items', 'admin::settings.deleted-items')->name('settings.deleted-items');
    Route::livewire('/database-backup', 'admin::database-backup.index')->name('database-backup');
    Route::get('/database-backup/download', function () {
        abort_unless(config('database.default') === 'mysql', 422, 'Backup export requires the MySQL connection.');

        $config = config('database.connections.mysql');

        $process = new Process([
            config('database.mysqldump_path', 'mysqldump'),
            '-h', $config['host'],
            '-P', (string) $config['port'],
            '-u', $config['username'],
            '--hex-blob',
            $config['database'],
        ], null, ['MYSQL_PWD' => $config['password']]);

        $process->setTimeout(300);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            abort(500, 'Backup failed: '.$e->getMessage());
        }

        $filename = 'osdw-backup-'.now()->format('Y-m-d-His').'.sql';

        return response()->streamDownload(function () use ($process) {
            echo $process->getOutput();
        }, $filename, ['Content-Type' => 'application/sql']);
    })->name('database-backup.download');
    Route::livewire('/users', 'admin::user-management.index')->name('users');
    Route::livewire('/email-log', 'admin::email-log.index')->name('email-log');
    Route::livewire('/scholarship-programs', 'admin::program-configuration.scholarship-programs')->name('scholarship-programs');
    Route::livewire('/scholarship-programs/standard-requirements', 'admin::program-configuration.standard-requirements')->name('scholarship-programs.standard-requirements');
    Route::get('/scholarship-programs/standard-requirements/{standardRequirement}/blank-form', function (StandardRequirement $standardRequirement) {
        abort_unless($standardRequirement->template_path && Storage::disk('local')->exists($standardRequirement->template_path), 404, 'That file is no longer on the server.');

        return Storage::disk('local')->response($standardRequirement->template_path, $standardRequirement->template_name);
    })->name('scholarship-programs.standard-requirements.template');
    Route::livewire('/scholarship-programs/{scholarshipProgram}/offerings', 'admin::program-configuration.offerings')->name('scholarship-programs.offerings');
    Route::livewire('/offerings/{offering}/view', 'admin::program-configuration.offering-view')->name('offerings.view');
    Route::livewire('/offerings/{offering}/configure', 'admin::program-configuration.offering-configuration')->name('offerings.configure');
    Route::livewire('/colleges', 'admin::master-data.colleges')->name('colleges');
    Route::livewire('/campus', 'admin::master-data.campuses')->name('campus');
    Route::livewire('/providers', 'admin::master-data.providers')->name('providers');
    Route::livewire('/providers/{provider}/view', 'admin::master-data.provider-view')->name('providers.view');
    Route::livewire('/programs', 'admin::master-data.programs')->name('programs');
    Route::livewire('/academic-terms', 'admin::master-data.academic-terms')->name('academic-terms');
});
