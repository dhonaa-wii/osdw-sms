<?php

use App\Enums\UserRole;
use App\Exports\ShortlistExport;
use App\Exports\SucLevellingExport;
use App\Exports\SucLevellingRangeExport;
use App\Models\AcademicTerm;
use App\Models\ApplicationAdditionalRequirement;
use App\Models\ApplicationDocument;
use App\Models\ProgramOffering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

Route::livewire('/', 'public::landing')->name('home');

Route::livewire('/programs', 'public::browse')->name('programs.index');
Route::livewire('/programs/{offering}', 'public::show')->name('programs.show');
Route::livewire('/programs/{offering}/apply', 'public::apply')->name('programs.apply');

Route::livewire('/track', 'public::track-lookup')->name('track.lookup');
Route::livewire('/track/dashboard', 'public::track-dashboard')->name('track.dashboard');
Route::livewire('/track/recover', 'public::track-recover')->name('track.recover');

Route::livewire('/results', 'public::results')->name('results.index');

Route::livewire('/login', 'staff::login')
    ->middleware('guest')
    ->name('login');

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

Route::livewire('/dashboard', 'staff::dashboard')
    ->middleware(['auth', 'role:'.implode(',', array_column(UserRole::cases(), 'value'))])
    ->name('dashboard');

Route::middleware(['auth', 'role:'.UserRole::OsdwStaff->value.','.UserRole::Admin->value])->prefix('staff')->name('staff.')->group(function () {
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
    Route::get('/applications/requirements/{receipt}', function (ApplicationAdditionalRequirement $receipt) {
        abort_unless($receipt->file_path && Storage::disk('local')->exists($receipt->file_path), 404, 'That file is no longer on the server.');

        return Storage::disk('local')->response($receipt->file_path, $receipt->original_name);
    })->name('applications.requirements.show');

    Route::livewire('/awards', 'staff::awards.index')->name('awards.index');
    Route::livewire('/offerings/{offering}/awards', 'staff::awards.workspace')->name('awards.workspace');
    Route::livewire('/scholars', 'staff::scholars.index')->name('scholars.index');
    Route::livewire('/offerings/{offering}/scholars', 'staff::scholars.workspace')->name('scholars.workspace');

    Route::livewire('/announcements', 'staff::announcements.index')->name('announcements.index');

    Route::livewire('/reports', 'staff::reports.index')->name('reports.index');
    Route::get('/reports/suc-levelling/{term}/export', function (AcademicTerm $term) {
        return Excel::download(
            new SucLevellingExport($term),
            'suc-levelling-'.str($term->school_year)->slug().'-'.$term->semester->value.'.xlsx'
        );
    })->name('reports.suc-levelling.export');
    Route::get('/reports/suc-levelling/export-range', function (Request $request) {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return Excel::download(
            new SucLevellingRangeExport($validated['from'] ?? null, $validated['to'] ?? null),
            'suc-levelling-'.($validated['from'] ?? 'start').'-to-'.($validated['to'] ?? 'now').'.xlsx'
        );
    })->name('reports.suc-levelling.export-range');
});

Route::livewire('/audit-log', 'staff::audit-log.index')
    ->middleware(['auth', 'role:'.UserRole::Admin->value])
    ->name('audit-log.index');

Route::middleware(['auth', 'role:'.UserRole::Admin->value])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/settings', 'admin::settings.index')->name('settings');
    Route::livewire('/settings/system', 'admin::settings.system')->name('settings.system');
    Route::livewire('/database-backup', 'admin::database-backup.index')->name('database-backup');
    Route::get('/database-backup/download', function () {
        abort_unless(config('database.default') === 'mysql', 422, 'Backup export requires the MySQL connection.');

        $config = config('database.connections.mysql');

        $process = new Process([
            config('database.mysqldump_path', 'mysqldump'),
            '-h', $config['host'],
            '-P', (string) $config['port'],
            '-u', $config['username'],
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
    Route::livewire('/scholarship-programs/{scholarshipProgram}/configure', 'admin::program-configuration.program-configuration')->name('scholarship-programs.configure');
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
