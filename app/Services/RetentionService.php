<?php

namespace App\Services;

use App\Enums\EligibilityOperator;
use App\Models\Base\RetentionRule;
use App\Models\ScholarTerm;
use Illuminate\Support\Collection;

class RetentionService
{
    /**
     * Evaluate a scholar's submitted term against the offering's configured
     * retention rules, if any. An offering with no rules returns an empty
     * collection — staff judge continuation manually rather than the system
     * inventing a threshold nobody configured.
     *
     * @return Collection<int, array{rule: RetentionRule, passed: bool}>
     */
    public function evaluate(ScholarTerm $scholarTerm): Collection
    {
        $offering = $scholarTerm->award->programOffering;

        $attributes = [
            'gwa' => $scholarTerm->gwa,
            'units_enrolled' => $scholarTerm->units_enrolled,
            'failing_grade' => $scholarTerm->has_failing_grade ? 1 : 0,
        ];

        return $offering->retentionRules->map(fn (RetentionRule $rule) => [
            'rule' => $rule,
            'passed' => $this->passes($rule, $attributes),
        ]);
    }

    protected function passes(RetentionRule $rule, array $attributes): bool
    {
        $value = $attributes[$rule->field->value] ?? null;

        if ($value === null) {
            return false;
        }

        return match ($rule->operator) {
            EligibilityOperator::Lte => (float) $value <= (float) $rule->value,
            EligibilityOperator::Gte => (float) $value >= (float) $rule->value,
            EligibilityOperator::Eq => (float) $value === (float) $rule->value,
            EligibilityOperator::In => false,
        };
    }
}
