<?php

namespace App\Livewire\Concerns;

use App\Enums\Sex;
use App\Models\Applicant;

/**
 * The personal details a student keeps on their account, shared by the
 * first-time profile setup and the Settings page. The student ID is not
 * here: it is entered once at setup and fixed after that.
 */
trait EditsStudentProfile
{
    public string $last_name = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $suffix = '';

    public string $sex = '';

    public string $birthdate = '';

    public string $mobile = '';

    public string $address = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function profileRules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', 'in:'.implode(',', array_column(Sex::cases(), 'value'))],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function profileData(): array
    {
        return $this->only(['last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'birthdate', 'mobile', 'address']);
    }

    protected function fillProfile(Applicant $applicant): void
    {
        $this->last_name = $applicant->last_name;
        $this->first_name = $applicant->first_name;
        $this->middle_name = (string) $applicant->middle_name;
        $this->suffix = (string) $applicant->suffix;
        $this->sex = $applicant->sex->value;
        $this->birthdate = $applicant->birthdate?->format('Y-m-d') ?? '';
        $this->mobile = (string) $applicant->mobile;
        $this->address = (string) $applicant->address;
    }
}
