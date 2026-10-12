<?php

namespace App\Enums;

/**
 * How an applicant hands in an additional requirement — a file through their
 * tracking page, a physical copy at the OSDW office, or both.
 */
enum SubmissionMode: string
{
    case Upload = 'upload';
    case Physical = 'physical';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload',
            self::Physical => 'Physical copy',
            self::Both => 'Upload and physical copy',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Upload => 'Uploaded on the tracking page',
            self::Physical => 'Handed in at the OSDW office',
            self::Both => 'Upload a copy, then hand in the original',
        };
    }

    public function needsUpload(): bool
    {
        return $this !== self::Physical;
    }

    public function needsPhysical(): bool
    {
        return $this !== self::Upload;
    }
}
