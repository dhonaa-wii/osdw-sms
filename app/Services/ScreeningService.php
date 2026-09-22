<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStatusLog;
use Illuminate\Support\Facades\DB;

class ScreeningService
{
    public function __construct(protected EligibilityService $eligibilityService) {}

    public function screen(Application $application): void
    {
        $application->loadMissing([
            'programOffering.eligibilityRules',
            'programOffering.formFields',
            'documents',
            'fieldResponses',
            'applicant',
        ]);

        DB::transaction(function () use ($application): void {
            $application->screenings()->delete();

            $this->recordCompletenessChecks($application);
            $this->recordEligibilityChecks($application);
            $this->recordDuplicateNotice($application);

            $fromStatus = $application->status;
            $toStatus = 'screening';

            $application->update(['status' => $toStatus, 'screened_at' => now()]);

            if ($fromStatus->value !== $toStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ]);
            }
        });
    }

    protected function recordCompletenessChecks(Application $application): bool
    {
        $allPassed = true;

        foreach ($application->programOffering->formFields->where('is_required', true) as $field) {
            if ($field->field_type->isFile()) {
                $passed = $application->documents->contains('offering_form_field_id', $field->id);
            } else {
                $response = $application->fieldResponses->firstWhere('offering_form_field_id', $field->id);
                $passed = $response !== null && trim((string) $response->value) !== '';
            }

            $allPassed = $allPassed && $passed;

            $application->screenings()->create([
                'offering_eligibility_rule_id' => null,
                'check_type' => 'completeness',
                'passed' => $passed,
                'message' => $passed ? "{$field->label} was provided." : "{$field->label} is required but was not provided.",
            ]);
        }

        return $allPassed;
    }

    protected function recordEligibilityChecks(Application $application): bool
    {
        $results = $this->eligibilityService->evaluate($application->programOffering, [
            'gwa' => $application->gwa,
            'year_level' => $application->year_level,
            'college' => $application->college_id,
            'income' => $application->monthly_income,
        ], strict: true);

        foreach ($results as $result) {
            $application->screenings()->create([
                'offering_eligibility_rule_id' => $result['rule']->id,
                'check_type' => 'eligibility',
                'passed' => $result['passed'],
                'message' => $result['passed'] ? "{$result['rule']->describe()}: met." : $result['rule']->message,
            ]);
        }

        return $results->every(fn ($result) => $result['passed']);
    }

    protected function recordDuplicateNotice(Application $application): void
    {
        $otherCount = $application->applicant->applications()->where('id', '!=', $application->id)->count();

        if ($otherCount === 0) {
            return;
        }

        $application->screenings()->create([
            'offering_eligibility_rule_id' => null,
            'check_type' => 'duplicate',
            'passed' => true,
            'message' => "This applicant has {$otherCount} other application(s) on file. Applying to multiple programs is allowed.",
        ]);
    }
}
