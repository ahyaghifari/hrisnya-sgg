<?php

namespace App\Models\Persuratan;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    protected $connection = 'persuratan';

    protected $table = 'perusahaan';

    protected $fillable = ['kode_surat', 'nama', 'hris_company_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Company HRIS (lintas DB, jadi bukan relasi Eloquent biasa). Null = penerbit di luar HRIS. */
    public function hrisCompany(): ?Companies
    {
        return $this->hris_company_id ? Companies::find($this->hris_company_id) : null;
    }
}
