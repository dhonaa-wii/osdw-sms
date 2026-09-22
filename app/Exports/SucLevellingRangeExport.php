<?php

namespace App\Exports;

use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SucLevellingRangeExport implements FromCollection, WithHeadings
{
    public function __construct(protected ?string $from, protected ?string $to) {}

    public function collection()
    {
        return app(ReportService::class)->sucLevellingByDateRange($this->from, $this->to)->map(fn (array $row) => array_values($row));
    }

    public function headings(): array
    {
        return [
            'Sequence No.', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Course', 'Year',
            'College', 'Name of Scholarship', 'Type', 'Funding', 'Campus', 'Amount',
        ];
    }
}
