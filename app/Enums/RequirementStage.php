<?php

namespace App\Enums;

use App\Models\ProgramOffering;

/**
 * When an offering's additional requirement applies. A shortlist requirement
 * gates the award — every one must be complied with before the applicant can
 * be awarded. An award requirement is asked of awardees afterwards, for
 * disbursement. A renewal requirement belongs to a renewal offering and gates
 * the renewal the same way a shortlist requirement gates an award.
 */
enum RequirementStage: string
{
    case Shortlist = 'shortlist';
    case Award = 'award';
    case Renewal = 'renewal';

    /**
     * The stages an offering configures: a renewal offering asks for renewal
     * requirements, plus shortlist and award ones when it also takes new
     * applicants; any other offering for shortlist and award ones only.
     *
     * @return array<int, self>
     */
    public static function forOffering(ProgramOffering $offering): array
    {
        $stages = $offering->takesNewApplicants() ? self::forApplicants() : [];

        return $offering->isRenewal() ? [...$stages, self::Renewal] : $stages;
    }

    /**
     * The stages that apply to new applicants.
     *
     * @return array<int, self>
     */
    public static function forApplicants(): array
    {
        return [self::Shortlist, self::Award];
    }

    /**
     * Disbursement and renewal uploads are submitted once; the scholar can
     * upload again only after staff return the file.
     */
    public function isSubmittedOnce(): bool
    {
        return $this !== self::Shortlist;
    }

    public function label(): string
    {
        return match ($this) {
            self::Shortlist => 'Shortlist',
            self::Award => 'Awarded',
            self::Renewal => 'Renewal',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::Shortlist => 'Shortlist requirements',
            self::Award => 'Disbursement requirements',
            self::Renewal => 'Renewal requirements',
        };
    }

    /**
     * The stage's name where staff review an applicant's documents, listed
     * under Documents after the application documents.
     */
    public function reviewHeading(): string
    {
        return match ($this) {
            self::Shortlist => 'Additional requirements',
            self::Award => 'Disbursement requirements',
            self::Renewal => 'Renewal requirements',
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::Shortlist => 'Shortlisted applicants complete these before they can be awarded.',
            self::Award => 'Awardees complete these before the award is released.',
            self::Renewal => 'Renewing scholars complete these before they can be renewed.',
        };
    }
}
