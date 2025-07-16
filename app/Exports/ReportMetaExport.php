<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportMetaExport implements FromArray,  WithMultipleSheets
{
    protected $sheets;
    protected $max_count;

    public function __construct(array $sheets,$max_count)
    {
        $this->sheets = $sheets;
        $this->max_count = $max_count;
    }

    public function array(): array
    {
        return $this->sheets;
    }

    public function sheets(): array
    {
        $sheets=array();

        foreach($this->sheets as $category=>$sheet){
            $sheets[]=new ReportWocExport($sheet,$category,$this->max_count);
        }

        return $sheets;
    }
}
