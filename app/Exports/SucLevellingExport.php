<?php

namespace App\Exports;

use App\Models\ProgramOffering;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SucLevellingExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected string $search = '',
        protected ?int $programId = null,
        protected ?ProgramOffering $offering = null,
        protected ?int $collegeId = null,
    ) {}

    public function collection()
    {
        $service = app(ReportService::class);

        return $service->sucLevelling(
            $service->granteeQuery($this->search, $this->programId, $this->offering, $this->collegeId)->get()
        )->map(fn (array $row) => array_values($row));
    }

    public function headings(): array
    {
        return [
            'Sequence No.', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Course', 'Year',
            'College', 'Name of Scholarship', 'Type', 'Funding', 'Campus', 'Amount',
        ];
    }
}
