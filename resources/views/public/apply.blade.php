<?php

use App\Enums\EligibilityField;
use App\Enums\FieldType;
use App\Enums\OfferingStatus;
use App\Enums\ScholarshipCategory;
use App\Enums\Sex;
use App\Models\Applicant;
use App\Models\College;
use App\Models\ProgramOffering;
use App\Services\ApplicationService;
use App\Services\EligibilityService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public', array('title' => 'Apply'))] class extends Component
{
    use WithFileUploads;

    public ProgramOffering $offering;

    public ?string $referenceNo = null;

    public string $student_id = '';

    public string $last_name = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $suffix = '';

    public string $sex = '';

    public string $birthdate = '';

    public string $email = '';

    public string $mobile = '';

    public string $address = '';

    public string $college_id = '';

    public string $year_level = '';

    public string $gwa = '';

    public string $monthly_income = '';

    public bool $consent_publish = false;

    public array $responses = [];

    public array $uploads = [];

    public array $otherApplications = [];

    public function mount(ProgramOffering $offering): void
    {
        abort_unless($offering->registrationStatus() === OfferingStatus::Open, 404);

        $this->offering = $offering->load([
            'scholarshipProgram', 'academicTerm', 'eligibilityRules', 'offeringColleges.college',
            'formFields' => fn ($query) => $query->orderBy('sort_order'),
        ]);
    }

    public function updatedStudentId(string $value): void
    {
        $this->otherApplications = [];

        if ($value === '') {
            return;
        }

        $applicant = Applicant::where('student_id', $value)->first();

        if (! $applicant) {
            return;
        }

        $this->last_name = $applicant->last_name;
        $this->first_name = $applicant->first_name;
        $this->middle_name = (string) $applicant->middle_name;
        $this->suffix = (string) $applicant->suffix;
        $this->sex = $applicant->sex->value;
        $this->birthdate = $applicant->birthdate?->format('Y-m-d') ?? '';
        $this->email = $applicant->email;
        $this->mobile = (string) $applicant->mobile;
        $this->address = (string) $applicant->address;

        $this->otherApplications = $applicant->applications()
            ->with('programOffering.scholarshipProgram')
            ->get()
            ->map(fn ($application) => [
                'program' => $application->programOffering->scholarshipProgram->name,
                'reference_no' => $application->reference_no,
                'status' => $application->status->label(),
            ])
            ->all();
    }

    public function requiresGwa(): bool
    {
        return $this->offering->scholarshipProgram->category === ScholarshipCategory::Academic;
    }

    public function requiresIncome(): bool
    {
        return $this->offering->eligibilityRules->contains('field', EligibilityField::Income);
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

        return app(EligibilityService::class)->evaluate($this->offering, [
            'gwa' => $this->gwa,
            'year_level' => $this->year_level,
            'college' => $this->college_id,
            'income' => $this->monthly_income,
        ])->filter(fn ($result) => ! $result['passed']);
    }

    protected function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', 'in:'.implode(',', array_column(Sex::cases(), 'value'))],
            'birthdate' => ['nullable', 'date'],
            'email' => ['required', 'email', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'college_id' => ['required', 'exists:colleges,id'],
            'year_level' => ['required', 'integer', 'min:1', 'max:6'],
            'gwa' => $this->requiresGwa() ? ['required', 'numeric', 'min:0', 'max:100'] : ['nullable', 'numeric', 'min:0', 'max:100'],
            'monthly_income' => $this->requiresIncome() ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'],
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

    public function submit(): void
    {
        $applicant = Applicant::where('student_id', $this->student_id)->first();

        if ($applicant) {
            $duplicate = $applicant->applications()->where('program_offering_id', $this->offering->id)->first();

            if ($duplicate) {
                $this->addError('student_id', "You've already applied to this offering. Your reference number is {$duplicate->reference_no}.");

                return;
            }
        }

        $this->validate();

        $application = app(ApplicationService::class)->submit(
            $this->offering,
            [
                'student_id' => $this->student_id,
                'last_name' => $this->last_name,
                'first_name' => $this->first_name,
                'middle_name' => $this->middle_name ?: null,
                'suffix' => $this->suffix ?: null,
                'sex' => $this->sex,
                'birthdate' => $this->birthdate ?: null,
                'email' => $this->email,
                'mobile' => $this->mobile ?: null,
                'address' => $this->address ?: null,
            ],
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

        $this->referenceNo = $application->reference_no;
    }

    public function with(): array
    {
        return [
            'colleges' => $this->eligibleColleges(),
            'ineligibleRules' => $this->eligibilityPreview(),
        ];
    }
};
?>

<div>
    @if ($referenceNo)
        <div class="rounded-card border border-line bg-surface p-8 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="mt-3 font-body text-[11px] font-600 uppercase tracking-[0.08em] text-forest-700">Application submitted</p>
            <p class="mt-2 font-display text-[24px] font-600 text-ink">Your reference number</p>
            <p class="mt-2 font-mono text-[28px] font-500 text-forest-700">{{ $referenceNo }}</p>
            <p class="mt-4 font-body text-[14px] text-slate">
                Write this down &mdash; you'll need your Student ID and this reference number to check your application status.
                It's also on its way to your email.
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('track.lookup') }}" class="inline-flex items-center justify-center rounded-control bg-forest-600 px-5 py-2.5 font-body text-[14px] font-500 text-white transition hover:bg-forest-700">Track your application</a>
                <a href="{{ route('programs.index') }}" class="inline-flex items-center justify-center rounded-control border border-line bg-surface px-5 py-2.5 font-body text-[14px] font-500 text-ink transition hover:border-forest-600">Browse other scholarships</a>
            </div>
        </div>
    @else
        <x-ui.back-link :href="route('programs.show', $offering)" :label="$offering->scholarshipProgram->name" />

        <h1 class="mt-3 font-display text-[28px] font-600 text-ink">Apply &mdash; {{ $offering->scholarshipProgram->name }} &middot; {{ $offering->displayName() }}</h1>

        @if (! empty($otherApplications))
            <x-ui.alert type="info" class="mt-6">
                You've already applied to {{ count($otherApplications) }} other program(s) with this Student ID:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($otherApplications as $other)
                        <li>{{ $other['program'] }} &mdash; <span class="font-mono">{{ $other['reference_no'] }}</span> ({{ $other['status'] }})</li>
                    @endforeach
                </ul>
                Applying to more than one program is allowed.
            </x-ui.alert>
        @endif

        @if ($ineligibleRules->isNotEmpty())
            <x-ui.alert type="warning" class="mt-6">
                Based on what you've entered so far, you may not qualify:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($ineligibleRules as $result)
                        <li>{{ $result['rule']->message }}</li>
                    @endforeach
                </ul>
                You can still submit &mdash; OSDW will confirm your eligibility during screening.
            </x-ui.alert>
        @endif

        <form wire:submit="submit" class="mt-8 space-y-8">
            <div>
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Your identity</p>
                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="student_id" label="Student ID" wire:model.live.blur="student_id" required hint="Used to look up your existing applications, if any." />
                    <x-ui.select name="sex" label="Sex" wire:model="sex" placeholder="Select" required>
                        @foreach (\App\Enums\Sex::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="last_name" label="Last name" wire:model="last_name" required />
                    <x-ui.input name="first_name" label="First name" wire:model="first_name" required />
                    <x-ui.input name="middle_name" label="Middle name" wire:model="middle_name" />
                    <x-ui.input name="suffix" label="Suffix" wire:model="suffix" placeholder="Jr., III, etc." />
                    <x-ui.input name="birthdate" label="Birthdate" type="date" wire:model="birthdate" />
                    <x-ui.input name="email" label="Email" type="email" wire:model="email" required hint="Where your reference number can be sent." />
                    <x-ui.input name="mobile" label="Mobile number" wire:model="mobile" />
                    <x-ui.textarea name="address" label="Address" wire:model="address" rows="2">{{ $address }}</x-ui.textarea>
                </div>
            </div>

            <div class="border-t border-line pt-6">
                <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Academic standing</p>

                @if ($this->isCollegeRestricted())
                    <p class="mt-2 font-body text-[13px] text-slate">
                        This offering is open only to: <span class="text-ink">{{ $colleges->pluck('name')->implode(', ') }}</span>
                    </p>
                @endif

                <div class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                        <x-ui.input name="gwa" label="GWA" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.300ms="gwa" required hint="Enter your GWA as a percentage, e.g. 90." />
                    @endif
                    @if ($this->requiresIncome())
                        <x-ui.input name="monthly_income" label="Monthly household income" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="monthly_income" required hint="This offering requires it to check need-based eligibility." />
                    @endif
                </div>
            </div>

            @if ($offering->formFields->isNotEmpty())
                <div class="border-t border-line pt-6">
                    <p class="font-body text-[11px] font-600 uppercase tracking-[0.08em] text-mist">Program requirements</p>
                    <div class="mt-3 space-y-5">
                        @foreach ($offering->formFields as $field)
                            <div>
                                <label class="block font-body text-[13px] font-500 text-ink">
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
                                            <div class="space-y-1">
                                                @foreach ($field->optionList() as $choice)
                                                    <label class="flex items-center gap-2 font-body text-[14px] text-ink">
                                                        <input type="radio" wire:model="responses.{{ $field->id }}" value="{{ $choice }}"> {{ $choice }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            @break
                                        @case(\App\Enums\FieldType::Checkbox)
                                            <label class="flex items-center gap-2 font-body text-[14px] text-ink">
                                                <input type="checkbox" wire:model="responses.{{ $field->id }}" value="1">
                                                Yes
                                            </label>
                                            @break
                                        @case(\App\Enums\FieldType::File)
                                        @case(\App\Enums\FieldType::Image)
                                            <x-ui.file-upload
                                                name="uploads.{{ $field->id }}"
                                                :accept="$field->accepted_formats ? '.'.str_replace(',', ',.', str_replace(' ', '', $field->accepted_formats)) : ($field->field_type->isImage() ? 'image/*' : null)"
                                                :preview="$field->field_type->isImage()"
                                                hint="{{ $field->accepted_formats }} &middot; max {{ $field->max_size_mb }}MB"
                                            />
                                            @break
                                        @default
                                            <x-ui.input name="responses.{{ $field->id }}" type="{{ $field->field_type->value }}" wire:model="responses.{{ $field->id }}" />
                                    @endswitch
                                </div>

                                @error("responses.{$field->id}")
                                    <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                                @enderror
                                @error("uploads.{$field->id}")
                                    <p class="mt-1 font-body text-[13px] text-crimson-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="border-t border-line pt-6">
                <label class="flex items-start gap-2 font-body text-[14px] text-ink">
                    <input type="checkbox" wire:model="consent_publish" class="mt-1 rounded border-line text-forest-600 focus-visible:ring-forest-600">
                    I consent to my name being published in official OSDW postings (results, award lists) if I qualify.
                </label>
            </div>

            @error('student_id')
                <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
            @enderror

            <div class="flex justify-end border-t border-line pt-6">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit">Submit application</x-ui.button>
            </div>
        </form>
    @endif
</div>
