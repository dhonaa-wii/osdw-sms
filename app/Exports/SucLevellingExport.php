<?php

namespace App\Exports;

use App\Models\AcademicTerm;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SucLevellingExport implements FromCollection, WithHeadings
{
    public function __construct(protected AcademicTerm $term) {}

    public function collection()
    {
        return app(ReportService::class)->sucLevelling($this->term)->map(fn (array $row) => array_values($row));
    }

    public function headings(): array
    {
        return [
            'Sequence No.', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Course', 'Year',
            'College', 'Name of Scholarship', 'Type', 'Funding', 'Campus', 'Amount',
        ];
    }
}
