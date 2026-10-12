<?php

namespace App\Services;

use App\Models\Award;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use Illuminate\Support\Collection;

class ProviderService
{
    /**
     * Every scholarship program this provider holds, with the amount
     * actually provided, broken down per academic term and summed all-time.
     *
     * @return array{rows: Collection<int, array{program: ScholarshipProgram, terms: Collection, scholars: int, total: float}>, grandTotal: float, totalScholars: int}
     */
    public function fundingSummary(Provider $provider): array
    {
        $programs = $provider->scholarshipPrograms()->orderBy('name')->get();

        $awardsByProgram = Award::whereHas('programOffering', fn ($query) => $query->whereIn('scholarship_program_id', $programs->pluck('id')))
            ->with('academicTerm', 'programOffering')
            ->get()
            ->groupBy(fn (Award $award) => $award->programOffering->scholarship_program_id);

        $rows = $programs->map(function (ScholarshipProgram $program) use ($awardsByProgram) {
            $programAwards = $awardsByProgram->get($program->id, collect());

            $terms = $programAwards
                ->groupBy('academic_term_id')
                ->map(fn (Collection $termAwards) => [
                    'term' => $termAwards->first()->academicTerm,
                    'scholars' => $termAwards->count(),
                    'amount' => $termAwards->sum('amount'),
                ])
                ->sortByDesc(fn (array $row) => $row['term']->start_date)
                ->values();

            return [
                'program' => $program,
                'terms' => $terms,
                'scholars' => $programAwards->count(),
                'total' => $programAwards->sum('amount'),
            ];
        });

        return [
            'rows' => $rows,
            'grandTotal' => $rows->sum('total'),
            'totalScholars' => $rows->sum('scholars'),
        ];
    }

    /**
     * Every provider with an allocated budget, alongside the amount currently
     * committed across all of that provider's programs and offerings, all terms.
     *
     * @return Collection<int, array{provider: Provider, awarded: float}>
     */
    public function budgetUtilization(): Collection
    {
        return Provider::whereNotNull('allocated_budget')
            ->get()
            ->map(fn (Provider $provider) => [
                'provider' => $provider,
                'awarded' => Award::whereHas('programOffering.scholarshipProgram', fn ($q) => $q->where('provider_id', $provider->id))
                    ->sum('amount'),
            ]);
    }
}
