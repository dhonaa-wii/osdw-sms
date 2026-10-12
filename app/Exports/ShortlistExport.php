<?php

namespace App\Exports;

use App\Models\ProgramOffering;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ShortlistExport implements FromCollection, WithHeadings
{
    public function __construct(protected ProgramOffering $offering) {}

    public function collection()
    {
        return $this->offering->applications()
            ->with(['applicant', 'college'])
            ->where('status', 'shortlisted')
            ->orderBy('college_id')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn ($application) => [
                $application->applicant->last_name,
                $application->applicant->first_name,
                $application->college->code,
                $application->submitted_at?->format('Y-m-d'),
                $application->status->label(),
            ]);
    }

    public function headings(): array
    {
        return ['Last Name', 'First Name', 'College', 'Submitted', 'Status'];
    }
}
