<?php

namespace App\Http\Controllers;

use App\Exports\Employees\EmployeeTemplateExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeImportController extends Controller
{
    public function downloadTemplate()
    {
        $companyId = Auth::user()?->company_id;

        return Excel::download(
            new EmployeeTemplateExport($companyId),
            'template-import-karyawan.xlsx'
        );
    }
}
