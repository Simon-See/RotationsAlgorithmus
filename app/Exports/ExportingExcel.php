<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class ExportingExcel implements FromCollection
{
    public function collection()
    {
        return null;
    }
}
