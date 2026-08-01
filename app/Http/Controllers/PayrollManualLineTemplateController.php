<?php

namespace App\Http\Controllers;

use App\Exports\Payroll\PayrollManualLineTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class PayrollManualLineTemplateController extends Controller
{
    public function downloadTemplate(string $type)
    {
        // Insentif Import sudah dihapus (lihat docblock IncentiveCalculator) - cuma Bon yang
        // masih pakai template ini.
        abort_unless($type === 'bon', 404);

        return Excel::download(new PayrollManualLineTemplateExport, "template_import_{$type}.xlsx");
    }
}
