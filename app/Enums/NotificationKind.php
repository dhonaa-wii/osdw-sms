<?php

namespace App\Enums;

/**
 * What a bell notification is about. Decides its icon and tone.
 * ProgramCreated is staff-only; registration and interview kinds are sent
 * to both bells.
 */
enum NotificationKind: string
{
    case StatusChanged = 'status_changed';
    case RequirementsOpened = 'requirements_opened';
    case InterviewScheduled = 'interview_scheduled';
    case Announcement = 'announcement';
    case ResultsPublished = 'results_published';
    case RegistrationOpened = 'registration_opened';
    case RegistrationClosed = 'registration_closed';
    case ProgramCreated = 'program_created';

    public function icon(): string
    {
        return match ($this) {
            self::StatusChanged => 'clock',
            self::RequirementsOpened => 'clipboard-check',
            self::InterviewScheduled => 'calendar',
            self::Announcement => 'megaphone',
            self::ResultsPublished => 'award',
            self::RegistrationOpened => 'graduation',
            self::RegistrationClosed => 'lock',
            self::ProgramCreated => 'layers',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::ResultsPublished => 'bg-gold-50 text-gold-600',
            self::RequirementsOpened => 'bg-amber-600/10 text-amber-600',
            self::InterviewScheduled, self::Announcement => 'bg-azure-600/10 text-azure-600',
            self::RegistrationClosed => 'bg-paper text-slate',
            default => 'bg-navy-50 text-navy-700',
        };
    }
}
