<?php

namespace App\Services;

use App\Enums\ApplicationKind;
use App\Enums\ApplicationStatus;
use App\Enums\NotificationKind;
use App\Enums\OfferingStatus;
use App\Enums\RequirementStage;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Award;
use App\Models\ProgramOffering;
use App\Models\User;
use App\Notifications\StudentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Fills the students' notification bells. Each event names who hears about
 * it: an applicant's own status, their offering's requirements, interview,
 * announcements and results, and — for every student — offerings opening
 * and closing for registration.
 */
class StudentNotificationService
{
    /**
     * Called for every status log. The student's own submission (submitted,
     * then automatically under review) is not news to them.
     */
    public function statusChanged(ApplicationStatusLog $log): void
    {
        $application = $log->application()->with('applicant.user', 'programOffering.scholarshipProgram', 'programOffering.academicTerm')->first();
        $user = $application?->applicant?->user;

        if (! $user) {
            return;
        }

        $ownSubmission = $application->kind === ApplicationKind::New
            && in_array($log->from_status, [null, ApplicationStatus::Submitted], true)
            && in_array($log->to_status, [ApplicationStatus::Submitted, ApplicationStatus::Screening], true);

        if ($ownSubmission) {
            return;
        }

        $offering = $application->programOffering;
        $renewal = $application->isRenewal();

        [$title, $body] = match ($log->to_status) {
            ApplicationStatus::Screening => $renewal && $log->from_status === null
                ? ['Renew your scholarship', 'Hand in your renewal requirements by '.$offering->closes_at->format('M j, Y').'.']
                : [$renewal ? 'Your renewal is under review' : 'Your application is under review', ''],
            ApplicationStatus::Shortlisted => ['You are on the shortlist', $this->shortlistNextStep($offering)],
            ApplicationStatus::Awarded => [$renewal ? 'Your scholarship has been renewed' : 'You have been awarded', $this->awardNextStep($offering, $renewal)],
            ApplicationStatus::Waitlisted => ['You are on the waitlist', 'You will be contacted if a slot opens up.'],
            ApplicationStatus::Denied => [$renewal ? 'Your renewal was not approved' : 'Your application was not approved', (string) $log->reason],
            default => ['Your application is '.mb_strtolower($application->statusLabel($log->to_status)), ''],
        };

        $user->notify(new StudentNotification(
            NotificationKind::StatusChanged,
            $title,
            $body === '' ? $this->offeringName($offering) : $this->offeringName($offering).'. '.$body,
            $this->applicationUrl($application),
        ));
    }

    /**
     * Staff recorded the award as disbursed. Not a status log — the
     * application stays awarded — so it is sent from AwardService.
     */
    public function disbursed(Award $award): void
    {
        $application = $award->application()->with('applicant.user', 'programOffering')->first();
        $user = $application?->applicant?->user;

        if (! $user) {
            return;
        }

        $user->notify(new StudentNotification(
            NotificationKind::StatusChanged,
            'Your scholarship has been disbursed',
            $this->offeringName($application->programOffering).'.',
            $this->applicationUrl($application),
        ));
    }

    /**
     * Staff opened a requirement stage: shortlisted applicants (shortlist
     * stage) or awardees (disbursement stage) now have something to hand in.
     */
    public function requirementsOpened(ProgramOffering $offering, RequirementStage $stage): void
    {
        if (! $offering->additionalRequirements()->where('stage', $stage->value)->exists()) {
            return;
        }

        $status = $stage === RequirementStage::Shortlist ? ApplicationStatus::Shortlisted : ApplicationStatus::Awarded;

        $this->notifyApplicants(
            $this->applications($offering)->where('kind', ApplicationKind::New->value)->where('status', $status->value),
            NotificationKind::RequirementsOpened,
            $stage === RequirementStage::Shortlist ? 'Requirements to complete' : 'Requirements for your award release',
            $this->offeringName($offering).'. Hand them in on your application page.',
        );
    }

    /**
     * The interview was set or moved. Only shortlisted applicants are
     * called to it.
     */
    public function interviewScheduled(ProgramOffering $offering, bool $rescheduled): void
    {
        if (! $offering->hasScheduledInterview()) {
            return;
        }

        $this->notifyApplicants(
            $this->applications($offering)->where('kind', ApplicationKind::New->value)->where('status', ApplicationStatus::Shortlisted->value),
            NotificationKind::InterviewScheduled,
            $rescheduled ? 'Your interview was moved' : 'Your interview is scheduled',
            $this->offeringName($offering).'. '.$this->interviewWhen($offering).'.',
        );
    }

    /**
     * An offering's announcement reaches everyone who applied to it; a
     * general one reaches every student and is shown on the landing page.
     */
    public function announcementPublished(Announcement $announcement): void
    {
        if ($announcement->program_offering_id) {
            $offering = $announcement->programOffering;

            $this->notifyApplicants(
                $this->applications($offering),
                NotificationKind::Announcement,
                $announcement->title,
                $this->offeringName($offering).'. '.str($announcement->body)->squish()->limit(120),
            );

            return;
        }

        Notification::send(User::students()->get(), new StudentNotification(
            NotificationKind::Announcement,
            $announcement->title,
            (string) str($announcement->body)->squish()->limit(140),
            route('home', absolute: false).'#announcements',
        ));
    }

