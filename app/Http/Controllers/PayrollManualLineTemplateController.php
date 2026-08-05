<?php

namespace App\Http\Controllers;

use App\Exports\Payroll\BonDetailImportTemplateExport;
use App\Exports\Payroll\PayrollManualLineTemplateExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollManualLineTemplateController extends Controller
{
    /** Query param `mode` (default 'simple') pilih template sesuai bon_metode company yang lagi
     * dipilih di halaman Payroll - lihat App\Imports\Payroll\BonImport vs BonDetailImport. */
    public function downloadTemplate(Request $request, string $type)
    {
        // Insentif Import sudah dihapus (lihat docblock IncentiveCalculator) - cuma Bon yang
        // masih pakai template ini.
        abort_unless($type === 'bon', 404);

        $mode = $request->query('mode', 'simple');

        return $mode === 'detail'
            ? Excel::download(new BonDetailImportTemplateExport, "template_import_{$type}_detail.xlsx")
            : Excel::download(new PayrollManualLineTemplateExport, "template_import_{$type}.xlsx");
    }
}
