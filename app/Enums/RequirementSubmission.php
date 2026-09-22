<?php

namespace App\Enums;

/**
 * How a shortlisted applicant hands in an additional requirement — as a file
 * through their tracking page, or as a physical copy at the OSDW office.
 */
enum RequirementSubmission: string
{
    case Upload = 'upload';
    case PhysicalCopy = 'physical_copy';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload',
            self::PhysicalCopy => 'Physical copy',
        };
    }

    /**
     * What the applicant is told to do, addressed to them directly.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Upload => 'Upload a copy below.',
            self::PhysicalCopy => 'Submit the original at the OSDW office.',
        };
    }

    public function isUpload(): bool
    {
        return $this === self::Upload;
    }
}
