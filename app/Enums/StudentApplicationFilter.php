<?php

namespace App\Enums;

/**
 * The status groups on a student's My applications page. Fewer than the
 * statuses themselves: a student only needs to know whether it is still
 * moving, awarded (or renewed), or closed out.
 */
enum StudentApplicationFilter: string
{
    case InProgress = 'in_progress';
    case Awarded = 'awarded';
    case NotApproved = 'not_approved';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Awarded => 'Awarded',
            self::NotApproved => 'Not approved',
        };
    }

    /**
     * @return array<int, ApplicationStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::InProgress => [ApplicationStatus::Draft, ApplicationStatus::Submitted, ApplicationStatus::Screening, ApplicationStatus::Shortlisted, ApplicationStatus::Waitlisted],
            self::Awarded => [ApplicationStatus::Awarded],
            self::NotApproved => [ApplicationStatus::Denied],
        };
    }
}
