<?php

use App\Services\DeletionService;
use App\Services\StudentNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('osdw:registration-notices', function (StudentNotificationService $notifications) {
    $notifications->syncRegistrationNotices();
})->purpose('Notify students of offerings that opened or closed for registration');

// Registration opens and closes on a date, not on any user action. The
// students' bell also runs this on page load, so notices still go out on a
// machine with no scheduler running.
Schedule::command('osdw:registration-notices')->everyMinute()->withoutOverlapping();

Artisan::command('osdw:purge-deleted', function (DeletionService $deletions) {
    $this->info('Purged '.$deletions->purgeExpired().' deleted programs and offerings.');
})->purpose('Permanently remove programs and offerings deleted more than '.DeletionService::RETENTION_DAYS.' days ago');

// Settings → Deleted items also purges on page load, so nothing past the
// retention period lingers on a machine with no scheduler running.
Schedule::command('osdw:purge-deleted')->daily();
