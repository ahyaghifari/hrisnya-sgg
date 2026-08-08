<?php

namespace App\Http\Controllers;

use App\Enum\CustomPermissionEnum;
use App\Exports\Kepegawaian\KaryawanAccountTemplateExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class KaryawanAccountImportController extends Controller
{
    public function downloadTemplate()
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::IMPORT_KARYAWAN->value), 403);

        return Excel::download(new KaryawanAccountTemplateExport, 'template-import-akun-karyawan.xlsx');
    }
}
