<?php

use App\Enums\EligibilityField;
use App\Enums\OfferingStatus;
use App\Enums\ScholarshipCategory;
use App\Models\Applicant;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Services\ApplicationService;
use App\Services\EligibilityService;
use App\Services\ScholarshipHoldingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Apply to an offering as the signed-in student. Their identity comes from
 * their profile (edited in Settings); this form asks only for this term's
 * academic standing and the offering's own fields.
 */
new #[Layout('layouts::public', array('title' => 'Apply', 'noindex' => true))] class extends Component
{
    use WithFileUploads;

    public ProgramOffering $offering;

    public string $college_id = '';

    public string $year_level = '';

    public string $gwa = '';

    public string $monthly_income = '';

    public bool $consent_publish = false;

    public array $responses = [];

    public array $uploads = [];

    public bool $reviewing = false;

    public ?int $previewFieldId = null;

    public function mount(ProgramOffering $offering): void
    {
        // A renewal offering takes new applicants only when the admin opened it to them.
        abort_unless($offering->takesNewApplicants() && $offering->registrationStatus() === OfferingStatus::Open, 404);

        $existing = $this->applicant()->applications()->where('program_offering_id', $offering->id)->first();

        if ($existing) {
            $this->redirectRoute('student.applications.show', $existing, navigate: true);

            return;
        }

        // A national grantee can't apply to another national scholarship; the
        // scholarship page says why in place of the Apply button.
        if (app(ScholarshipHoldingService::class)->applyBlocker($this->applicant(), $offering)) {
            $this->redirectRoute('programs.show', $offering, navigate: true);

            return;
        }

        $this->offering = $offering->load([
            'scholarshipProgram', 'academicTerm', 'eligibilityRules', 'offeringColleges.college',
            'formFields' => fn ($query) => $query->orderBy('sort_order'),
        ]);
    }

    public function applicant(): Applicant
    {
        return auth()->user()->applicant;
    }

    public function requiresGwa(): bool
    {
        return $this->offering->category === ScholarshipCategory::Academic;
    }

    public function isCollegeRestricted(): bool
    {
        return $this->offering->college_scope->value === 'restricted' || $this->offering->allocation_mode->value === 'quota';
    }

    public function eligibleColleges(): Collection
    {
        if ($this->isCollegeRestricted()) {
            return $this->offering->offeringColleges->pluck('college')->sortBy('name')->values();
        }

        return College::where('is_active', true)->orderBy('name')->get();
    }

    public function eligibilityPreview(): Collection
    {
        if ($this->offering->eligibilityRules->isEmpty()) {
            return collect();
        }

        // An academic offering's GWA minimum and the income cap block
        // submission, so they show on their own fields instead.
        return app(EligibilityService::class)->evaluate($this->offering, [
            'gwa' => $this->gwa,
            'year_level' => $this->year_level,
            'college' => $this->college_id,
            'income' => $this->monthly_income,
        ])->filter(fn ($result) => ! $result['passed'])
            ->reject(fn ($result) => $this->requiresGwa() && $result['rule']->field === EligibilityField::Gwa)
            ->reject(fn ($result) => $result['rule']->field === EligibilityField::Income)
            ->values();
    }

    public function updatedGwa(): void
    {
        $this->resetErrorBag('gwa');

        if ($this->gwa !== '' && is_numeric($this->gwa)) {
            $this->ensureGwaMeetsMinimum();
        }
    }

    public function updatedMonthlyIncome(): void
    {
        $this->resetErrorBag('monthly_income');

        if ($this->monthly_income !== '' && is_numeric($this->monthly_income)) {
            $this->ensureIncomeWithinCap();
        }
    }

    /**
     * Stops the form with an error on the income field when it is above the
     * offering's cap. The message never states the cap.
     */
    protected function ensureIncomeWithinCap(): bool
    {
        $unmetRule = app(EligibilityService::class)->unmetIncomeRule($this->offering, $this->monthly_income);

        if ($unmetRule) {
            $this->addError('monthly_income', $unmetRule->message);
        }

        return $unmetRule === null;
    }

    /**
     * Stops the form with an error on the GWA field when it is below the
     * academic offering's required GWA.
     */
    protected function ensureGwaMeetsMinimum(): bool
    {
        $unmetRule = app(EligibilityService::class)->unmetGwaRule($this->offering, $this->gwa);

        if ($unmetRule) {
            $this->addError('gwa', $unmetRule->message);
        }

        return $unmetRule === null;
    }

    /** Runs both blocking checks, so the student sees every failing field at once. */
    protected function meetsSubmissionRules(): bool
    {
        $gwaMet = $this->ensureGwaMeetsMinimum();
        $incomeMet = $this->ensureIncomeWithinCap();

        return $gwaMet && $incomeMet;
    }

    protected function rules(): array
    {
        $rules = [
            'college_id' => ['required', 'exists:colleges,id'],
            'year_level' => ['required', 'integer', 'min:1', 'max:6'],
            'gwa' => $this->requiresGwa() ? ['required', 'numeric', 'min:0', 'max:100'] : ['nullable', 'numeric', 'min:0', 'max:100'],
            'monthly_income' => ['required', 'numeric', 'min:0'],
        ];

        foreach ($this->offering->formFields as $field) {
            if ($field->field_type->isFile()) {
                $fileRules = ['file', 'max:'.($field->max_size_mb * 1024)];

                if ($field->accepted_formats) {
                    $fileRules[] = 'mimes:'.$field->accepted_formats;
                }

                $rules["uploads.{$field->id}"] = $field->is_required ? array_merge(['required'], $fileRules) : array_merge(['nullable'], $fileRules);
            } else {
                $rules["responses.{$field->id}"] = $field->is_required ? ['required', 'string'] : ['nullable', 'string'];
            }
        }

        return $rules;
    }

    public function goToReview(): void
    {
        $this->validate();

        if (! $this->meetsSubmissionRules()) {
            return;
        }

        $this->reviewing = true;
        $this->scrollToTop();
    }

    public function backToEdit(): void
    {
        $this->reviewing = false;
        $this->previewFieldId = null;
        $this->scrollToTop();
    }

    protected function scrollToTop(): void
    {
        $this->js("document.getElementById('apply-top')?.scrollIntoView({ behavior: 'smooth' })");
    }

    public function previewFile(int $fieldId): void
    {
        $this->previewFieldId = $fieldId;
    }

    public function closePreviewFile(): void
    {
        $this->previewFieldId = null;
    }

    public function previewUrl(?TemporaryUploadedFile $upload): ?string
    {
        if (! $upload) {
            return null;
        }

        $mime = (string) $upload->getMimeType();

        if (str_starts_with($mime, 'image/') || $mime === 'application/pdf') {
            return $upload->temporaryUrl();
        }

        return null;
    }

    public function submit(ApplicationService $applications): void
    {
        $this->validate();

        if (! $this->meetsSubmissionRules()) {
            $this->backToEdit();

            return;
        }

        try {
            $application = $applications->submit(
                $this->offering,
                $this->applicant(),
                [
                    'college_id' => $this->college_id,
                    'year_level' => $this->year_level,
                    'gwa' => $this->gwa,
                    'monthly_income' => $this->monthly_income,
                    'consent_publish' => $this->consent_publish,
                ],
                $this->responses,
                $this->uploads,
            );
        } catch (\RuntimeException $e) {
            $this->addError('submit', $e->getMessage());

            return;
        }

        session()->flash('toast', 'Application submitted.');
        $this->redirectRoute('student.applications.show', $application, navigate: true);
    }

    public function with(): array
    {
        return [
            'colleges' => $this->eligibleColleges(),
            'ineligibleRules' => $this->eligibilityPreview(),
            'otherApplications' => $this->applicant()->applications()
                ->with('programOffering.scholarshipProgram')
                ->latest('id')
                ->get(),
        ];
    }
};
?>


