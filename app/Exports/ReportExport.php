<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements FromArray,  WithMultipleSheets
{
    protected $sheets;

    public function __construct(array $sheets)
    {
        $this->sheets = $sheets;
    }

    public function array(): array
    {
        return $this->sheets;
    }

    public function sheets(): array
    {
        $sheets=array();

        foreach($this->sheets as $category=>$sheet){
            $sheets[]=new ReportGeneralExport($sheet,$category);
        }

        return $sheets;
    }
}