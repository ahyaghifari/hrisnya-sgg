<?php

namespace App\Models\Sikartap;

use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penunjukan manual reviewer atasan Ide Inovasi per node Departemen (level 1) org struktur -
 * disederhanakan jadi 1 level saja (2026-08-26, instruksi user), bukan Departemen-atau-Divisi.
 * Ini SATU-SATUNYA sumber penentuan atasan (bukan lagi union dengan head_id org struktur -
 * dicabut karena datanya nyaris selalu kosong di lapangan). Lihat
 * Sikartap::ideInovasiDepartment()/ideInovasiAtasanCandidates(). */
class SikartapIdeInovasiReviewer extends Model
{
    use HasUlids;

    protected $table = 'sikartap_ide_inovasi_reviewers';

    protected $fillable = ['company_id', 'org_structure_id', 'user_id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class);
    }

    public function orgStructure(): BelongsTo
    {
        return $this->belongsTo(OrgStructure::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