@php
    $cardHeading = 'flex items-center gap-2 border-b border-line px-5 py-3 font-display text-[15px] font-600 text-ink';
    $termClass = 'font-body text-[12px] text-mist';
    $valueClass = 'font-body text-[14px] text-ink';
@endphp

<div>
    <x-ui.back-link :href="route('programs.show', $offering)" :label="$offering->scholarshipProgram->name" wire:navigate />

    <div id="apply-top" class="mt-3 flex scroll-mt-20 flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-[22px] font-600 leading-snug text-ink">Apply &mdash; {{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>

        <ol class="flex items-center gap-2 font-body text-[13px]" aria-label="Steps">
            <li class="flex items-center gap-1.5 {{ $reviewing ? 'text-slate' : 'font-500 text-navy-700' }}" @if (! $reviewing) aria-current="step" @endif>
                <span class="flex size-5 items-center justify-center rounded-full text-[11px] {{ $reviewing ? 'bg-navy-600 text-white' : 'border border-navy-600 text-navy-700' }}">
                    @if ($reviewing) <x-ui.icon name="check" class="size-3" /> @else 1 @endif
                </span>
                Your details
            </li>
            <li class="h-px w-6 bg-line" aria-hidden="true"></li>
            <li class="flex items-center gap-1.5 {{ $reviewing ? 'font-500 text-navy-700' : 'text-mist' }}" @if ($reviewing) aria-current="step" @endif>
                <span class="flex size-5 items-center justify-center rounded-full border text-[11px] {{ $reviewing ? 'border-navy-600 text-navy-700' : 'border-line' }}">2</span>
                Review
            </li>
        </ol>
    </div>

    <form wire:submit="{{ $reviewing ? 'submit' : 'goToReview' }}" novalidate class="mt-4">
        {{-- Step 1: details. Hidden rather than removed while reviewing, so chosen files and inputs keep their state. --}}
        <div @class(['space-y-4', 'hidden' => $reviewing])>
            <section class="rounded-card border border-line bg-surface">
                <h2 class="{{ $cardHeading }}"><x-ui.icon name="user" class="size-4 text-navy-600" /> Your identity</h2>
                @include('student.partials.applicant-summary', ['applicant' => $this->applicant()])
                <p class="border-t border-line px-5 py-3 font-body text-[13px] text-slate">
                    Something wrong? <a href="{{ route('student.settings') }}" wire:navigate class="font-500 text-navy-700 hover:underline">Update your profile in Settings</a> before you fill in this form.
                </p>

                @if ($otherApplications->isNotEmpty())
                    <div class="mx-5 mb-5 flex items-start gap-2 rounded-control border border-azure-600/30 bg-paper px-3 py-2.5">
                        <x-ui.icon name="info" class="mt-0.5 size-4 text-azure-600" />
                        <div class="font-body text-[13px] text-ink">
                            <p>You've applied to {{ $otherApplications->count() }} other {{ $otherApplications->count() === 1 ? 'program' : 'programs' }}. Applying to more than one is allowed.</p>
                            <ul class="mt-1 space-y-0.5 text-slate">
                                @foreach ($otherApplications as $other)
                                    <li>{{ $other->programOffering->scholarshipProgram->name }} &middot; {{ $other->statusLabel() }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </section>

            <section class="rounded-card border border-line bg-surface">
                <h2 class="{{ $cardHeading }}"><x-ui.icon name="graduation" class="size-4 text-navy-600" /> Academic standing</h2>
                <div class="p-5">
                    @if ($this->isCollegeRestricted())
                        <p class="mb-4 font-body text-[13px] text-slate">Open only to <span class="text-ink">{{ $colleges->pluck('name')->implode(', ') }}</span></p>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.select name="college_id" label="College" wire:model.live="college_id" placeholder="Select your college" required>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select name="year_level" label="Year level" wire:model.live="year_level" placeholder="Select" required>
                            @foreach (range(1, 6) as $level)
                                <option value="{{ $level }}">Year {{ $level }}</option>
                            @endforeach
                        </x-ui.select>

                        @if ($this->requiresGwa())
                            <x-ui.input name="gwa" label="GWA" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.400ms="gwa" required class="font-mono" hint="As a percentage, e.g. 90." />
                        @endif
                        <x-ui.input name="monthly_income" label="Family monthly income" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="monthly_income" required class="font-mono" hint="Your family's total monthly income in pesos." />
                    </div>

                    @if ($ineligibleRules->isNotEmpty())
                        <div class="mt-4 flex items-start gap-2 rounded-control border border-amber-600/30 bg-paper px-3 py-2.5">
                            <x-ui.icon name="alert-circle" class="mt-0.5 size-4 text-amber-600" />
                            <div class="font-body text-[13px] text-ink">
                                <p>Based on what you've entered, you may not qualify:</p>
                                <ul class="mt-1 space-y-0.5 text-slate">
                                    @foreach ($ineligibleRules as $result)
                                        <li>&bull; {{ $result['rule']->message }}</li>
                                    @endforeach
                                </ul>
                                <p class="mt-1 text-slate">You can still submit. OSDW confirms your eligibility during screening.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            @if ($offering->formFields->isNotEmpty())
                <section class="rounded-card border border-line bg-surface">
                    <h2 class="{{ $cardHeading }}"><x-ui.icon name="form" class="size-4 text-navy-600" /> Program requirements</h2>
                    <div class="divide-y divide-line">
                        @foreach ($offering->formFields as $field)
                            <div class="px-5 py-4" wire:key="field-{{ $field->id }}">
                                <label @unless ($field->field_type->isFile() || in_array($field->field_type, [\App\Enums\FieldType::Radio, \App\Enums\FieldType::Checkbox], true)) for="responses.{{ $field->id }}" @endunless class="block font-body text-[13px] font-500 text-ink">
                                    {{ $field->label }}
                                    @if ($field->is_required)<span class="text-crimson-600">*</span>@endif
                                </label>
                                @if ($field->description)
                                    <p class="font-body text-[12px] text-slate">{{ $field->description }}</p>
                                @endif

                                <div class="mt-1">
                                    @switch($field->field_type)
                                        @case(\App\Enums\FieldType::Textarea)
                                            <x-ui.textarea name="responses.{{ $field->id }}" wire:model="responses.{{ $field->id }}" rows="3">{{ $responses[$field->id] ?? '' }}</x-ui.textarea>
                                            @break
                                        @case(\App\Enums\FieldType::Select)
                                            <x-ui.select name="responses.{{ $field->id }}" wire:model="responses.{{ $field->id }}" placeholder="Select">
                                                @foreach ($field->optionList() as $choice)
                                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                                @endforeach
                                            </x-ui.select>
                                            @break
                                        @case(\App\Enums\FieldType::Radio)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($field->optionList() as $choice)
                                                    <label class="flex cursor-pointer items-center gap-2 rounded-control border border-line px-3 py-2 font-body text-[14px] text-ink transition-colors hover:border-navy-600 has-[:checked]:border-navy-600 has-[:checked]:bg-navy-50">
                                                        <input type="radio" wire:model="responses.{{ $field->id }}" value="{{ $choice }}" class="accent-navy-600"> {{ $choice }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            @break
                                        @case(\App\Enums\FieldType::Checkbox)
                                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-control border border-line px-3 py-2 font-body text-[14px] text-ink transition-colors hover:border-navy-600 has-[:checked]:border-navy-600 has-[:checked]:bg-navy-50">
                                                <input type="checkbox" wire:model="responses.{{ $field->id }}" value="1" class="accent-navy-600">
                                                Yes
                                            </label>
                                            @break
                                        @case(\App\Enums\FieldType::File)
                                        @case(\App\Enums\FieldType::Image)
                                            <x-ui.file-upload
                                                name="uploads.{{ $field->id }}"
                                                :accept="$field->accepted_formats ? '.'.str_replace(',', ',.', str_replace(' ', '', $field->accepted_formats)) : ($field->field_type->isImage() ? 'image/*' : null)"
                                                :preview="$field->field_type->isImage()"
                                                hint="{{ $field->accepted_formats }} · max {{ $field->max_size_mb }}MB"
                                            />
                                            @break
                                        @default
                                            <x-ui.input name="responses.{{ $field->id }}" type="{{ $field->field_type->value }}" wire:model="responses.{{ $field->id }}" />
                                    @endswitch
                                </div>

                                {{-- The input components print their own errors; radios and checkboxes don't. --}}
                                @if (in_array($field->field_type, [\App\Enums\FieldType::Radio, \App\Enums\FieldType::Checkbox], true))
                                    @error("responses.{$field->id}")
                                        <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-card border border-line bg-surface p-5">
                <label class="flex cursor-pointer items-start gap-2.5 font-body text-[14px] text-ink">
                    <input type="checkbox" wire:model="consent_publish" class="mt-1 size-4 shrink-0 accent-navy-600">
                    I consent to my name being published in official OSDW postings (results, award lists) if I qualify.
                </label>

                @if ($errors->isNotEmpty())
                    <p class="mt-4 flex items-start gap-1.5 border-t border-line pt-4 font-body text-[13px] text-crimson-600">
                        <x-ui.icon name="alert-circle" class="mt-0.5 size-4" />
                        <span>Some fields need your attention. Check the messages above.</span>
                    </p>
                @endif

                <div class="mt-4 flex justify-end border-t border-line pt-4">
                    <x-ui.button type="submit" loading="goToReview">Review application <x-ui.icon name="arrow-right" class="size-4" /></x-ui.button>
                </div>
            </section>
        </div>

        {{-- Step 2: review --}}
        @if ($reviewing)
            <div class="space-y-4">
                <section class="rounded-card border border-line bg-surface">
                    <h2 class="{{ $cardHeading }}"><x-ui.icon name="user" class="size-4 text-navy-600" /> Your identity</h2>
                    @include('student.partials.applicant-summary', ['applicant' => $this->applicant()])
                </section>

                <section class="rounded-card border border-line bg-surface">
                    <h2 class="{{ $cardHeading }}"><x-ui.icon name="graduation" class="size-4 text-navy-600" /> Academic standing</h2>
                    <dl class="grid grid-cols-1 gap-x-5 gap-y-3 p-5 sm:grid-cols-2">
                        <div><dt class="{{ $termClass }}">College</dt><dd class="{{ $valueClass }}">{{ $colleges->firstWhere('id', $college_id)?->name }}</dd></div>
                        <div><dt class="{{ $termClass }}">Year level</dt><dd class="{{ $valueClass }}">Year {{ $year_level }}</dd></div>
                        @if ($this->requiresGwa())
                            <div><dt class="{{ $termClass }}">GWA</dt><dd class="font-mono text-[14px] text-ink">{{ $gwa }}</dd></div>
                        @endif
                        <div><dt class="{{ $termClass }}">Family monthly income</dt><dd class="font-mono text-[14px] text-ink">{{ $monthly_income }}</dd></div>
                    </dl>
                </section>

                @if ($offering->formFields->isNotEmpty())
                    <section class="rounded-card border border-line bg-surface">
                        <h2 class="{{ $cardHeading }}"><x-ui.icon name="form" class="size-4 text-navy-600" /> Program requirements</h2>
                        <ul class="divide-y divide-line">
                            @foreach ($offering->formFields as $field)
                                @php $upload = $field->field_type->isFile() ? ($uploads[$field->id] ?? null) : null; @endphp
                                <li class="flex items-center gap-3 px-5 py-2.5" wire:key="review-field-{{ $field->id }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="{{ $termClass }}">{{ $field->label }}</p>
                                        <p class="truncate {{ $valueClass }}">
                                            @if ($field->field_type->isFile())
                                                {{ $upload?->getClientOriginalName() ?? '—' }}
                                            @else
                                                {{ $responses[$field->id] ?? '—' }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($upload)
                                        <button type="button" wire:click="previewFile({{ $field->id }})" class="inline-flex shrink-0 items-center gap-1 rounded-control font-body text-[13px] font-500 text-navy-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-600">
                                            <x-ui.icon name="eye" class="size-3.5" /> View
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="rounded-card border border-line bg-surface p-5">
                    <p class="flex items-center gap-2 font-body text-[14px] text-ink">
                        <x-ui.icon :name="$consent_publish ? 'check-circle' : 'x'" class="size-4 {{ $consent_publish ? 'text-navy-600' : 'text-mist' }}" />
                        {{ $consent_publish ? 'Your name may be published if you qualify.' : 'Your name will not be published.' }}
                    </p>

                    @error('submit')
                        <p class="mt-4 flex items-start gap-1.5 border-t border-line pt-4 font-body text-[13px] text-crimson-600">
                            <x-ui.icon name="alert-circle" class="mt-0.5 size-4" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror

                    <div class="mt-4 flex flex-wrap justify-between gap-2 border-t border-line pt-4">
                        <x-ui.button variant="secondary" wire:click="backToEdit" loading="backToEdit"><x-ui.icon name="pencil" class="size-4" /> Back to edit</x-ui.button>
                        <x-ui.button type="submit" loading="submit">Submit application</x-ui.button>
                    </div>
                </section>
            </div>
        @endif
    </form>

    <x-ui.modal :show="$previewFieldId !== null" title="{{ $offering->formFields->firstWhere('id', $previewFieldId)?->label }}">
        @php $previewUpload = $previewFieldId ? ($uploads[$previewFieldId] ?? null) : null; @endphp
        @if ($previewUpload)
            @php $previewFileUrl = $this->previewUrl($previewUpload); $previewMime = (string) $previewUpload->getMimeType(); @endphp
            @if ($previewFileUrl && str_starts_with($previewMime, 'image/'))
                <img src="{{ $previewFileUrl }}" alt="{{ $previewUpload->getClientOriginalName() }}" class="max-h-[70vh] w-full rounded-control object-contain">
            @elseif ($previewFileUrl && $previewMime === 'application/pdf')
                <iframe src="{{ $previewFileUrl }}" class="h-[70vh] w-full rounded-control border border-line" title="{{ $previewUpload->getClientOriginalName() }}"></iframe>
            @else
                <p class="font-body text-[14px] text-ink">{{ $previewUpload->getClientOriginalName() }}</p>
                <p class="mt-1 font-body text-[13px] text-slate">Preview isn't available for this file type.</p>
            @endif
        @endif
        <div class="mt-4 flex justify-end">
            <x-ui.button variant="secondary" wire:click="closePreviewFile">Close</x-ui.button>
        </div>
    </x-ui.modal>
</div>
