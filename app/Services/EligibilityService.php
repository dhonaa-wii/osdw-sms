<?php

namespace App\Services;

use App\Enums\EligibilityField;
use App\Enums\EligibilityOperator;
use App\Enums\ScholarshipCategory;
use App\Models\Base\EligibilityRule;
use App\Models\ProgramOffering;
use Illuminate\Support\Collection;

class EligibilityService
{
    /**
     * Evaluate every eligibility rule on an offering against a set of applicant
     * attributes, keyed by EligibilityField value (gwa, year_level, college, income).
     *
     * In preview mode (the default), a blank attribute is treated as "not yet
     * entered" and skipped rather than failed, so a half-filled application form
     * doesn't show false warnings. Strict mode is for final screening, where a
     * required attribute that's still blank is a genuine eligibility failure.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, array{rule: EligibilityRule, passed: bool}>
     */
    public function evaluate(ProgramOffering $offering, array $attributes, bool $strict = false): Collection
    {
        return $offering->eligibilityRules->map(fn (EligibilityRule $rule) => [
            'rule' => $rule,
            'passed' => $this->passes($rule, $attributes, $strict),
        ]);
    }

    /**
     * An academic offering requires a minimum GWA, and an application that
     * misses it cannot be submitted. Returns the rule the GWA fails, if any.
     */
    public function unmetGwaRule(ProgramOffering $offering, mixed $gwa): ?EligibilityRule
    {
        if ($offering->category !== ScholarshipCategory::Academic) {
            return null;
        }

        return $offering->eligibilityRules
            ->filter(fn (EligibilityRule $rule) => $rule->field === EligibilityField::Gwa)
            ->first(fn (EligibilityRule $rule) => ! $this->passes($rule, ['gwa' => $gwa], strict: true));
    }

    /**
     * An offering's family income cap blocks an application above it. The cap
     * is never shown to students, so its message names no amount.
     */
    public function unmetIncomeRule(ProgramOffering $offering, mixed $income): ?EligibilityRule
    {
        return $offering->eligibilityRules
            ->filter(fn (EligibilityRule $rule) => $rule->field === EligibilityField::Income)
            ->first(fn (EligibilityRule $rule) => ! $this->passes($rule, ['income' => $income], strict: true));
    }

    /** The offering's family monthly income cap, or null when it has none. */
    public function incomeCap(ProgramOffering $offering): ?string
    {
        return $offering->eligibilityRules()->where('field', EligibilityField::Income->value)->value('value');
    }

    /** Sets or clears the offering's family monthly income cap — blank means no cap. */
    public function saveIncomeCap(ProgramOffering $offering, ?string $cap): void
    {
        if ($cap === null || $cap === '') {
            $offering->eligibilityRules()->where('field', EligibilityField::Income->value)->delete();

            return;
        }

        $offering->eligibilityRules()->updateOrCreate(
            ['field' => EligibilityField::Income->value],
            [
                'operator' => EligibilityOperator::Lte->value,
                'value' => $cap,
                'message' => "Your family's monthly income is above this scholarship's limit, so you can't apply.",
            ]
        );
    }

    protected function passes(EligibilityRule $rule, array $attributes, bool $strict): bool
    {
        $value = $attributes[$rule->field->value] ?? null;

        if ($value === null || $value === '') {
            return ! $strict;
        }

        return match ($rule->operator) {
            EligibilityOperator::Lte => (float) $value <= (float) $rule->value,
            EligibilityOperator::Gte => (float) $value >= (float) $rule->value,
            EligibilityOperator::Eq => (string) $value === (string) $rule->value,
            EligibilityOperator::In => in_array((string) $value, array_map('trim', explode(',', $rule->value)), true),
        };
    }
}
