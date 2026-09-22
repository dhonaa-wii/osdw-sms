<?php

namespace App\Services;

use App\Enums\EligibilityOperator;
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
