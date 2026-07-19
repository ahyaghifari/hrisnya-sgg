<?php

namespace App\Http\Controllers;

use App\Exports\Payroll\PayrollManualLineTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class PayrollManualLineTemplateController extends Controller
{
    public function downloadTemplate(string $type)
    {
        abort_unless(in_array($type, ['bon', 'insentif'], true), 404);

        return Excel::download(new PayrollManualLineTemplateExport, "template_import_{$type}.xlsx");
    }
}
