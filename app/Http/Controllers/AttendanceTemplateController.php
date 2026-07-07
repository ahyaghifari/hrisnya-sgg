<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceTemplateExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceTemplateController extends Controller
{
    public function downloadTemplate()
    {
        return Excel::download(new AttendanceTemplateExport, 'attendance_template.xlsx');
    }
}