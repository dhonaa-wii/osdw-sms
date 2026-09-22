<?php

namespace App\Services;

use App\Enums\AllocationMode;
use App\Enums\CollegeScope;
use App\Models\College;
use App\Models\OfferingCollege;
use App\Models\OfferingEligibilityRule;
use App\Models\OfferingFormField;
use App\Models\OfferingRetentionRule;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramFormField;
use App\Models\ProgramOffering;
use App\Models\ProgramRetentionRule;
use App\Models\ScholarshipProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfferingService
{
    /**
     * Statuses that mean an applicant holds a slot in this offering. An award
     * is reserved at `approved` and activated at `accepted`, so both consume
     * a slot; a denied or withdrawn application never does.
     *
     * @var list<string>
     */
    protected array $awardedStatuses = ['approved', 'accepted'];

    /**
     * Headline figures for one offering: the money it commits, how many
     * applied, and how many hold a slot — overall and per college.
     *
     * Applications still in `draft` are excluded from every count: the
     * student has not submitted them, so they are not applicants yet.
     *
     * @return array{applied: int, awarded: int, allocation: float|null, awardedAmount: float, perCollege: Collection<int, array{college: College, applied: int, awarded: int, slots: int|null}>}
     */
    public function summary(ProgramOffering $offering): array
    {
        $applications = $offering->applications()
            ->where('status', '!=', 'draft')
            ->with('college')
            ->get(['id', 'college_id', 'status']);

        $applied = $applications->count();
        $awarded = $applications->whereIn('status.value', $this->awardedStatuses)->count();

        // Quota colleges appear even with no applicants yet — an empty quota
        // is exactly what staff need to see.
        $quotaSlots = $offering->offeringColleges()->with('college')->get();

        $perCollege = $quotaSlots
            ->map(fn (OfferingCollege $offeringCollege) => $offeringCollege->college)
            ->merge($applications->pluck('college'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn ($college) => [
                'college' => $college,
                'applied' => $applications->where('college_id', $college->id)->count(),
                'awarded' => $applications->where('college_id', $college->id)
                    ->whereIn('status.value', $this->awardedStatuses)
                    ->count(),
                'slots' => $quotaSlots->firstWhere('college_id', $college->id)?->slots,
            ])
            ->values();

        return [
            'applied' => $applied,
            'awarded' => $awarded,
            'allocation' => $offering->amount === null ? null : (float) $offering->amount * $offering->total_slots,
            'awardedAmount' => (float) $offering->awards()->whereIn('status', ['reserved', 'active', 'completed'])->sum('amount'),
            'perCollege' => $perCollege,
        ];
    }

    /**
     * Open a new offering of a scholarship program.
     *
     * The program's requirements are copied into the offering rather than
     * shared with it: form fields, eligibility rules and retention rules all
     * land in the offering's own rows. Staff therefore start from a filled-in
     * set instead of a blank one, and can edit it for this term without
     * affecting the program template or any offering already running.
     *
     * The copy runs once, at creation. Later edits to the template do not
     * reach offerings that already exist.
     *
     * Everything that can change from term to term — college scope, the
     * eligible colleges, allocation mode, slots, dates, amount — is set on
     * the offering itself and never copied.
     *
     * @param  array<string, mixed>  $attributes  term, campus, dates, slots, amount, scope, allocation
     */
    public function openOffering(ScholarshipProgram $program, array $attributes): ProgramOffering
    {
        return DB::transaction(function () use ($program, $attributes) {
            $offering = $program->offerings()->create([
                ...$attributes,
                'college_scope' => $attributes['college_scope'] ?? CollegeScope::All->value,
                'allocation_mode' => $attributes['allocation_mode'] ?? AllocationMode::Open->value,
            ]);

            $this->copyConfiguration($program, $offering);

            return $offering;
        });
    }

    /**
     * Copy every configuration set from the program template into the offering.
     * Called only for an offering with no configuration of its own.
     */
    protected function copyConfiguration(ScholarshipProgram $program, ProgramOffering $offering): void
    {
        $program->formFields()->orderBy('sort_order')->each(
            fn (ProgramFormField $field) => OfferingFormField::create([
                ...$field->copyableAttributes(),
                'program_offering_id' => $offering->id,
            ])
        );

        $program->eligibilityRules()->each(
            fn (ProgramEligibilityRule $rule) => OfferingEligibilityRule::create([
                ...$rule->copyableAttributes(),
                'program_offering_id' => $offering->id,
            ])
        );

        $program->retentionRules()->each(
            fn (ProgramRetentionRule $rule) => OfferingRetentionRule::create([
                ...$rule->copyableAttributes(),
                'program_offering_id' => $offering->id,
            ])
        );
    }
}