    public function resultsPublished(ProgramOffering $offering): void
    {
        $url = route('programs.results', $offering, absolute: false);

        $this->applications($offering)->with('applicant.user')->get()
            ->map(fn (Application $application) => $application->applicant->user)
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new StudentNotification(
                NotificationKind::ResultsPublished,
                'Results are out',
                $this->offeringName($offering).'. See the list of awardees.',
                $url,
            )));
    }

    /**
     * Tell every student when an offering opens for registration, and again
     * when it closes — at its deadline, or as soon as it hits its
     * application cap. Each offering is announced once per event: the
     * guarded update below lets only one caller claim it, so running this
     * from the scheduler and from page loads at the same time is safe.
     */
    public function syncRegistrationNotices(): void
    {
        $opened = ProgramOffering::open()
            ->whereNull('registration_opened_notified_at')
            ->whereHas('scholarshipProgram', fn ($query) => $query->where('is_active', true))
            ->with('scholarshipProgram', 'academicTerm')
            ->get();

        foreach ($opened as $offering) {
            if (! $this->claim($offering, 'registration_opened_notified_at')) {
                continue;
            }

            app(StaffNotificationService::class)->registrationOpened($offering);

            Notification::send(User::students()->get(), new StudentNotification(
                NotificationKind::RegistrationOpened,
                'Now open: '.$offering->scholarshipProgram->name,
                $offering->displayName().'. Apply by '.$offering->closes_at->format('M j, Y').'.',
                route('programs.show', $offering, absolute: false),
            ));
        }

        $closed = ProgramOffering::whereNotNull('registration_opened_notified_at')
            ->whereNull('registration_closed_notified_at')
            ->with('scholarshipProgram', 'academicTerm')
            ->get()
            ->filter(fn (ProgramOffering $offering) => $offering->registrationStatus() === OfferingStatus::Closed);

        foreach ($closed as $offering) {
            if (! $this->claim($offering, 'registration_closed_notified_at')) {
                continue;
            }

            // Students who applied land on their application; everyone else on the program list.
            $applicationIds = $this->applications($offering)
                ->join('applicants', 'applicants.id', '=', 'applications.applicant_id')
                ->whereNotNull('applicants.user_id')
                ->pluck('applications.id', 'applicants.user_id');

            app(StaffNotificationService::class)->registrationClosed($offering);

            foreach (User::students()->get() as $user) {
                $user->notify(new StudentNotification(
                    NotificationKind::RegistrationClosed,
                    'Registration closed: '.$offering->scholarshipProgram->name,
                    $offering->displayName().' is no longer taking applications.',
                    $applicationIds->has($user->id)
                        ? route('student.applications.show', $applicationIds[$user->id], absolute: false)
                        : route('programs.index', absolute: false),
                ));
            }
        }
    }

    /**
     * Stamp the column only if no one else has; true when this caller won.
     */
    protected function claim(ProgramOffering $offering, string $column): bool
    {
        return ProgramOffering::whereKey($offering->id)->whereNull($column)->update([$column => now()]) === 1;
    }

    protected function applications(ProgramOffering $offering): Builder
    {
        return Application::where('program_offering_id', $offering->id);
    }

    /**
     * One notification per applicant, each linking to their own application.
     */
    protected function notifyApplicants(Builder $applications, NotificationKind $kind, string $title, string $body): void
    {
        $applications->with('applicant.user')->get()
            ->filter(fn (Application $application) => $application->applicant->user !== null)
            ->each(fn (Application $application) => $application->applicant->user->notify(
                new StudentNotification($kind, $title, $body, $this->applicationUrl($application))
            ));
    }

    protected function shortlistNextStep(ProgramOffering $offering): string
    {
        $steps = collect();

        if ($offering->hasOpenedRequirements(RequirementStage::Shortlist)
            && $offering->additionalRequirements()->where('stage', RequirementStage::Shortlist->value)->exists()) {
            $steps->push('Hand in the requirements on your application page');
        }

        if ($offering->hasScheduledInterview()) {
            $steps->push('Your interview is '.$this->interviewWhen($offering));
        }

        return $steps->isEmpty() ? '' : $steps->implode('. ').'.';
    }

    protected function awardNextStep(ProgramOffering $offering, bool $renewal): string
    {
        if (! $renewal
            && $offering->hasOpenedRequirements(RequirementStage::Award)
            && $offering->additionalRequirements()->where('stage', RequirementStage::Award->value)->exists()) {
            return 'Hand in the requirements for your award release on your application page.';
        }

        return '';
    }

    protected function interviewWhen(ProgramOffering $offering): string
    {
        return 'on '.$offering->interview_at->format('M j, Y · g:i A')
            .($offering->interview_venue ? ' at '.$offering->interview_venue : '');
    }

    protected function offeringName(ProgramOffering $offering): string
    {
        $offering->loadMissing('scholarshipProgram', 'academicTerm');

        return $offering->scholarshipProgram->name.' · '.$offering->displayName();
    }

    protected function applicationUrl(Application $application): string
    {
        return route('student.applications.show', $application, absolute: false);
    }
}
