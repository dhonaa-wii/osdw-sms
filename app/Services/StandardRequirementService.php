<?php

namespace App\Services;

use App\Enums\ApplicationKind;
use App\Enums\RequirementSource;
use App\Enums\RequirementStage;
use App\Models\ProgramOffering;
use App\Models\StandardFormField;
use App\Models\StandardRequirement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The standard requirements: one application form and one set of
 * disbursement requirements, kept by the admin on the Standard requirements
 * page. An offering that takes new applicants either uses them or has its
 * own set manually (the default).
 *
 * Using them copies them into the offering — its own form fields and
 * award-stage requirements, the same rows a manual offering has — so
 * screening, the application page and the review pages need no special
 * case. The copy follows every later change to the standard requirements
 * until the offering's first application comes in; from then on it is
 * frozen, because submitted answers and files point at its rows.
 */
class StandardRequirementService
{
    public function __construct(private OfferingRequirementService $offeringRequirements) {}

    /**
     * @param  array{label: string, instructions: ?string, submission_mode: string, accepted_formats: ?string, max_size_mb: ?int}  $attributes
     */
    public function saveRequirement(
        array $attributes,
        ?int $requirementId = null,
        ?UploadedFile $template = null,
        bool $removeTemplate = false,
    ): StandardRequirement {
        $requirement = DB::transaction(function () use ($attributes, $requirementId, $template, $removeTemplate) {
            if ($requirementId !== null) {
                $requirement = StandardRequirement::findOrFail($requirementId);
                $requirement->update($attributes);
            } else {
                $requirement = StandardRequirement::create($attributes + [
                    'sort_order' => (int) StandardRequirement::max('sort_order') + 1,
                ]);
            }

            if ($template !== null) {
                $this->deleteTemplate($requirement);
                $requirement->update([
                    'template_path' => $template->store('standard-requirements/templates', 'local'),
                    'template_name' => $template->getClientOriginalName(),
                ]);
            } elseif ($removeTemplate) {
                $this->deleteTemplate($requirement);
            }

            return $requirement;
        });

        $this->syncOfferings();

        return $requirement;
    }

    public function removeRequirement(StandardRequirement $requirement): void
    {
        if ($requirement->template_path) {
            Storage::disk('local')->delete($requirement->template_path);
        }

        $requirement->delete();
        $this->syncOfferings();
    }

    public function move(StandardRequirement $requirement, int $direction): void
    {
        $siblings = StandardRequirement::orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn ($sibling) => $sibling->id === $requirement->id);
        $target = $index + $direction;

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $sibling) {
                $sibling->update(['sort_order' => $position + 1]);
            }
        });

        $this->syncOfferings();
    }

    /**
     * An offering can switch to the standard requirements only before its
     * first application, since switching replaces its form.
     */
    public function canUseStandard(ProgramOffering $offering): bool
    {
        return $offering->takesNewApplicants()
            && ! $offering->applications()->where('kind', ApplicationKind::New->value)->exists();
    }

    public function useStandard(ProgramOffering $offering): void
    {
        if (! $this->canUseStandard($offering)) {
            throw new \RuntimeException('Applications have already come in, so this offering keeps its own form.');
        }

        DB::transaction(function () use ($offering): void {
            $offering->update(['requirements_source' => RequirementSource::Standard]);
            $this->copyInto($offering);
        });
    }

    /**
     * Keeps what was copied, now editable on the offering.
     */
    public function setManually(ProgramOffering $offering): void
    {
        $offering->update(['requirements_source' => RequirementSource::Manual]);
    }

    /**
     * Copy the current standard requirements into every offering that uses
     * them and has no applications yet. Run after every change on the
     * Standard requirements page.
     */
    public function syncOfferings(): void
    {
        ProgramOffering::where('requirements_source', RequirementSource::Standard->value)
            ->get()
            ->filter(fn (ProgramOffering $offering) => $this->canUseStandard($offering))
            ->each(fn (ProgramOffering $offering) => DB::transaction(fn () => $this->copyInto($offering)));
    }

    /**
     * Replace the offering's form fields and disbursement requirements with
     * copies of the standard ones. Blank forms are copied too, so each
     * offering owns its files the same way a manual one does.
     */
    protected function copyInto(ProgramOffering $offering): void
    {
        $offering->formFields()->delete();

        foreach (StandardFormField::orderBy('sort_order')->get() as $field) {
            $offering->formFields()->create($field->only($field->getFillable()));
        }

        $offering->additionalRequirements()
            ->where('stage', RequirementStage::Award->value)
            ->get()
            ->each(fn ($requirement) => $this->offeringRequirements->removeRequirement($requirement));

        foreach (StandardRequirement::orderBy('sort_order')->orderBy('id')->get() as $position => $requirement) {
            $templatePath = $this->copyTemplate($requirement, $offering);

            $offering->additionalRequirements()->create([
                'stage' => RequirementStage::Award,
                'label' => $requirement->label,
                'instructions' => $requirement->instructions,
                'submission_mode' => $requirement->submission_mode,
                'accepted_formats' => $requirement->accepted_formats,
                'max_size_mb' => $requirement->max_size_mb,
                'template_path' => $templatePath,
                'template_name' => $templatePath ? $requirement->template_name : null,
                'sort_order' => $position + 1,
            ]);
        }
    }

    protected function copyTemplate(StandardRequirement $requirement, ProgramOffering $offering): ?string
    {
        if (! $requirement->template_path || ! Storage::disk('local')->exists($requirement->template_path)) {
            return null;
        }

        $copy = "offerings/{$offering->id}/templates/".Str::random(40).'.'.pathinfo($requirement->template_path, PATHINFO_EXTENSION);
        Storage::disk('local')->copy($requirement->template_path, $copy);

        return $copy;
    }

    protected function deleteTemplate(StandardRequirement $requirement): void
    {
        if ($requirement->template_path) {
            Storage::disk('local')->delete($requirement->template_path);
        }

        $requirement->update(['template_path' => null, 'template_name' => null]);
    }
}
