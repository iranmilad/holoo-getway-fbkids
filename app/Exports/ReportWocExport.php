<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportWocExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting, WithMapping
{
    protected $rows;
    protected $title;
    protected $max_count;

    public function __construct(array $rows,string $title,$max_count)
    {
        $this->rows = $rows;
        $this->title = $title;
        $this->max_count= $max_count;
    }

    public function map($row): array
    {
        $record=[];

        $record= [
            $row["id"] ,
            $row["name"] ,
            $row["sku"] ,
            $row["price"] ,
            $row["regular_price"],
            $row["sale_price"] ,
            $row["stock_quantity"],
        ];
        for ($i=0; $i < $this->max_count ; $i++) {
            if ($i>0) {
                if (!isset($row["_holo_sku_".$i])) break;
                array_push($record,$row["_holo_sku_".$i]);
            }
            else{
                array_push($record,$row["_holo_sku"]);
            }
        }
        return $record;
    }

    public function headings(): array
    {
        $head=[
            "id",
            "name",
            "sku",
            "price",
            "regular_price",
            "sale_price",
            "stock_quantity"
        ];
        for ($i=0; $i < $this->max_count ; $i++) {
            array_push($head,"meta:_holo_sku");
        }

        return $head;
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
