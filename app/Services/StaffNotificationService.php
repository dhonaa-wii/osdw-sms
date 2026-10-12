<?php

namespace App\Services;

use App\Enums\ApplicationKind;
use App\Enums\NotificationKind;
use App\Enums\UserRole;
use App\Models\ProgramOffering;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Notifications\StaffNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fills the staff and administrators' notification bells: a new scholarship
 * program, an offering opening or closing for registration, and an
 * interview being scheduled or moved. Every active staff and administrator
 * account hears about each one.
 */
class StaffNotificationService
{
    /**
     * Administrators land on the program's offerings; staff, who cannot
     * open program configuration, on the Applications list.
     */
    public function programCreated(ScholarshipProgram $program): void
    {
        $program->loadMissing('provider');

        $notification = new StaffNotification(
            NotificationKind::ProgramCreated,
            'New scholarship program: '.$program->name,
            $program->provider ? 'Provided by '.$program->provider->name.'.' : '',
            route('admin.scholarship-programs.offerings', $program, absolute: false),
        );

        Notification::send($this->recipients()->where('role', UserRole::Admin->value)->get(), $notification);

        $notification->url = route('staff.applications.index', absolute: false);
        Notification::send($this->recipients()->where('role', UserRole::OsdwStaff->value)->get(), $notification);
    }

    public function registrationOpened(ProgramOffering $offering): void
    {
        $this->send(
            NotificationKind::RegistrationOpened,
            'Now open: '.$offering->scholarshipProgram->name,
            $offering->displayName().'. Applications close '.$offering->closes_at->format('M j, Y').'.',
            $this->workspaceUrl($offering),
        );
    }

    public function registrationClosed(ProgramOffering $offering): void
    {
        $count = $offering->applications()->where('kind', ApplicationKind::New->value)->count();

        $this->send(
            NotificationKind::RegistrationClosed,
            'Registration closed: '.$offering->scholarshipProgram->name,
            $offering->displayName().'. '.$count.' '.str('application')->plural($count).' to review.',
            $this->workspaceUrl($offering),
        );
    }

    public function interviewScheduled(ProgramOffering $offering, bool $rescheduled): void
    {
        $offering->loadMissing('scholarshipProgram', 'academicTerm');

        $this->send(
            NotificationKind::InterviewScheduled,
            ($rescheduled ? 'Interview moved: ' : 'Interview scheduled: ').$offering->scholarshipProgram->name,
            $offering->displayName().'. '.$offering->interview_at->format('M j, Y · g:i A').($offering->interview_venue ? ', '.$offering->interview_venue : '').'.',
            $this->workspaceUrl($offering),
        );
    }

    protected function send(NotificationKind $kind, string $title, string $body, string $url): void
    {
        Notification::send($this->recipients()->get(), new StaffNotification($kind, $title, $body, $url));
    }

    protected function recipients()
    {
        return User::staff()->where('is_active', true);
    }

    protected function workspaceUrl(ProgramOffering $offering): string
    {
        return route('staff.applications.workspace', $offering, absolute: false);
    }
}
