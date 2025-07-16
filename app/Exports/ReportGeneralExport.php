<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportGeneralExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting, WithMapping
{
    protected $rows;
    protected $title;

    public function __construct(array $rows,string $title)
    {
        $this->rows = $rows;
        $this->title = $title;
    }

    public function map($row): array
    {
        return [
            $row['holooCode'],
            $row['holooName'],
            $row['holooRegularPrice'],
            $row['holooStockQuantity'],
            $row['holooCustomerCode']
        ];
    }

    public function headings(): array
    {
        return [
            'holooCode',
            'holooName',
            'holooRegularPrice',
            'holooStockQuantity',
            'holooCustomerCode'
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;;
    }

    public function columnFormats(): array
    {
        return [

        ];
    }
}
