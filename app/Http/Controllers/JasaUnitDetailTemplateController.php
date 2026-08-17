<?php

namespace App\Http\Controllers;

use App\Exports\Payroll\JasaUnitDetailTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class JasaUnitDetailTemplateController extends Controller
{
    public function downloadTemplate()
    {
        return Excel::download(new JasaUnitDetailTemplateExport, 'template-input-jasa-unit.xlsx');
    }
}
